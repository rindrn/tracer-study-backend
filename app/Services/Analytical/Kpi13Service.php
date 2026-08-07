<?php

namespace App\Services\Analytical;

use App\DTOs\Analytical\Kpi13\Kpi13ChartDTO;
use App\Repositories\Analytical\Kpi13Repository;
use App\Services\Transactional\ThresholdService;
use Illuminate\Support\Collection;

/**
 * Kpi13Service — Perbandingan KPI Lintas Program Studi
 *
 * Alur:
 *  1. Fetch 6 query dari Cube.js via Kpi13Repository (5 metrik + pendapatan)
 *  2. Join result per id_prodi menggunakan Collection
 *  3. Hitung persentase dengan denominator yang tepat
 *  4. Resolve status threshold (baik/unggul/kurang) per metrik per prodi via ThresholdService
 *  5. Rakit Kpi13ChartDTO
 *
 * Tidak ada koneksi ke OLTP untuk data KPI — murni dari OLAP (Cube.js).
 * Threshold tetap dibaca dari OLTP (tabel thresholds) via ThresholdService,
 * sama seperti chart Kpi3/Kpi4-8 lainnya.
 *
 * DENOMINATOR:
 *  - keterserapan  = bekerja / total_alumni × 100
 *  - wirausaha     = wirausaha / total_alumni × 100
 *  - masa_tunggu   = cepat / bekerja × 100   (% dari yang bekerja, lebih valid)
 *  - kesesuaian    = sesuai / bekerja × 100  (% dari yang bekerja)
 *  - pendapatan    = rata-rata take-home pay (bukan %, satuan rupiah)
 *
 * INDICATOR KEY (tabel threshold_indicators, dipakai untuk resolve threshold):
 *  - keterserapan → graduate_absorption
 *  - masa_tunggu  → employment_time
 *  - kesesuaian   → field_relevance
 *  - wirausaha    → entrepreneurship
 *  - pendapatan   → salary_above_ump
 *
 * Taruh di: app/Services/Analytical/Kpi13Service.php
 */
class Kpi13Service
{
    private const METRIC_INDICATOR_KEY = [
        'keterserapan' => 'graduate_absorption',
        'masa_tunggu'  => 'employment_time',
        'kesesuaian'   => 'field_relevance',
        'wirausaha'    => 'entrepreneurship',
        'pendapatan'   => 'salary_above_ump',
    ];

    // Ambang default % ≥ x UMP dipakai untuk metrik pendapatan lintas-prodi —
    // sama dengan default di PendapatanService, dipakai seragam demi
    // perbandingan yang adil antar prodi (lihat komentar di Kpi13Repository).
    private const AMBANG_UMP_DEFAULT = 1.2;

    public function __construct(
        private readonly Kpi13Repository $repo,
        private readonly ThresholdService $thresholdService,
    ) {}

    // ──────────────────────────────────────────────────────────────
    //  PUBLIC
    // ──────────────────────────────────────────────────────────────

    public function getChart(array $filters): Kpi13ChartDTO
    {
        $tahun = isset($filters['tahun']) ? (string) $filters['tahun'] : null;

        // 1. Fetch semua data dari Cube.js (sequential — Cube pre-agg sangat cepat)
        $total       = $this->repo->getTotalAlumniPerProdi($tahun);
        $bekerja     = $this->repo->getBekerjaPerProdi($tahun);
        $cepat       = $this->repo->getMasaTungguCepatPerProdi($tahun);
        $sesuai      = $this->repo->getKesesuaianPerProdi($tahun);
        $wirausaha   = $this->repo->getWirausahaPerProdi($tahun);
        $pendapatan  = $this->repo->getPendapatanPerProdi($tahun);
        $aboveAmbang = $this->repo->getPendapatanAboveAmbangPerProdi($tahun, self::AMBANG_UMP_DEFAULT);
        $years       = $this->repo->getAvailableYears();

        // 2. Build lookup maps indexed by id_prodi untuk O(1) lookup
        $bekerjaMap     = $bekerja->keyBy('id_prodi');
        $cepatMap       = $cepat->keyBy('id_prodi');
        $sesuaiMap      = $sesuai->keyBy('id_prodi');
        $wirausahaMap   = $wirausaha->keyBy('id_prodi');
        $pendapatanMap  = $pendapatan->keyBy('id_prodi');
        $aboveAmbangMap = $aboveAmbang->keyBy('id_prodi');

        // 3. Hitung persentase per prodi
        $prodiRows = $total->map(function (array $row) use (
            $bekerjaMap,
            $cepatMap,
            $sesuaiMap,
            $wirausahaMap,
            $pendapatanMap,
            $aboveAmbangMap
        ): array {
            $id  = $row['id_prodi'];
            $n   = max($row['total'], 1); // guard div/0

            $jmlBekerja   = (int) ($bekerjaMap[$id]['bekerja']   ?? 0);
            $jmlCepat     = (int) ($cepatMap[$id]['cepat']       ?? 0);
            $jmlSesuai    = (int) ($sesuaiMap[$id]['sesuai']     ?? 0);
            $jmlWirausaha = (int) ($wirausahaMap[$id]['wirausaha'] ?? 0);

            $avgGaji           = (int) ($pendapatanMap[$id]['avg_gaji'] ?? 0);
            $totalDenganDataUmp = (int) ($pendapatanMap[$id]['total_dengan_data_ump'] ?? 0);
            $jmlAboveAmbang     = (int) ($aboveAmbangMap[$id]['count_above'] ?? 0);

            // Denominator masa tunggu & kesesuaian = jumlah yang bekerja
            $nBekerja = max($jmlBekerja, 1);

            $metrics = [
                'keterserapan' => $this->pct($jmlBekerja,       $n),
                'wirausaha'    => $this->pct($jmlWirausaha,     $n),
                'masa_tunggu'  => $this->pct($jmlCepat,         $nBekerja),
                'kesesuaian'   => $this->pct($jmlSesuai,        $nBekerja),
                'pendapatan'   => $this->pct($jmlAboveAmbang,   max($totalDenganDataUmp, 1)),
            ];

            return [
                'id_prodi'     => $id,
                'prodi'        => $row['nama_prodi'],
                'jurusan'      => $row['jurusan'],
                'total_alumni' => $row['total'],

                // Persentase — 2 desimal (pendapatan = % alumni bergaji >= 1,2x UMP)
                ...$metrics,

                // Status threshold per metrik: 'unggul' | 'baik' | 'kurang' | null (belum ada data threshold)
                'threshold_status' => $this->resolveThresholdStatus($id, $metrics),

                // Angka mentah — untuk tooltip detail di FE
                'raw' => [
                    'bekerja'          => $jmlBekerja,
                    'cepat'            => $jmlCepat,
                    'sesuai'           => $jmlSesuai,
                    'wirausaha'        => $jmlWirausaha,
                    'avg_gaji'         => $avgGaji,
                    'ambang_ump_multiplier' => self::AMBANG_UMP_DEFAULT,
                ],
            ];
        })->values()->toArray();

        return new Kpi13ChartDTO(
            prodiRows:      $prodiRows,
            availableYears: $years->toArray(),
            filters:        array_filter(['tahun' => $tahun]),
        );
    }

    // ──────────────────────────────────────────────────────────────
    //  Threshold status per metrik, per prodi
    // ──────────────────────────────────────────────────────────────

    /**
     * @param array<string,float> $metrics key metrik → nilai persentase yang sudah dihitung
     * @return array<string, 'unggul'|'baik'|'kurang'|null>
     */
    private function resolveThresholdStatus(int $prodiId, array $metrics): array
    {
        $status = [];

        foreach (self::METRIC_INDICATOR_KEY as $metricKey => $indicatorKey) {
            $chart   = $this->thresholdService->forChart($prodiId, $indicatorKey);
            $version = collect($chart['versions'] ?? [])->firstWhere('is_active', true);
            $baik    = $version['thresholds']['baik']['value']   ?? null;
            $unggul  = $version['thresholds']['unggul']['value'] ?? null;
            $value   = $metrics[$metricKey];

            $status[$metricKey] = match (true) {
                $baik === null && $unggul === null => null,
                $unggul !== null && $value >= $unggul => 'unggul',
                $baik !== null && $value >= $baik => 'baik',
                default => 'kurang',
            };
        }

        return $status;
    }

    // ──────────────────────────────────────────────────────────────
    //  Data for export — reuse getChart, strip ke flat array
    // ──────────────────────────────────────────────────────────────

    public function getExportRows(array $filters): array
    {
        return $this->getChart($filters)->prodiRows;
    }

    // ──────────────────────────────────────────────────────────────
    //  PRIVATE
    // ──────────────────────────────────────────────────────────────

    private function pct(int $numerator, int $denominator): float
    {
        if ($denominator <= 0) return 0.0;
        return round(($numerator / $denominator) * 100, 2);
    }
}