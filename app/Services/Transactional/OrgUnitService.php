<?php
// app/Services/Transactional/OrgUnitService.php
namespace App\Services\Transactional;

use App\Exceptions\BusinessException;
use App\Repositories\Transactional\JurusanRepository;
use App\Repositories\Transactional\OrgUnitRepository;
use App\Repositories\Transactional\OrgUnitTypeRepository;
use Illuminate\Support\Facades\DB;

/**
 * OrgUnitService — satu-satunya jalur tulis untuk pohon unit organisasi
 * (`org_units`).
 *
 * Dua aturan bisnis yang tidak bisa (aman) ditegakkan di level database
 * pada Postgres untuk pohon multi-level:
 *
 *   1. Anti-siklus (DFR-09): unit tidak boleh jadi anak dari keturunannya
 *      sendiri. Diperiksa dengan menelusuri rantai parent_id ke atas
 *      sebelum reparent disetujui -- lihat assertNoCycle().
 *   2. Level pada parent harus lebih dangkal dari level unit anak (mis.
 *      Departemen tidak boleh jadi parent dari Fakultas) -- lihat
 *      assertParentIsShallower().
 *
 * Skala pohon ini puluhan baris (36 prodi, ~11 jurusan di POLBAN), jadi
 * penelusuran iteratif sederhana ini cukup; tidak perlu recursive CTE.
 */
class OrgUnitService
{
    private const CONN = 'oltp';

    /** Batas kedalaman wajar -- DFR-04 membatasi custom template maksimal 5 level. */
    private const MAX_DEPTH_GUARD = 20;

    public function __construct(
        private readonly OrgUnitRepository $orgUnitRepo,
        private readonly OrgUnitTypeRepository $orgUnitTypeRepo,
        // Default disediakan supaya konstruksi manual yang sudah ada di
        // Fase 1/3 (`new OrgUnitService($orgUnitRepo, $orgUnitTypeRepo)`)
        // tetap kompil tanpa perlu diubah.
        private readonly JurusanService $jurusanService = new JurusanService(new JurusanRepository()),
    ) {}

    public function create(int $orgUnitTypeId, string $name, ?int $parentId = null, bool $isActive = true): array
    {
        $name = trim($name);
        if ($name === '') {
            throw new BusinessException('Nama unit organisasi tidak boleh kosong.', 422);
        }

        $type = $this->orgUnitTypeRepo->find($orgUnitTypeId);
        if ($type === null) {
            throw new BusinessException('Level struktur organisasi tidak ditemukan.', 404);
        }

        $parent = null;
        if ($parentId !== null) {
            $parent = $this->orgUnitRepo->find($parentId);
            if ($parent === null) {
                throw new BusinessException('Unit induk tidak ditemukan.', 404);
            }
            $this->assertParentIsShallower($parent->org_unit_type_id, $orgUnitTypeId);
        }

        if ($this->orgUnitRepo->findSibling($orgUnitTypeId, $parentId, $name) !== null) {
            throw new BusinessException("Unit \"{$name}\" sudah ada pada level dan induk yang sama.", 422);
        }

        $id = $this->orgUnitRepo->insert([
            'org_unit_type_id' => $orgUnitTypeId,
            'parent_id'        => $parentId,
            'name'             => $name,
            'is_active'        => $isActive,
        ]);

        return ['id' => $id, 'name' => $name, 'org_unit_type_id' => $orgUnitTypeId, 'parent_id' => $parentId];
    }

    /**
     * Pindahkan unit ke induk lain (DFR-09). Ditolak kalau menciptakan
     * siklus -- termasuk kasus trivial memindahkan unit menjadi anak
     * dirinya sendiri.
     */
    public function reparent(int $id, ?int $newParentId): void
    {
        $unit = $this->orgUnitRepo->find($id);
        if ($unit === null) {
            throw new BusinessException('Unit organisasi tidak ditemukan.', 404);
        }

        if ($newParentId !== null) {
            if ($newParentId === $id) {
                throw new BusinessException('Unit tidak boleh menjadi induk dari dirinya sendiri.', 422);
            }

            $newParent = $this->orgUnitRepo->find($newParentId);
            if ($newParent === null) {
                throw new BusinessException('Unit induk baru tidak ditemukan.', 404);
            }

            $this->assertNoCycle($id, $newParentId);
            $this->assertParentIsShallower($newParent->org_unit_type_id, $unit->org_unit_type_id);
        }

        if ($this->orgUnitRepo->findSibling($unit->org_unit_type_id, $newParentId, $unit->name) !== null) {
            throw new BusinessException("Sudah ada unit bernama \"{$unit->name}\" di bawah induk baru tersebut.", 422);
        }

        $this->orgUnitRepo->update($id, ['parent_id' => $newParentId]);
    }

    /**
     * Ganti nama dan/atau status aktif unit (DFR-07, DFR-10).
     *
     * Karena programs/users menunjuk org_units lewat FK (org_unit_id),
     * penggantian nama otomatis "terlihat" oleh mereka -- tidak perlu
     * rambatan manual seperti JurusanService. SATU pengecualian: mode
     * politeknik (dual-mode DFR-25) masih memakai kolom teks lama
     * (`programs.jurusan`, `users.jurusan`) sebagai sumber kebenaran, dan
     * tabel `jurusans` adalah salinan nama level "Jurusan" hasil backfill
     * DFR-24. Kalau unit yang di-rename di sini persis nama sebuah baris
     * `jurusans`, rename itu dirambatkan lewat JurusanService supaya kedua
     * jalur (teks lama & org_units baru) tidak pecah kembar.
     */
    public function update(int $id, string $name, ?bool $isActive = null): array
    {
        $unit = $this->orgUnitRepo->find($id);
        if ($unit === null) {
            throw new BusinessException('Unit organisasi tidak ditemukan.', 404);
        }

        $name = trim($name);
        if ($name === '') {
            throw new BusinessException('Nama unit organisasi tidak boleh kosong.', 422);
        }

        $sibling = $this->orgUnitRepo->findSibling($unit->org_unit_type_id, $unit->parent_id !== null ? (int) $unit->parent_id : null, $name);
        if ($sibling !== null && (int) $sibling->id !== $id) {
            throw new BusinessException("Unit \"{$name}\" sudah ada pada level dan induk yang sama.", 422);
        }

        $jurusanAffected = ['programs' => 0, 'users' => 0];

        DB::connection(self::CONN)->transaction(function () use ($id, $unit, $name, $isActive, &$jurusanAffected) {
            $attributes = ['name' => $name];
            if ($isActive !== null) {
                $attributes['is_active'] = $isActive;
            }

            if ($unit->name !== $name) {
                $jurusan = $this->jurusanServiceFindByExactName($unit->name);
                if ($jurusan !== null) {
                    $renamed = $this->jurusanService->update((int) $jurusan->id, $name);
                    $jurusanAffected = $renamed['affected'];
                }
            }

            $this->orgUnitRepo->update($id, $attributes);
        });

        return [
            'id'               => $id,
            'name'             => $name,
            'org_unit_type_id' => (int) $unit->org_unit_type_id,
            'parent_id'        => $unit->parent_id !== null ? (int) $unit->parent_id : null,
            'jurusan_affected' => $jurusanAffected,
        ];
    }

    /**
     * Pohon unit organisasi lengkap (DFR-08), opsional dibatasi ke satu
     * template institusi. Dibangun in-memory dari daftar flat -- skala
     * puluhan baris, jadi tidak perlu recursive query.
     */
    public function tree(?string $institutionType = null): array
    {
        $units = $this->orgUnitRepo->all();

        if ($institutionType !== null) {
            $typeIds = collect($this->orgUnitTypeRepo->byInstitutionType($institutionType))->pluck('id')->map(fn ($v) => (int) $v)->all();
            $units = $units->filter(fn ($u) => in_array((int) $u->org_unit_type_id, $typeIds, true))->values();
        }

        $resolvedType = $institutionType ?? $this->inferInstitutionType($units) ?? config('institution.structure_template', 'politeknik');
        $typesById = collect($this->orgUnitTypeRepo->byInstitutionType($resolvedType))->keyBy('id');

        $byParent = $units->groupBy(fn ($u) => $u->parent_id === null ? 'root' : (int) $u->parent_id);

        $build = function ($parentKey) use (&$build, $byParent, $typesById) {
            return collect($byParent->get($parentKey, collect()))->map(function ($u) use (&$build, $typesById) {
                $type = $typesById->get((int) $u->org_unit_type_id);
                return [
                    'id'               => (int) $u->id,
                    'name'             => $u->name,
                    'org_unit_type_id' => (int) $u->org_unit_type_id,
                    'level_label'      => $type->label ?? null,
                    'level_index'      => $type !== null ? (int) $type->level_index : null,
                    'parent_id'        => $u->parent_id !== null ? (int) $u->parent_id : null,
                    'is_active'        => (bool) $u->is_active,
                    'children'         => $build((int) $u->id),
                ];
            })->values()->all();
        };

        return $build('root');
    }

    /** DFR-11: cari/filter unit berdasarkan nama dan/atau level. */
    public function search(?string $query, ?int $orgUnitTypeId = null): array
    {
        return $this->orgUnitRepo->search($query, $orgUnitTypeId)->map(fn ($row) => [
            'id'               => (int) $row->id,
            'name'             => $row->name,
            'org_unit_type_id' => (int) $row->org_unit_type_id,
            'level_label'      => $row->level_label,
            'level_index'      => (int) $row->level_index,
            'parent_id'        => $row->parent_id !== null ? (int) $row->parent_id : null,
            'is_active'        => (bool) $row->is_active,
        ])->all();
    }

    private function inferInstitutionType($units): ?string
    {
        $firstTypeId = $units->first()->org_unit_type_id ?? null;
        if ($firstTypeId === null) {
            return null;
        }

        $type = $this->orgUnitTypeRepo->find((int) $firstTypeId);

        return $type->institution_type ?? null;
    }

    /**
     * Nama jurusan cocok persis (case-sensitive) dengan nama unit -- sama
     * dengan cara OrgUnitBackfillSeeder (DFR-24) awalnya mencocokkan
     * keduanya. Bukan pencarian longgar: kalau tidak ada yang cocok
     * persis, unit ini bukan representasi org_units dari sebuah jurusan
     * (mis. levelnya bukan "Jurusan" politeknik) dan rename tidak perlu
     * dirambatkan ke tabel jurusans sama sekali.
     */
    private function jurusanServiceFindByExactName(string $name): ?object
    {
        return DB::connection(self::CONN)->table('jurusans')->where('name', $name)->first();
    }

    public function delete(int $id): void
    {
        $unit = $this->orgUnitRepo->find($id);
        if ($unit === null) {
            throw new BusinessException('Unit organisasi tidak ditemukan.', 404);
        }

        $childCount = $this->orgUnitRepo->childCount($id);
        if ($childCount > 0) {
            throw new BusinessException(
                "Unit \"{$unit->name}\" masih punya {$childCount} unit anak. Pindahkan/hapus unit anak dulu.",
                422,
            );
        }

        $this->orgUnitRepo->delete($id);
    }

    /**
     * Menelusuri rantai parent_id dari $newParentId ke atas. Kalau $unitId
     * ditemukan di rantai itu (atau sama dengan $newParentId), memindahkan
     * $unitId ke bawah $newParentId akan membuat siklus.
     */
    private function assertNoCycle(int $unitId, int $newParentId): void
    {
        $currentId = $newParentId;
        $guard     = 0;

        while ($currentId !== null) {
            if ($currentId === $unitId) {
                throw new BusinessException(
                    'Perpindahan ditolak: unit tidak boleh menjadi anak dari keturunannya sendiri (siklus).',
                    422,
                );
            }

            if (++$guard > self::MAX_DEPTH_GUARD) {
                // Idealnya tidak pernah tercapai (pohon puluhan baris) --
                // ini jaring pengaman kalau ada data korup yang sudah
                // bersiklus dari luar service ini.
                throw new BusinessException(
                    'Perpindahan ditolak: rantai induk terlalu dalam atau data pohon tidak konsisten.',
                    422,
                );
            }

            $parent    = $this->orgUnitRepo->find($currentId);
            $currentId = $parent?->parent_id;
        }
    }

    /**
     * Level induk harus punya level_index lebih kecil (lebih dangkal) dari
     * level anak pada template institusi yang sama.
     */
    private function assertParentIsShallower(int $parentOrgUnitTypeId, int $childOrgUnitTypeId): void
    {
        $parentType = $this->orgUnitTypeRepo->find($parentOrgUnitTypeId);
        $childType  = $this->orgUnitTypeRepo->find($childOrgUnitTypeId);

        if ($parentType === null || $childType === null) {
            throw new BusinessException('Level struktur organisasi tidak valid.', 422);
        }

        if ($parentType->institution_type !== $childType->institution_type) {
            throw new BusinessException('Unit induk dan unit anak harus berasal dari template institusi yang sama.', 422);
        }

        if ((int) $parentType->level_index >= (int) $childType->level_index) {
            throw new BusinessException(
                "Level \"{$parentType->label}\" tidak boleh menjadi induk dari level \"{$childType->label}\" (urutan level tidak valid).",
                422,
            );
        }
    }
}
