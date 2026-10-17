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
    /**
     * Kanonikalisasi label_tingkat_instansi case-insensitive. Jawaban
     * mentah untuk role ini seharusnya selalu salah satu dari 3 opsi
     * dropdown resmi, tapi data seed/demo (init.sql) ternyata punya
     * campuran kapitalisasi untuk kalimat yang sama persis (mis.
     * "Multinasional/Internasional" vs "Multinasional/internasional") --
     * tanpa normalisasi ini, dim_perusahaan mencatatnya sebagai DUA
     * kategori berbeda dan chart Sebaran Level Perusahaan menampilkan
     * bar dobel untuk kategori yang sama.
     */
    private const TINGKAT_INSTANSI_CANONICAL = [
        'lokal/wilayah/wiraswasta tidak berbadan hukum' => 'Lokal/Wilayah/Wiraswasta tidak berbadan hukum',
        'multinasional/internasional'                   => 'Multinasional/Internasional',
        'nasional/wiraswasta berbadan hukum'             => 'Nasional/Wiraswasta berbadan hukum',
    ];

    /**
     * Isian "Lainnya" yang sebenarnya opsi resmi dengan nama lama. Dashboard
     * KPI 12 sudah lama menghitung "Lembaga Pemerintah" sebagai Instansi
     * pemerintah; tanpa ini 83 alumni data asli pindah ke "Lainnya".
     */
    private const JENIS_ALIAS = [
        'lembaga pemerintah' => 'Instansi pemerintah',
    ];

    public function __construct(
        private readonly OlapLoadRepository $olapRepo,
    ) {}

    /**
     * @param array $resolvedAnswers keys: 'nama_perusahaan', 'jenis_perusahaan',
     *              'jenis_perusahaan_lainnya', 'kota', 'provinsi', 'tingkat_instansi'
     * @return int|null perusahaan_sk, null jika alumni tidak bekerja
     */
    public function syncAndResolveSk(array $resolvedAnswers, \DateTimeInterface $snapshotDate): ?int
    {
        $namaPerusahaan = $resolvedAnswers['nama_perusahaan'] ?? null;

        if ($namaPerusahaan === null) {
            return null;
        }

        $idPerusahaan = $this->deriveBusinessKey($namaPerusahaan, $resolvedAnswers['kota'] ?? '');

        [$jenis, $jenisLainnya] = $this->resolveJenisAlias(
            $resolvedAnswers['jenis_perusahaan'] ?? null,
            $resolvedAnswers['jenis_perusahaan_lainnya'] ?? null,
        );

        $newAttributes = [
            'id_perusahaan'           => $idPerusahaan,
            'company_name'            => $namaPerusahaan,
            'label_jenis_perusahaan'  => $jenis,
            'jenis_perusahaan_lainnya' => $jenisLainnya,
            'label_tingkat_instansi'  => $this->normalizeTingkatInstansi($resolvedAnswers['tingkat_instansi'] ?? null),
            'nama_kota'               => $resolvedAnswers['kota'] ?? null,
            'nama_provinsi'           => $resolvedAnswers['provinsi'] ?? null,
        ];

        $active = $this->olapRepo->getActivePerusahaanVersion($idPerusahaan);

        if ($active === null) {
            return $this->olapRepo->insertNewPerusahaanVersion($newAttributes, $snapshotDate);
        }

        $hasChanged = $active->label_jenis_perusahaan !== $newAttributes['label_jenis_perusahaan']
            || ($active->jenis_perusahaan_lainnya ?? null) !== $newAttributes['jenis_perusahaan_lainnya']
            || $active->label_tingkat_instansi !== $newAttributes['label_tingkat_instansi']
            || $active->nama_kota !== $newAttributes['nama_kota']
            || $active->nama_provinsi !== $newAttributes['nama_provinsi'];

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

    /** @return array{0: ?string, 1: ?string} [label jenis, isian Lainnya] */
    private function resolveJenisAlias(?string $jenis, ?string $lainnya): array
    {
        $alias = $lainnya !== null ? (self::JENIS_ALIAS[mb_strtolower(trim($lainnya))] ?? null) : null;

        return $alias !== null ? [$alias, null] : [$jenis, $lainnya];
    }

    /** Nilai yang tidak dikenali dibiarkan apa adanya (fail-visible, bukan fail-silent). */
    private function normalizeTingkatInstansi(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $key = mb_strtolower(trim($value));

        return self::TINGKAT_INSTANSI_CANONICAL[$key] ?? $value;
    }
}