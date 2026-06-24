<?php

namespace App\Services\Analytical;

use App\DTOs\Analytical\ResponseRate\ResponseRateBarDTO;
use App\DTOs\Analytical\ResponseRate\ResponseRatePieDTO;
use App\DTOs\Analytical\ResponseRate\ResponseRateTrendDTO;
use App\DTOs\Analytical\ResponseRate\ResponseRateDrillDownDTO;
use App\Repositories\Analytical\ResponseRateRepository;

class ResponseRateService
{
    public function __construct(
        private readonly ResponseRateRepository $repo,
    ) {}

    // ──────────────────────────────────────────────────────────────
    //  BAR
    // ──────────────────────────────────────────────────────────────

    public function getBar(array $params): ResponseRateBarDTO
    {
        $sort = $params['sort'] ?? 'valueDesc';

        $raw = $this->repo->getBarData(
            jenjang:        $params['jenjang']         ?? null,
            namaProdi:      $params['nama_prodi']      ?? null,
            graduationYear: $params['graduation_year'] ?? null,
        );

        $data = $raw->map(function ($r) {
            $total = $r['total'];

            $pctResponded    = $total > 0 ? round($r['count_submitted'] / $total * 100, 1) : 0.0;
            $pctNotResponded = $total > 0 ? round(($r['count_ongoing'] + $r['count_started']) / $total * 100, 1) : 0.0;

            return [
                'prodi'        => $r['nama_prodi'],
                'jenjang'      => $r['jenjang'],
                'responded'    => $pctResponded,
                'notResponded' => $pctNotResponded,
                'total'        => $total,
                'breakdown'    => [
                    'submitted' => $r['count_submitted'],  // Selesai
                    'ongoing'   => $r['count_ongoing'],    // Sedang Mengisi
                    'started'   => $r['count_started'],    // Belum Mengisi
                ],
            ];
        });

        $sorted = $this->applySort($data, $sort);

        return new ResponseRateBarDTO(
            data:    $sorted->values()->toArray(),
            sort:    $sort,
            filters: $this->activeFilters($params),
        );
    }

    // ──────────────────────────────────────────────────────────────
    //  PIE
    // ──────────────────────────────────────────────────────────────

    public function getPie(array $params): ResponseRatePieDTO
    {
        $raw = $this->repo->getPieData(
            jenjang:        $params['jenjang']         ?? null,
            namaProdi:      $params['nama_prodi']      ?? null,
            graduationYear: $params['graduation_year'] ?? null,
        );

        $total = $raw['total'];

        $data = [
            [
                'name'   => 'Selesai',
                'status' => 'submitted',   // ← langsung bisa dipakai sebagai params drilldown
                'value'  => $raw['count_submitted'],
                'pct'    => $total > 0 ? round($raw['count_submitted'] / $total * 100, 1) : 0.0,
            ],
            [
                'name'   => 'Sedang Mengisi',
                'status' => 'ongoing',     // ← langsung bisa dipakai sebagai params drilldown
                'value'  => $raw['count_ongoing'],
                'pct'    => $total > 0 ? round($raw['count_ongoing'] / $total * 100, 1) : 0.0,
            ],
            [
                'name'   => 'Belum Mengisi',
                'status' => 'started',     // ← langsung bisa dipakai sebagai params drilldown
                'value'  => $raw['count_started'],
                'pct'    => $total > 0 ? round($raw['count_started'] / $total * 100, 1) : 0.0,
            ],
        ];

        return new ResponseRatePieDTO(
            data:    $data,
            total:   $total,
            filters: $this->activeFilters($params),
        );
    }

    // ──────────────────────────────────────────────────────────────
    //  TREND
    // ──────────────────────────────────────────────────────────────

    public function getTrend(array $params): ResponseRateTrendDTO
    {
        $raw = $this->repo->getTrendData(
            jenjang:        $params['jenjang']         ?? null,
            namaProdi:      $params['nama_prodi']      ?? null,
            graduationYear: $params['graduation_year'] ?? null,
        );

        $data = $raw->map(function ($r) {
            $total = $r['total'];
            $rate  = $total > 0 ? round($r['count_submitted'] / $total * 100, 1) : 0.0;

            return [
                'year'      => $r['graduation_year'],
                'rate'      => $rate,
                'total'     => $total,
                'breakdown' => [
                    'submitted' => $r['count_submitted'],
                    'ongoing'   => $r['count_ongoing'],
                    'started'   => $r['count_started'],
                ],
            ];
        })->values()->toArray();

        return new ResponseRateTrendDTO(
            data:    $data,
            filters: $this->activeFilters($params),
        );
    }

    // ──────────────────────────────────────────────────────────────
    //  DRILL-DOWN
    // ──────────────────────────────────────────────────────────────

    public function getDrillDown(array $params): ResponseRateDrillDownDTO
    {
        $page    = max(1, (int) ($params['page']     ?? 1));
        $perPage = min(100, max(5, (int) ($params['per_page'] ?? 15)));
        $status  = $params['status'];

        $result = $this->repo->getDetailAlumniByStatus(
            status:         $status,
            jenjang:        $params['jenjang']         ?? null,
            namaProdi:      $params['nama_prodi']      ?? null,
            graduationYear: $params['graduation_year'] ?? null,
            search:         $params['search']          ?? null,
            page:           $page,
            perPage:        $perPage,
        );

        return new ResponseRateDrillDownDTO(
            data:        $result['data'],
            status:      $status,
            page:        $page,
            perPage:     $perPage,
            totalOnPage: $result['total_on_page'],
            totalCount:  $result['total_count'],
            filters:     $this->activeFilters($params, ['jenjang', 'nama_prodi', 'graduation_year']),
        );
    }

    // ──────────────────────────────────────────────────────────────
    //  PRIVATE HELPERS
    // ──────────────────────────────────────────────────────────────

    private function applySort(\Illuminate\Support\Collection $data, string $sort): \Illuminate\Support\Collection
    {
        return match ($sort) {
            'valueAsc' => $data->sortBy('responded'),
            'name'     => $data->sortBy(fn($r) => $r['prodi']),
            default    => $data->sortByDesc('responded'),
        };
    }

    private function activeFilters(array $params, array $keys = []): array
    {
        $allKeys = ['jenjang', 'nama_prodi', 'graduation_year'];
        $keys    = empty($keys) ? $allKeys : $keys;

        return array_filter(
            array_intersect_key($params, array_flip($keys)),
            fn($v) => $v !== null && $v !== '' && $v !== [],
        );
    }
}