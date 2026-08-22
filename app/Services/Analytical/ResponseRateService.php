<?php

namespace App\Services\Analytical;

use App\DTOs\Analytical\ResponseRate\ResponseRateBarDTO;
use App\DTOs\Analytical\ResponseRate\ResponseRatePieDTO;
use App\DTOs\Analytical\ResponseRate\ResponseRateTrendDTO;
use App\DTOs\Analytical\ResponseRate\ResponseRateDrillDownDTO;
use App\Repositories\Analytical\ResponseRateRepository;
use App\Traits\WithCache;

class ResponseRateService
{
    use WithCache;

    private const TTL = 3600;

    public function __construct(
        private readonly ResponseRateRepository $repo,
    ) {}

    // ── BAR ───────────────────────────────────────────────────────

    public function getBar(array $params): ResponseRateBarDTO
    {
        $sort = $params['sort'] ?? 'valueDesc';
        $key  = $this->key('response_rate:bar', $params);

        $data = $this->remember($key, function () use ($params, $sort) {
            $raw = $this->repo->getBarData(
                jenjang:        $params['jenjang']         ?? null,
                namaProdi:      $params['nama_prodi']      ?? null,
                jurusan:        $params['jurusan']         ?? null,
                idProdiIn:      $params['id_prodi_in'] ?? null,
                graduationYear: $params['graduation_year'] ?? null,
            );

            $data = $raw->map(function ($r) {
                $total           = $r['total'];
                $pctResponded    = $total > 0 ? round($r['count_submitted'] / $total * 100, 1) : 0.0;
                $pctNotResponded = $total > 0 ? round(($r['count_ongoing'] + $r['count_started']) / $total * 100, 1) : 0.0;

                return [
                    'prodi'        => $r['nama_prodi'],
                    'jenjang'      => $r['jenjang'],
                    'responded'    => $pctResponded,
                    'notResponded' => $pctNotResponded,
                    'total'        => $total,
                    'breakdown'    => [
                        'submitted' => $r['count_submitted'],
                        'ongoing'   => $r['count_ongoing'],
                        'started'   => $r['count_started'],
                    ],
                ];
            });

            return $this->applySort($data, $sort)->values()->toArray();
        }, self::TTL, ['analytics-dashboard']);

        return new ResponseRateBarDTO(
            data:    $data,
            sort:    $sort,
            filters: $this->activeFilters($params),
        );
    }

    // ── PIE ───────────────────────────────────────────────────────

    public function getPie(array $params): ResponseRatePieDTO
    {
        $key = $this->key('response_rate:pie', $params);

        $cached = $this->remember($key, function () use ($params) {
            $raw   = $this->repo->getPieData(
                jenjang:        $params['jenjang']         ?? null,
                namaProdi:      $params['nama_prodi']      ?? null,
                jurusan:        $params['jurusan']         ?? null,
                idProdiIn:      $params['id_prodi_in'] ?? null,
                graduationYear: $params['graduation_year'] ?? null,
            );
            $total = $raw['total'];

            return [
                'data'  => [
                    ['name' => 'Selesai',         'status' => 'submitted', 'value' => $raw['count_submitted'], 'pct' => $total > 0 ? round($raw['count_submitted'] / $total * 100, 1) : 0.0],
                    ['name' => 'Sedang Mengisi',  'status' => 'ongoing',   'value' => $raw['count_ongoing'],   'pct' => $total > 0 ? round($raw['count_ongoing']   / $total * 100, 1) : 0.0],
                    ['name' => 'Belum Mengisi',   'status' => 'started',   'value' => $raw['count_started'],   'pct' => $total > 0 ? round($raw['count_started']   / $total * 100, 1) : 0.0],
                ],
                'total' => $total,
            ];
        }, self::TTL, ['analytics-dashboard']);

        return new ResponseRatePieDTO(
            data:    $cached['data'],
            total:   $cached['total'],
            filters: $this->activeFilters($params),
        );
    }

    // ── TREND ─────────────────────────────────────────────────────

    public function getTrend(array $params): ResponseRateTrendDTO
    {
        $key = $this->key('response_rate:trend', $params);

        $data = $this->remember($key, function () use ($params) {
            $raw = $this->repo->getTrendData(
                jenjang:        $params['jenjang']         ?? null,
                namaProdi:      $params['nama_prodi']      ?? null,
                jurusan:        $params['jurusan']         ?? null,
                idProdiIn:      $params['id_prodi_in'] ?? null,
                graduationYear: $params['graduation_year'] ?? null,
            );

            return $raw->map(function ($r) {
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
        }, self::TTL, ['analytics-dashboard']);

        return new ResponseRateTrendDTO(
            data:    $data,
            filters: $this->activeFilters($params),
        );
    }

    // ── DRILL-DOWN (tidak di-cache) ───────────────────────────────

    public function getDrillDown(array $params): ResponseRateDrillDownDTO
    {
        $page    = max(1, (int) ($params['page']     ?? 1));
        $perPage = min(100, max(5, (int) ($params['per_page'] ?? 15)));
        $status  = $params['status'];

        $result = $this->repo->getDetailAlumniByStatus(
            status:         $status,
            jenjang:        $params['jenjang']         ?? null,
            namaProdi:      $params['nama_prodi']      ?? null,
                jurusan:        $params['jurusan']         ?? null,
                idProdiIn:      $params['id_prodi_in'] ?? null,
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
            filters:     $this->activeFilters($params, ['jenjang', 'nama_prodi', 'jurusan', 'graduation_year']),
        );
    }

    // ── Helpers ───────────────────────────────────────────────────

    private function applySort(\Illuminate\Support\Collection $data, string $sort): \Illuminate\Support\Collection
    {
        return match ($sort) {
            'valueAsc' => $data->sortBy('responded'),
            'name'     => $data->sortBy(fn($r) => $r['prodi']),
            default    => $data->sortByDesc('responded'),
        };
    }

    private function key(string $prefix, array $params): string
    {
        $relevant = array_diff_key($params, array_flip(['page', 'per_page', 'search']));
        ksort($relevant);
        return $prefix . ':' . md5(json_encode($relevant));
    }

    private function activeFilters(array $params, array $keys = []): array
    {
        // 'jurusan' ikut dilaporkan supaya kajur melihat cakupan yang
        // sedang berlaku pada dirinya, bukan mengira dasbornya se-institusi.
        $allKeys = ['jenjang', 'nama_prodi', 'jurusan', 'graduation_year'];
        $keys    = empty($keys) ? $allKeys : $keys;
        return array_filter(
            array_intersect_key($params, array_flip($keys)),
            fn($v) => $v !== null && $v !== '' && $v !== [],
        );
    }
}