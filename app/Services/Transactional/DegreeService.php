<?php
// app/Services/Transactional/DegreeService.php
namespace App\Services\Transactional;

use App\Exceptions\BusinessException;
use App\Repositories\Transactional\DegreeRepository;
use App\Support\Degree;

/**
 * DegreeService — satu-satunya jalur tulis untuk master jenjang.
 *
 * Seluruh aturan yang menjaga gudang data tinggal di sini, bukan di validasi
 * request, karena keputusannya bergantung pada keadaan baris (bawaan atau
 * bukan, dipakai prodi atau tidak) dan bukan pada bentuk masukan.
 */
class DegreeService
{
    public function __construct(
        private readonly DegreeRepository $repo,
    ) {}

    public function list(): array
    {
        return $this->repo->allWithUsage()->map(fn ($row) => [
            'id'            => (int) $row->id,
            'code'          => $row->code,
            'label'         => $row->label,
            'sort_order'    => (int) $row->sort_order,
            'is_seeded'     => (bool) $row->is_seeded,
            'is_active'     => (bool) $row->is_active,
            'program_count' => (int) $row->program_count,
        ])->all();
    }

    public function create(array $input): array
    {
        $code  = trim($input['code']);
        $label = trim($input['label'] ?? '') ?: $code;

        if ($this->repo->findByCode($code) !== null) {
            throw new BusinessException("Jenjang dengan kode \"{$code}\" sudah ada.", 422);
        }

        $id = $this->repo->insert([
            'code'       => $code,
            'label'      => $label,
            'sort_order' => $input['sort_order'] ?? $this->repo->nextSortOrder(),
            'is_seeded'  => false,
            'is_active'  => $input['is_active'] ?? true,
        ]);

        Degree::flushCache();

        return ['id' => $id, 'code' => $code, 'label' => $label];
    }

    /**
     * Ubah jenjang.
     *
     * Kode hanya boleh diganti selama baris ini bukan bawaan DAN belum dipakai
     * prodi mana pun. Alasannya bukan kerapian: kode mengalir ke
     * `dim_prodi.jenjang`, dan `ProdiDimService` ikut membandingkan kolom itu
     * saat menentukan versi SCD — mengganti kode yang sudah dipakai akan
     * menutup versi setiap prodi berjenjang itu lalu membuka versi baru,
     * sehingga dasbor historis terbelah jadi sebelum dan sesudah tanpa galat.
     *
     * Label dan urutan bebas diubah kapan saja; keduanya tidak pernah sampai ke
     * gudang data.
     */
    public function update(int $id, array $input): array
    {
        $degree = $this->repo->find($id);
        if ($degree === null) {
            throw new BusinessException('Jenjang tidak ditemukan.', 404);
        }

        $attributes = [];

        if (array_key_exists('code', $input)) {
            $code = trim($input['code']);

            if ($code !== $degree->code) {
                if ($degree->is_seeded) {
                    throw new BusinessException(
                        "Kode jenjang bawaan tidak boleh diubah karena nilainya sudah tersimpan di gudang data. Ubah labelnya saja bila yang ingin diganti hanya tampilan.",
                        422,
                    );
                }

                $used = $this->repo->programCount($degree->code);
                if ($used > 0) {
                    throw new BusinessException(
                        "Kode jenjang \"{$degree->code}\" sudah dipakai {$used} program studi dan nilainya sudah masuk gudang data, jadi tidak bisa diubah. Ubah labelnya saja.",
                        422,
                    );
                }

                if ($this->repo->findByCode($code) !== null) {
                    throw new BusinessException("Jenjang dengan kode \"{$code}\" sudah ada.", 422);
                }

                $attributes['code'] = $code;
            }
        }

        foreach (['label', 'sort_order', 'is_active'] as $field) {
            if (array_key_exists($field, $input) && $input[$field] !== null) {
                $attributes[$field] = is_string($input[$field]) ? trim($input[$field]) : $input[$field];
            }
        }

        if ($attributes !== []) {
            $this->repo->update($id, $attributes);
            Degree::flushCache();
        }

        return ['id' => $id] + $attributes;
    }

    /**
     * Hapus jenjang.
     *
     * Ditolak untuk baris bawaan dan untuk jenjang yang masih dipakai prodi.
     * Kunci asing `programs_degree_foreign` sebetulnya sudah menahan yang
     * kedua, tetapi ditolak lebih dahulu di sini supaya pesannya menyebut
     * jumlah pemakainya, bukan galat basis data mentah.
     */
    public function delete(int $id): void
    {
        $degree = $this->repo->find($id);
        if ($degree === null) {
            throw new BusinessException('Jenjang tidak ditemukan.', 404);
        }

        if ($degree->is_seeded) {
            throw new BusinessException(
                "Jenjang bawaan tidak boleh dihapus. Nonaktifkan saja bila kampus ini tidak memakainya — prodi lama yang terlanjur memakainya tetap utuh.",
                422,
            );
        }

        $used = $this->repo->programCount($degree->code);
        if ($used > 0) {
            throw new BusinessException(
                "Jenjang \"{$degree->code}\" masih dipakai {$used} program studi. Pindahkan dulu prodinya sebelum menghapus.",
                422,
            );
        }

        $this->repo->delete($id);

        Degree::flushCache();
    }
}
