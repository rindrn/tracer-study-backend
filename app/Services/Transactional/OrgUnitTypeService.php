<?php
// app/Services/Transactional/OrgUnitTypeService.php
namespace App\Services\Transactional;

use App\Exceptions\BusinessException;
use App\Repositories\Transactional\OrgUnitTypeRepository;

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
    public function __construct(
        private readonly OrgUnitTypeRepository $repo,
    ) {}

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
