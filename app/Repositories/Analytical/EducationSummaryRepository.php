<?php

namespace App\Repositories\Analytical;

use Illuminate\Support\Collection;

class EducationSummaryRepository extends BaseAnalyticalRepository
{
    // ──────────────────────────────────────────────────────────────
    //  1. SKOR KOMPETENSI + GAP TERBESAR (source: KPI 9 — FactRangeEvaluasi)
    // ──────────────────────────────────────────────────────────────

    /**
     *
     * @return Collection<array{kode_field, label, kategori, avg_skor, count}>
     */
    public function getKompetensiData(
        ?string $jenjang        = null,
        ?string $jurusan        = null,
        ?string $namaProdi      = null,
        ?string $tahunLulus     = null,
        ?string $mingguSnapshot = null,
    ): Collection {
        $filters = array_merge(
            $this->buildGlobalFilters(
                jenjang:        $jenjang,
                jurusan:        $jurusan,
                namaProdi:      $namaProdi,
                tahunLulus:     $tahunLulus,
                mingguSnapshot: $mingguSnapshot,
            ),
            [[
                'member'   => 'DimIndikatorEvaluasi.kategori_pertanyaan',
                'operator' => 'in',
                'values'   => ['Kompetensi_A', 'Kompetensi_B'],
            ]],
        );

        return $this->cube->load([
            'measures'   => [
                'FactRangeEvaluasi.avg_skor',
                'FactRangeEvaluasi.count',
            ],
            'dimensions' => [
                'DimIndikatorEvaluasi.label_pertanyaan',
                'DimIndikatorEvaluasi.kode_field',
                'DimIndikatorEvaluasi.kategori_pertanyaan',
            ],
            'filters' => $filters,
            'order'   => [['DimIndikatorEvaluasi.kode_field', 'asc']],
        ])->map(fn($r) => [
            'kode_field' => $r['DimIndikatorEvaluasi.kode_field']           ?? '',
            'label'      => $r['DimIndikatorEvaluasi.label_pertanyaan']     ?? '',
            'kategori'   => $r['DimIndikatorEvaluasi.kategori_pertanyaan']  ?? '',
            'avg_skor'   => (float) ($r['FactRangeEvaluasi.avg_skor']       ?? 0),
            'count'      => (int)   ($r['FactRangeEvaluasi.count']          ?? 0),
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    //  2. METODE TERBAIK + AVG PERSEPSI (source: KPI 10 — FactRangeEvaluasi)
    // ──────────────────────────────────────────────────────────────

    /**
     *
     * @return Collection<array{kode_field, label, avg_skor, count}>
     */
    public function getMetodeData(
        ?string $jenjang        = null,
        ?string $jurusan        = null,
        ?string $namaProdi      = null,
        ?string $tahunLulus     = null,
        ?string $mingguSnapshot = null,
    ): Collection {
        $filters = array_merge(
            $this->buildGlobalFilters(
                jenjang:        $jenjang,
                jurusan:        $jurusan,
                namaProdi:      $namaProdi,
                tahunLulus:     $tahunLulus,
                mingguSnapshot: $mingguSnapshot,
            ),
            [[
                'member'   => 'DimIndikatorEvaluasi.kategori_pertanyaan',
                'operator' => 'equals',
                'values'   => ['MetodePembelajaran'],
            ]],
        );

        return $this->cube->load([
            'measures'   => [
                'FactRangeEvaluasi.avg_skor',
                'FactRangeEvaluasi.count',
            ],
            'dimensions' => [
                'DimIndikatorEvaluasi.label_pertanyaan',
                'DimIndikatorEvaluasi.kode_field',
            ],
            'filters' => $filters,
            'order'   => [['DimIndikatorEvaluasi.kode_field', 'asc']],
        ])->map(fn($r) => [
            'kode_field' => $r['DimIndikatorEvaluasi.kode_field']       ?? '',
            'label'      => $r['DimIndikatorEvaluasi.label_pertanyaan'] ?? '',
            'avg_skor'   => (float) ($r['FactRangeEvaluasi.avg_skor']   ?? 0),
            'count'      => (int)   ($r['FactRangeEvaluasi.count']      ?? 0),
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    //  3. MANDIRI/KELUARGA + BEASISWA (source: KPI 11 — FactTracerStudy)
    // ──────────────────────────────────────────────────────────────

    /**
     *
     * @return Collection<array{sumber_biaya, count}>
     */
    public function getPembiayaanData(
        ?string $jenjang        = null,
        ?string $jurusan        = null,
        ?string $namaProdi      = null,
        ?string $tahunLulus     = null,
        ?string $mingguSnapshot = null,
    ): Collection {
        $filters = $this->buildGlobalFilters(
            jenjang:        $jenjang,
            jurusan:        $jurusan,
            namaProdi:      $namaProdi,
            tahunLulus:     $tahunLulus,
            mingguSnapshot: $mingguSnapshot,
        );

        return $this->cube->load([
            'measures'   => ['FactTracerStudy.count_alumni'],
            'dimensions' => ['DimAlumni.label_sumber_biaya_dipolban'],
            'filters'    => $filters,
            'order'      => [['FactTracerStudy.count_alumni', 'desc']],
        ])->map(fn($r) => [
            'sumber_biaya' => $r['DimAlumni.label_sumber_biaya_dipolban'] ?? '',
            'count'        => (int) ($r['FactTracerStudy.count_alumni']   ?? 0),
        ]);
    }
}