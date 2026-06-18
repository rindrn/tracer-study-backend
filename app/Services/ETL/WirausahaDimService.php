<?php

namespace App\Services\ETL;

use App\Repositories\ETL\OlapLoadRepository;

/**
 * ETL untuk dim_wirausaha. SCD Type 2 (terkonfirmasi: valid_from,
 * valid_to, flag_wirausaha ada di schema).
 *
 * Sama seperti dim_perusahaan, OLTP TIDAK punya tabel wirausaha
 * tersendiri -- jabatan, lokasi usaha, dst adalah JAWABAN alumni yang
 * memilih f8='3' (Wiraswasta). Business key di-derive dari kombinasi
 * jawaban relevan milik satu alumni.
 *
 * Dipanggil inline dari AlumniFactBuilderService setelah jawaban
 * alumni di-pivot, sama seperti PerusahaanDimService.
 */
class WirausahaDimService
{
    public function __construct(
        private readonly OlapLoadRepository $olapRepo,
    ) {}

    /**
     * @param array $resolvedAnswers harus berisi 'jabatan', 'kota', 'provinsi',
     *              'tingkat_instansi' (sesuaikan question_code asli saat wiring)
     * @return int|null wirausaha_sk, null jika alumni bukan wiraswasta
     */
    public function syncAndResolveSk(array $resolvedAnswers, \DateTimeInterface $snapshotDate): ?int
    {
        $jabatan = $resolvedAnswers['jabatan'] ?? null;

        if ($jabatan === null) {
            return null; // alumni bukan wiraswasta / tidak mengisi data ini
        }

        $idWirausaha = $this->deriveBusinessKey($jabatan, $resolvedAnswers['kota'] ?? '');

        $newAttributes = [
            'id_wirausaha'           => $idWirausaha,
            'jabatan'                => $jabatan,
            'label_tingkat_instansi' => $resolvedAnswers['tingkat_instansi'] ?? null,
            'nama_provinsi'          => $resolvedAnswers['provinsi'] ?? null,
            'nama_kota'              => $resolvedAnswers['kota'] ?? null,
        ];

        $active = $this->olapRepo->getActiveWirausahaVersion($idWirausaha);

        if ($active === null) {
            return $this->olapRepo->insertNewWirausahaVersion($newAttributes, $snapshotDate);
        }

        $hasChanged = $active->label_tingkat_instansi !== $newAttributes['label_tingkat_instansi']
            || $active->nama_provinsi !== $newAttributes['nama_provinsi']
            || $active->nama_kota !== $newAttributes['nama_kota'];

        if ($hasChanged) {
            $this->olapRepo->closeWirausahaVersion($active->wirausaha_sk, $snapshotDate);
            return $this->olapRepo->insertNewWirausahaVersion($newAttributes, $snapshotDate);
        }

        return $active->wirausaha_sk;
    }

    private function deriveBusinessKey(string $jabatan, string $kota): string
    {
        return mb_strtolower(trim($jabatan))
            . '|'
            . mb_strtolower(trim($kota));
    }
}