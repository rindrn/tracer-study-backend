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
    //  BAR — stacked bar per prodi (Kpi1ParticipationChart)
    // ──────────────────────────────────────────────────────────────

    /**
     * {
     *   "filters": {},
     *   "sort": "valueDesc",
     *   "data": [
     *     {
     *       "prodi": "Teknik Informatika",
     *       "jenjang": "D4",
     *       "responded": 76.5,
     *       "notResponded": 23.5,
     *       "total": 95,
     *       "breakdown": { "selesai": 62, "on_going": 10, "belum_mengisi": 23 }
     *     }
     *   ]
     * }
     *
     * responded    = % submitted / total
     * notResponded = % (ongoing + belum_mengisi) / total
     */
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

            $pctResponded    = $total > 0 ? round($r['count_selesai'] / $total * 100, 1) : 0.0;
            $pctNotResponded = $total > 0 ? round(($r['count_on_going'] + $r['count_belum_mengisi']) / $total * 100, 1) : 0.0;

            return [
                'prodi'        => $r['nama_prodi'],
                'jenjang'      => $r['jenjang'],
                'responded'    => $pctResponded,
                'notResponded' => $pctNotResponded,
                'total'        => $total,
                'breakdown'    => [
                    'selesai'       => $r['count_selesai'],
                    'on_going'      => $r['count_on_going'],
                    'belum_mengisi' => $r['count_belum_mengisi'],
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
    //  PIE — 3 status aggregate (Kpi2CompletionStatusChart)
    // ──────────────────────────────────────────────────────────────

    /**
     * {
     *   "filters": {},
     *   "total": 1093,
     *   "data": [
     *     { "name": "Selesai",         "value": 612, "pct": 56.0 },
     *     { "name": "Sedang Mengisi",  "value": 184, "pct": 16.8 },
     *     { "name": "Belum Mengisi",   "value": 297, "pct": 27.2 }
     *   ]
     * }
     */
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
                'name'  => 'Selesai',
                'value' => $raw['count_selesai'],
                'pct'   => $total > 0 ? round($raw['count_selesai'] / $total * 100, 1) : 0.0,
            ],
            [
                'name'  => 'Sedang Mengisi',
                'value' => $raw['count_on_going'],
                'pct'   => $total > 0 ? round($raw['count_on_going'] / $total * 100, 1) : 0.0,
            ],
            [
                'name'  => 'Belum Mengisi',
                'value' => $raw['count_belum_mengisi'],
                'pct'   => $total > 0 ? round($raw['count_belum_mengisi'] / $total * 100, 1) : 0.0,
            ],
        ];

        return new ResponseRatePieDTO(
            data:    $data,
            total:   $total,
            filters: $this->activeFilters($params),
        );
    }

    // ──────────────────────────────────────────────────────────────
    //  TREND — response rate per graduation_year (Kpi3ParticipationTrendChart)
    // ──────────────────────────────────────────────────────────────

    /**
     * {
     *   "filters": {},
     *   "data": [
     *     {
     *       "year": "2020",
     *       "rate": 42.0,
     *       "total": 1320,
     *       "breakdown": { "selesai": 480, "on_going": 74, "belum_mengisi": 766 }
     *     }
     *   ]
     * }
     *
     * "rate" = % submitted / total
     */
    public function getTrend(array $params): ResponseRateTrendDTO
    {
        $raw = $this->repo->getTrendData(
            jenjang:        $params['jenjang']         ?? null,
            namaProdi:      $params['nama_prodi']      ?? null,
            graduationYear: $params['graduation_year'] ?? null,
        );

        $data = $raw->map(function ($r) {
            $total = $r['total'];
            $rate  = $total > 0 ? round($r['count_selesai'] / $total * 100, 1) : 0.0;

            return [
                'year'      => $r['graduation_year'],
                'rate'      => $rate,
                'total'     => $total,
                'breakdown' => [
                    'selesai'       => $r['count_selesai'],
                    'on_going'      => $r['count_on_going'],
                    'belum_mengisi' => $r['count_belum_mengisi'],
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
            default    => $data->sortByDesc('responded'), // valueDesc
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