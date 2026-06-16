<?php

namespace App\Services\ETL;

use App\Repositories\ETL\OlapLoadRepository;
use App\Repositories\ETL\OltpExtractRepository;

/**
 * ETL untuk dim_indikator_evaluasi. SCD Type 1 (overwrite).
 * Business key: kode_field = questionnaire_questions.code (dikonfirmasi).
 *
 * Sumber: SEMUA pertanyaan question_type IN (boolean, number) yang
 * relevan sebagai indikator evaluasi:
 *   - boolean  + metadata.group_code      -> jenis_skala = 'multi_select'
 *               (group_code dipakai sebagai kategori_pertanyaan)
 *   - number   + metadata.scale_min/max   -> jenis_skala = 'range'
 *               (metadata.competency atau metadata.method dipakai
 *               sebagai dasar kategori_pertanyaan, lihat deriveKategori())
 *
 * Contoh konkret dari data nyata (dim_indikator_evaluasi.csv):
 *   f1761 -> kategori 'Kompetensi_A' (dimension=saat_lulus), jenis range
 *   f1762 -> kategori 'Kompetensi_B' (dimension=saat_ini),  jenis range
 *   f21   -> kategori 'MetodePembelajaran', jenis range
 *   f1601 -> kategori 'AlasanKerjaTdkSesuai' (dari group_code), jenis multi_select
 */
class IndikatorEvaluasiDimService
{
    public function __construct(
        private readonly OltpExtractRepository $oltpRepo,
        private readonly OlapLoadRepository $olapRepo,
    ) {}

    /**
     * @return array{processed:int, inserted:int, updated:int}
     */
    public function sync(): array
    {
        $candidates = $this->oltpRepo->getAllIndikatorEvaluasiCandidates();

        $processed = 0;
        $inserted = 0;
        $updated = 0;

        foreach ($candidates as $row) {
            $metadata = $row->metadata !== null ? json_decode($row->metadata, true) : null;

            $resolved = match ($row->question_type) {
                'boolean' => $this->resolveBooleanIndicator($row, $metadata),
                'number'  => $this->resolveNumberIndicator($row, $metadata),
                default   => null,
            };

            if ($resolved === null) {
                // Pertanyaan boolean/number tapi TIDAK punya metadata
                // group_code atau scale_min/max -> bukan indikator evaluasi
                // (misal pertanyaan boolean biasa di luar grup multi-select).
                continue;
            }

            $processed++;

            $existingId = $this->olapRepo->getIndikatorEvaluasiId($row->question_code);

            $this->olapRepo->upsertIndikatorEvaluasi(
                $row->question_code,
                $resolved['label_pertanyaan'],
                $resolved['kategori_pertanyaan'],
                $resolved['jenis_skala'],
            );

            $existingId === null ? $inserted++ : $updated++;
        }

        return ['processed' => $processed, 'inserted' => $inserted, 'updated' => $updated];
    }

    private function resolveBooleanIndicator(object $row, ?array $metadata): ?array
    {
        if ($metadata === null || !isset($metadata['group_code'])) {
            return null;
        }

        return [
            // group_label lebih ringkas untuk ditampilkan sebagai label_pertanyaan
            // dibanding question_text penuh yang berulang teks induknya.
            'label_pertanyaan'    => $metadata['group_label'] ?? $row->question_text,
            'kategori_pertanyaan' => $this->deriveKategoriFromGroupCode($metadata['group_code']),
            'jenis_skala'         => 'multi_select',
        ];
    }

    private function resolveNumberIndicator(object $row, ?array $metadata): ?array
    {
        if ($metadata === null || !isset($metadata['scale_min'], $metadata['scale_max'])) {
            return null;
        }

        // Dua pola kategori berbeda ditemukan di data nyata:
        //   - Kompetensi: ada 'competency' + 'dimension' (saat_lulus=A, saat_ini=B)
        //   - MetodePembelajaran: ada 'method', tanpa dimension
        if (isset($metadata['competency'], $metadata['dimension'])) {
            $suffix = $metadata['dimension'] === 'saat_lulus' ? 'A' : 'B';
            return [
                'label_pertanyaan'    => $metadata['competency'] . ' — ' . ($metadata['dimension'] === 'saat_lulus' ? 'dikuasai saat lulus' : 'dibutuhkan di pekerjaan'),
                'kategori_pertanyaan' => "Kompetensi_{$suffix}",
                'jenis_skala'         => 'range',
            ];
        }

        if (isset($metadata['method'])) {
            return [
                'label_pertanyaan'    => $metadata['method'],
                'kategori_pertanyaan' => 'MetodePembelajaran',
                'jenis_skala'         => 'range',
            ];
        }

        // Pola number+scale lain yang belum dikenal -> tetap dimasukkan
        // dengan kategori generik, supaya tidak hilang dari dim, tapi
        // beri label jelas bahwa ini butuh pemetaan kategori baru.
        return [
            'label_pertanyaan'    => $row->question_text,
            'kategori_pertanyaan' => 'Lainnya_Range',
            'jenis_skala'         => 'range',
        ];
    }

    /**
     * group_code contoh: "q21_alasan_tidak_sesuai" -> kategori
     * "AlasanKerjaTdkSesuai" (PascalCase, sesuai konvensi yang dipakai
     * di dim_indikator_evaluasi.csv). Mapping eksplisit untuk group_code
     * yang sudah dikenal; group_code baru di masa depan akan jatuh ke
     * fallback yang tetap konsisten secara penamaan.
     */
    private function deriveKategoriFromGroupCode(string $groupCode): string
    {
        return match ($groupCode) {
            'q21_alasan_tidak_sesuai' => 'AlasanKerjaTdkSesuai',
            default => str(ucwords(str_replace(['q', '_'], ['', ' '], preg_replace('/^q\d+_/', '', $groupCode))))
                ->replace(' ', '')
                ->toString(),
        };
    }
}