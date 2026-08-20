<?php
// app/Services/Transactional/OrgUnitService.php
namespace App\Services\Transactional;

use App\Exceptions\BusinessException;
use App\Repositories\Transactional\OrgUnitRepository;
use App\Repositories\Transactional\OrgUnitTypeRepository;

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
