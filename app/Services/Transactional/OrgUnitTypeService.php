<?php
// app/Services/Transactional/OrgUnitTypeService.php
namespace App\Services\Transactional;

use App\Exceptions\BusinessException;
use App\Repositories\Config\AppSettingRepository;
use App\Repositories\Transactional\OrgUnitRepository;
use App\Repositories\Transactional\OrgUnitTypeRepository;
use Illuminate\Support\Facades\DB;

/**
 * OrgUnitTypeService — satu-satunya jalur tulis untuk katalog level
 * hierarki (`org_unit_types`).
 *
 * Tugas intinya guard DFR-05: begitu sebuah level dipakai satu atau lebih
 * `org_units`, urutan (level_index)/status wajib/penghapusan level itu
 * TIDAK BOLEH berubah lewat jalur biasa -- itu tugas wizard migrasi
 * struktur (DFR-06, Fase 4), bukan CRUD template biasa. Rename label saja
 * (teks yang ditampilkan, bukan urutan) tetap diperbolehkan kapan pun
 * karena tidak mengubah bentuk pohon yang sudah ada.
 */
class OrgUnitTypeService
{
    private const CONN = 'oltp';

    /** DFR-01/DFR-25: key app_settings yang menyimpan template aktif. */
    private const SETTING_KEY = 'institution_structure_template';

    /** DFR-04: batas maksimal level pada template custom. */
    private const CUSTOM_MAX_LEVELS = 5;

    private const VALID_INSTITUTION_TYPES = ['politeknik', 'universitas', 'institut', 'custom'];

    public function __construct(
        private readonly OrgUnitTypeRepository $repo,
        // Parameter tambahan (DFR-01/04/06) diberi default supaya konstruksi
        // manual yang sudah ada di Fase 1/3 (`new OrgUnitTypeService(new
        // OrgUnitTypeRepository())`) tetap kompil tanpa perlu diubah.
        private readonly OrgUnitRepository $orgUnitRepo = new OrgUnitRepository(),
        private readonly AppSettingRepository $appSettingRepo = new AppSettingRepository(),
    ) {}

    /** Template institusi yang sedang aktif (DFR-01), fallback ke config kalau app_settings kosong. */
    public function activeInstitutionType(): string
    {
        return $this->appSettingRepo->get(self::SETTING_KEY) ?? config('institution.structure_template', 'politeknik');
    }

    /**
     * Pilih template institusi saat instalasi awal (DFR-01).
     *
     * Ditolak begitu ada org_units apa pun -- setelah itu perubahan bentuk
     * pohon adalah tugas wizard migrasi struktur (DFR-06), bukan pilihan
     * template ulang yang mengganti seluruh dasar hierarki.
     */
    public function selectTemplate(string $institutionType): array
    {
        if (! in_array($institutionType, self::VALID_INSTITUTION_TYPES, true)) {
            throw new BusinessException(
                'Jenis institusi tidak valid. Pilih salah satu: ' . implode(', ', self::VALID_INSTITUTION_TYPES) . '.',
                422,
            );
        }

        if ($this->orgUnitRepo->countAll() > 0) {
            throw new BusinessException(
                'Template hanya bisa dipilih sebelum ada unit organisasi apa pun. '
                . 'Struktur sudah punya data -- gunakan wizard migrasi struktur untuk mengubahnya.',
                422,
            );
        }

        $levels = $this->repo->byInstitutionType($institutionType);
        if ($levels->isEmpty()) {
            throw new BusinessException(
                $institutionType === 'custom'
                    ? 'Template custom belum didefinisikan. Definisikan level-nya dulu sebelum memilihnya (DFR-04).'
                    : "Template \"{$institutionType}\" belum punya level yang terdefinisi.",
                422,
            );
        }

        $this->appSettingRepo->set(self::SETTING_KEY, $institutionType);

        return $this->listByInstitutionType($institutionType);
    }

    /**
     * Definisikan template custom (DFR-04): nama level bebas, maksimal 5
     * level, minimal 1 level "unit akademik" (level teratas) + 1 level
     * "program studi" (level dasar/terdalam -- baris terakhir array).
     *
     * @param array<int,array{label:string,is_required?:bool}> $levels terurut dari level teratas ke dasar
     */
    public function defineCustomTemplate(array $levels): array
    {
        $levels = array_values($levels);

        if (count($levels) < 2) {
            throw new BusinessException(
                'Template custom butuh minimal 2 level: 1 unit akademik + 1 program studi di dasar pohon.',
                422,
            );
        }

        if (count($levels) > self::CUSTOM_MAX_LEVELS) {
            throw new BusinessException(
                'Template custom dibatasi maksimal ' . self::CUSTOM_MAX_LEVELS . ' level.',
                422,
            );
        }

        $labels = [];
        foreach ($levels as $i => $level) {
            $label = trim((string) ($level['label'] ?? ''));
            if ($label === '') {
                throw new BusinessException('Nama level ke-' . ($i + 1) . ' tidak boleh kosong.', 422);
            }
            $labels[] = $label;
        }

        if (count($labels) !== count(array_unique($labels))) {
            throw new BusinessException('Nama level pada template custom tidak boleh duplikat.', 422);
        }

        // Custom sudah dipakai org_units -- guard DFR-05: definisi ulang
        // akan menghapus baris org_unit_types lama, dan menghapus level
        // yang masih dipakai berarti org_units yatim.
        $existing = $this->repo->byInstitutionType('custom');
        foreach ($existing as $existingLevel) {
            $usage = $this->repo->orgUnitCount((int) $existingLevel->id);
            if ($usage > 0) {
                throw new BusinessException(
                    "Template custom sudah dipakai {$usage} unit organisasi pada level \"{$existingLevel->label}\". "
                    . 'Gunakan wizard migrasi struktur untuk mengubahnya, bukan definisi ulang.',
                    422,
                );
            }
        }

        DB::connection(self::CONN)->transaction(function () use ($levels) {
            $this->repo->deleteByInstitutionType('custom');

            $now = now();
            foreach ($levels as $i => $level) {
                $this->repo->insert([
                    'institution_type' => 'custom',
                    'level_index'      => $i + 1,
                    'label'            => trim((string) $level['label']),
                    'is_required'      => (bool) ($level['is_required'] ?? true),
                ]);
            }
        });

        return $this->listByInstitutionType('custom');
    }

    /**
     * Sisip level baru di posisi $atLevelIndex pada wizard migrasi
     * struktur (DFR-06). Level yang sudah ada di posisi itu dan seterusnya
     * digeser turun satu -- org_units existing TIDAK ikut berubah
     * (parent_id-nya tetap sama, hanya level_index level yang dipakai
     * generasi org_units berikutnya yang bertambah dalam), jadi tidak ada
     * data yang hilang.
     */
    public function insertLevel(string $institutionType, int $atLevelIndex, string $label, bool $isRequired = true): array
    {
        $label = trim($label);
        if ($label === '') {
            throw new BusinessException('Nama level tidak boleh kosong.', 422);
        }

        $maxLevel = $this->repo->maxLevelIndex($institutionType);
        if ($atLevelIndex < 1 || $atLevelIndex > $maxLevel + 1) {
            throw new BusinessException(
                "Posisi level tidak valid. Template \"{$institutionType}\" punya {$maxLevel} level saat ini.",
                422,
            );
        }

        if ($maxLevel + 1 > self::CUSTOM_MAX_LEVELS) {
            throw new BusinessException('Template dibatasi maksimal ' . self::CUSTOM_MAX_LEVELS . ' level.', 422);
        }

        DB::connection(self::CONN)->transaction(function () use ($institutionType, $atLevelIndex, $label, $isRequired) {
            $this->repo->shiftLevelIndexes($institutionType, $atLevelIndex, 1);

            $this->repo->insert([
                'institution_type' => $institutionType,
                'level_index'      => $atLevelIndex,
                'label'            => $label,
                'is_required'      => $isRequired,
            ]);
        });

        return $this->listByInstitutionType($institutionType);
    }

    /**
     * Hapus level di tengah pohon (DFR-06). Berbeda dari delete() biasa:
     * kalau level ini masih dipakai org_units, unit-unit itu SENDIRI ikut
     * terhapus (levelnya lenyap, tidak mungkin unit tetap ada tanpa
     * level), tapi seluruh KETURUNANNYA "naik" jadi anak dari induk level
     * yang dihapus -- tidak ada unit anak yang hilang, hanya satu tingkat
     * kedalaman yang lenyap dari rantainya.
     */
    public function removeLevel(int $id): array
    {
        $type = $this->repo->find($id);
        if ($type === null) {
            throw new BusinessException('Level struktur organisasi tidak ditemukan.', 404);
        }

        if ($this->repo->byInstitutionType($type->institution_type)->count() <= 1) {
            throw new BusinessException(
                'Tidak bisa menghapus satu-satunya level yang tersisa pada template ini.',
                422,
            );
        }

        $result = [];

        DB::connection(self::CONN)->transaction(function () use ($type, &$result) {
            $units = $this->orgUnitRepo->unitsByTypeId((int) $type->id);

            foreach ($units as $unit) {
                // Anak-anak unit yang levelnya dihapus naik ke induk unit
                // itu sendiri (bisa null = jadi root), lalu unit ini baru
                // aman dihapus (childCount-nya sudah nol).
                $this->orgUnitRepo->reparentChildrenTo((int) $unit->id, $unit->parent_id !== null ? (int) $unit->parent_id : null);
                $this->orgUnitRepo->delete((int) $unit->id);
            }

            $this->repo->delete((int) $type->id);
            $this->repo->shiftLevelIndexes($type->institution_type, (int) $type->level_index + 1, -1);

            $result = [
                'removed_level'   => $type->label,
                'units_removed'   => $units->count(),
                'remaining_levels' => $this->listByInstitutionType($type->institution_type),
            ];
        });

        return $result;
    }

    /** Daftar level aktif satu template, terurut (DFR-03). */
    public function listByInstitutionType(string $institutionType): array
    {
        return $this->repo->byInstitutionType($institutionType)->map(fn ($row) => [
            'id'               => (int) $row->id,
            'institution_type' => $row->institution_type,
            'level_index'      => (int) $row->level_index,
            'label'            => $row->label,
            'is_required'      => (bool) $row->is_required,
        ])->all();
    }

    /**
     * Ganti label (nama tampilan) level. Aman kapan pun -- tidak mengubah
     * urutan/jumlah level, jadi tidak butuh guard DFR-05.
     */
    public function renameLabel(int $id, string $label): void
    {
        $type = $this->repo->find($id);
        if ($type === null) {
            throw new BusinessException('Level struktur organisasi tidak ditemukan.', 404);
        }

        $this->repo->update($id, ['label' => trim($label)]);
    }

    /**
     * Ubah urutan (level_index) dan/atau status wajib sebuah level.
     *
     * DFR-05: ditolak begitu ada org_units yang memakai level ini, supaya
     * pohon yang sudah dibangun admin tidak tiba-tiba berubah bentuk tanpa
     * lewat wizard migrasi struktur (DFR-06).
     */
    public function changeStructure(int $id, ?int $levelIndex = null, ?bool $isRequired = null): void
    {
        $type = $this->repo->find($id);
        if ($type === null) {
            throw new BusinessException('Level struktur organisasi tidak ditemukan.', 404);
        }

        $this->assertMutable($type, [
            'level_index' => $levelIndex,
            'is_required' => $isRequired,
        ]);

        $attributes = array_filter([
            'level_index' => $levelIndex,
            'is_required' => $isRequired,
        ], fn ($v) => $v !== null);

        if ($attributes === []) {
            return;
        }

        $this->repo->update($id, $attributes);
    }

    /**
     * Hapus level. Selalu ditolak kalau masih dipakai org_units apa pun --
     * menghapus level yang dipakai berarti org_units yatim ke FK yang
     * sudah tidak ada.
     */
    public function delete(int $id): void
    {
        $type = $this->repo->find($id);
        if ($type === null) {
            throw new BusinessException('Level struktur organisasi tidak ditemukan.', 404);
        }

        $usage = $this->repo->orgUnitCount($id);
        if ($usage > 0) {
            throw new BusinessException(
                "Level \"{$type->label}\" masih dipakai {$usage} unit organisasi. "
                . 'Hapus/pindahkan unit tersebut dulu, atau gunakan wizard migrasi struktur.',
                422,
            );
        }

        $this->repo->delete($id);
    }

    /**
     * @param array{level_index:?int,is_required:?bool} $incoming
     */
    private function assertMutable(object $type, array $incoming): void
    {
        $changesStructure =
            ($incoming['level_index'] !== null && (int) $incoming['level_index'] !== (int) $type->level_index)
            || ($incoming['is_required'] !== null && (bool) $incoming['is_required'] !== (bool) $type->is_required);

        if (! $changesStructure) {
            return;
        }

        $usage = $this->repo->orgUnitCount($type->id);
        if ($usage > 0) {
            throw new BusinessException(
                "Urutan/status level \"{$type->label}\" tidak bisa diubah karena sudah dipakai {$usage} unit organisasi. "
                . 'Gunakan wizard migrasi struktur untuk menyisip/menghapus level tanpa kehilangan data (DFR-06).',
                422,
            );
        }
    }
}
