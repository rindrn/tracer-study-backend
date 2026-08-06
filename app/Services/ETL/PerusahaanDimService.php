<?php

namespace App\Services\ETL;

use App\Repositories\ETL\OlapLoadRepository;

/**
 * ETL untuk dim_perusahaan. SCD Type 2 (valid_from, valid_to,
 * flag_perusahaan ada di schema).
 *
 * OLTP TIDAK punya tabel "companies" tersendiri -- nama perusahaan,
 * jenis perusahaan, kota dst adalah JAWABAN alumni (question_code
 * mengikuti pertanyaan terkait pekerjaan, misal f1101 jenis perusahaan).
 * Business key id_perusahaan di-derive dari kombinasi jawaban tersebut.
 */
class PerusahaanDimService
{
    public function __construct(
        private readonly OlapLoadRepository $olapRepo,
    ) {}

    /**
     * @param array $resolvedAnswers keys: 'nama_perusahaan', 'jenis_perusahaan',
     *              'kota', 'provinsi', 'tingkat_instansi'
     * @return int|null perusahaan_sk, null jika alumni tidak bekerja
     */
    public function syncAndResolveSk(array $resolvedAnswers, \DateTimeInterface $snapshotDate): ?int
    {
        $namaPerusahaan = $resolvedAnswers['nama_perusahaan'] ?? null;

        if ($namaPerusahaan === null) {
            return null;
        }

        $idPerusahaan = $this->deriveBusinessKey($namaPerusahaan, $resolvedAnswers['kota'] ?? '');

        $newAttributes = [
            'id_perusahaan'           => $idPerusahaan,
            'company_name'            => $namaPerusahaan,
            'label_jenis_perusahaan'  => $resolvedAnswers['jenis_perusahaan'] ?? null,
            'label_tingkat_instansi'  => $resolvedAnswers['tingkat_instansi'] ?? null,
            'nama_kota'               => $resolvedAnswers['kota'] ?? null,
            'nama_provinsi'           => $resolvedAnswers['provinsi'] ?? null,
        ];

        $active = $this->olapRepo->getActivePerusahaanVersion($idPerusahaan);

        if ($active === null) {
            return $this->olapRepo->insertNewPerusahaanVersion($newAttributes, $snapshotDate);
        }

        // Perbandingan longgar (bukan !==) — lihat WirausahaDimService untuk
        // alasan lengkap: kolom DB selalu string lewat PDO sedangkan nilai
        // baru bisa int/null, jadi !== salah mendeteksi "berubah" padahal
        // nilainya sama dan memicu versi SCD duplikat terus-menerus.
        $hasChanged = self::normalize($active->label_jenis_perusahaan) !== self::normalize($newAttributes['label_jenis_perusahaan'])
            || self::normalize($active->label_tingkat_instansi) !== self::normalize($newAttributes['label_tingkat_instansi'])
            || self::normalize($active->nama_kota) !== self::normalize($newAttributes['nama_kota'])
            || self::normalize($active->nama_provinsi) !== self::normalize($newAttributes['nama_provinsi']);

        if ($hasChanged) {
            $this->olapRepo->closePerusahaanVersion($active->perusahaan_sk, $snapshotDate);
            return $this->olapRepo->insertNewPerusahaanVersion($newAttributes, $snapshotDate);
        }

        return $active->perusahaan_sk;
    }

    private function deriveBusinessKey(string $companyName, string $city): string
    {
        return mb_strtolower(trim($companyName))
            . '|'
            . mb_strtolower(trim($city));
    }

    /** Null dan string kosong dianggap setara; selain itu dibandingkan sebagai string. */
    private static function normalize(mixed $value): string
    {
        return $value === null ? '' : (string) $value;
    }
}