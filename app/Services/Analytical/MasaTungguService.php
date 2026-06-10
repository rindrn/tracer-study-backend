<?php

namespace App\Services\Analytical;

use App\DTOs\Analytical\MasaTunggu\MasaTungguBarDTO;
use App\DTOs\Analytical\MasaTunggu\MasaTungguDistribusiDTO;
use App\DTOs\Analytical\MasaTunggu\MasaTungguDrillDownDTO;
use App\DTOs\Analytical\MasaTunggu\MasaTungguBandingkanDTO;
use App\Repositories\Analytical\MasaTungguRepository;

/**
 * MasaTungguService
 *
 * Orkestrasi data dari MasaTungguRepository (KPI 5 — Masa Tunggu Kerja):
 *   - getBar()         → bar/combo % cepat + avg per prodi per tahun
 *   - getDistribusi()  → flat rows per prodi × tahun dengan count 3 rentang
 *   - getDrillDown()   → list alumni per rentang masa tunggu (drill-down modal)
 *   - getBandingkan()  → perbandingan distribusi masa tunggu per prodi
 *
 * Taruh di: app/Services/Analytical/MasaTungguService.php
 */
class MasaTungguService
{
    public function __construct(
        private readonly MasaTungguRepository $repo,
    ) {}

    // ──────────────────────────────────────────────────────────────
    //  BAR — % cepat + avg masa tunggu per prodi per tahun
    // ──────────────────────────────────────────────────────────────

    /**
     * Response persis sesuai spec BE.md:
     * {
     *   "filters": {},
     *   "data": [
     *     {
     *       "nama_prodi", "jenjang", "jurusan", "tahun_lulus",
     *       "count_alumni", "count_terserap", "count_masa_tunggu_cepat",
     *       "pct_cepat", "avg_masa_tunggu_bekerja"
     *     }
     *   ]
     * }
     *
     * pct_cepat = count_masa_tunggu_cepat / count_terserap × 100
     */
    public function getBar(array $params): MasaTungguBarDTO
    {
        $raw = $this->repo->getBarData(
            jenjang:        $params['jenjang']         ?? null,
            jurusan:        $params['jurusan']         ?? null,
            namaProdi:      $params['nama_prodi']      ?? null,
            tahunLulus:     $params['tahun_lulus']     ?? null,
            mingguSnapshot: $params['minggu_snapshot'] ?? null,
        );

        $data = $raw->map(function ($r) {
            $pctCepat = $r['count_terserap'] > 0
                ? round($r['count_masa_tunggu_cepat'] / $r['count_terserap'] * 100, 1)
                : 0.0;

            return [
                'nama_prodi'              => $r['nama_prodi'],
                'jenjang'                 => $r['jenjang'],
                'jurusan'                 => $r['jurusan'],
                'tahun_lulus'             => $r['tahun_lulus'],
                'count_alumni'            => $r['count_alumni'],
                'count_terserap'          => $r['count_terserap'],
                'count_masa_tunggu_cepat' => $r['count_masa_tunggu_cepat'],
                'pct_cepat'               => $pctCepat,
                'avg_masa_tunggu_bekerja' => $r['avg_masa_tunggu_bekerja'],
            ];
        })->values()->toArray();

        return new MasaTungguBarDTO(
            data:    $data,
            filters: $this->activeFilters($params),
        );
    }

    // ──────────────────────────────────────────────────────────────
    //  DISTRIBUSI — flat rows per prodi × tahun
    // ──────────────────────────────────────────────────────────────

    /**
     * Response persis sesuai spec BE.md:
     * {
     *   "filters": {},
     *   "data": [
     *     {
     *       "nama_prodi", "jenjang", "tahun_lulus",
     *       "count_tunggu_0_3_bulan", "count_tunggu_3_6_bulan", "count_tunggu_lebih_6_bulan",
     *       "avg_masa_tunggu_bekerja", "min_masa_tunggu_bekerja", "max_masa_tunggu_bekerja"
     *     }
     *   ]
     * }
     */
    public function getDistribusi(array $params): MasaTungguDistribusiDTO
    {
        $raw = $this->repo->getDistribusiData(
            jenjang:        $params['jenjang']         ?? null,
            jurusan:        $params['jurusan']         ?? null,
            namaProdi:      $params['nama_prodi']      ?? null,
            tahunLulus:     $params['tahun_lulus']     ?? null,
            mingguSnapshot: $params['minggu_snapshot'] ?? null,
        );

        // Kembalikan flat — FE yang handle tampilan
        $data = $raw->map(fn($r) => [
            'nama_prodi'                 => $r['nama_prodi'],
            'jenjang'                    => $r['jenjang'],
            'tahun_lulus'                => $r['tahun_lulus'],
            'count_tunggu_0_3_bulan'     => $r['count_tunggu_0_3_bulan'],
            'count_tunggu_3_6_bulan'     => $r['count_tunggu_3_6_bulan'],
            'count_tunggu_lebih_6_bulan' => $r['count_tunggu_lebih_6_bulan'],
            'avg_masa_tunggu_bekerja'    => $r['avg_masa_tunggu_bekerja'],
            'min_masa_tunggu_bekerja'    => $r['min_masa_tunggu_bekerja'],
            'max_masa_tunggu_bekerja'    => $r['max_masa_tunggu_bekerja'],
        ])->values()->toArray();

        return new MasaTungguDistribusiDTO(
            data:    $data,
            filters: $this->activeFilters($params),
        );
    }

    // ──────────────────────────────────────────────────────────────
    //  DRILL-DOWN — list alumni per rentang masa tunggu
    // ──────────────────────────────────────────────────────────────

    public function getDrillDown(array $params): MasaTungguDrillDownDTO
    {
        $page    = max(1, (int) ($params['page']     ?? 1));
        $perPage = min(100, max(5, (int) ($params['per_page'] ?? 15)));
        $rentang = $params['rentang'];

        $result = $this->repo->getDetailAlumniByRentang(
            rentang:        $rentang,
            jenjang:        $params['jenjang']         ?? null,
            jurusan:        $params['jurusan']         ?? null,
            namaProdi:      $params['nama_prodi']      ?? null,
            tahunLulus:     $params['tahun_lulus']     ?? null,
            mingguSnapshot: $params['minggu_snapshot'] ?? null,
            search:         $params['search']          ?? null,
            page:           $page,
            perPage:        $perPage,
        );

        return new MasaTungguDrillDownDTO(
            data:        $result['data'],
            rentang:     $rentang,
            page:        $page,
            perPage:     $perPage,
            totalOnPage: $result['total_on_page'],
            filters:     $this->activeFilters($params, [
                'jenjang', 'jurusan', 'nama_prodi', 'tahun_lulus', 'minggu_snapshot',
            ]),
        );
    }

    // ──────────────────────────────────────────────────────────────
    //  BANDINGKAN PER PRODI
    // ──────────────────────────────────────────────────────────────

    /**
     * Response persis sesuai spec BE.md:
     * {
     *   "filters": {},
     *   "prodi_list": [...],
     *   "data": [
     *     {
     *       "nama_prodi", "jenjang", "tahun_lulus",
     *       "count_tunggu_0_3_bulan", "count_tunggu_3_6_bulan", "count_tunggu_lebih_6_bulan",
     *       "avg_masa_tunggu_bekerja", "min_masa_tunggu_bekerja", "max_masa_tunggu_bekerja",
     *       "pct_cepat"
     *     }
     *   ]
     * }
     */
    public function getBandingkan(array $params): MasaTungguBandingkanDTO
    {
        $prodiFilter = $params['prodi'] ?? [];
        if (is_string($prodiFilter)) {
            $prodiFilter = [$prodiFilter];
        }

        $result = $this->repo->getDistribusiPerProdi(
            prodiFilter:    $prodiFilter,
            jenjang:        $params['jenjang']         ?? null,
            jurusan:        $params['jurusan']         ?? null,
            tahunLulus:     $params['tahun_lulus']     ?? null,
            mingguSnapshot: $params['minggu_snapshot'] ?? null,
        );

        return new MasaTungguBandingkanDTO(
            data:      $result['data'],
            prodiList: $result['prodi_list'],
            filters:   $this->activeFilters($params),
        );
    }

    // ──────────────────────────────────────────────────────────────
    //  PRIVATE HELPERS
    // ──────────────────────────────────────────────────────────────

    private function activeFilters(array $params, array $keys = []): array
    {
        $allKeys = ['jenjang', 'jurusan', 'nama_prodi', 'tahun_lulus', 'minggu_snapshot'];
        $keys    = empty($keys) ? $allKeys : $keys;

        return array_filter(
            array_intersect_key($params, array_flip($keys)),
            fn($v) => $v !== null && $v !== '' && $v !== [],
        );
    }
}