<?php

namespace App\Services\Analytical;

use App\DTOs\Analytical\MetodePembelajaran\MetodePembelajaranDTO;
use App\DTOs\Analytical\MetodePembelajaran\MetodePembelajaranBandingkanDTO;
use App\DTOs\Analytical\MetodePembelajaran\MetodePembelajaranDrillDownDTO;
use App\Repositories\Analytical\MetodePembelajaranRepository;
use App\Traits\WithCache;

class MetodePembelajaranService
{
    use WithCache;

    private const TTL = 3600;

    public function __construct(
        private readonly MetodePembelajaranRepository $repo,
    ) {}

    public function getMetode(array $params): MetodePembelajaranDTO
    {
        $key = $this->key('metode_pembelajaran:metode', $params);

        $data = $this->remember($key, function () use ($params) {
            return $this->repo->getMetodeData(
                jenjang:        $params['jenjang']         ?? null,
                jurusan:        $params['jurusan']         ?? null,
                namaProdi:      $params['nama_prodi']      ?? null,
                tahunLulus:     $params['tahun_lulus']     ?? null,
                mingguSnapshot: $params['minggu_snapshot'] ?? null,
            )->map(fn($r) => [
                'kode_field'      => $r['kode_field'],
                'label'           => $r['label'],
                'avg_skor'        => round($r['avg_skor'], 2),
                'count_responden' => $r['count'],
            ])->values()->toArray();
        }, self::TTL, ['analytics-dashboard']);

        return new MetodePembelajaranDTO(
            data:    $data,
            filters: $this->activeFilters($params),
        );
    }

    public function getBandingkan(array $params): MetodePembelajaranBandingkanDTO
    {
        $key = $this->key('metode_pembelajaran:bandingkan', $params);

        $cached = $this->remember($key, function () use ($params) {
            $prodiFilter = is_string($params['prodi'] ?? null)
                ? [$params['prodi']]
                : ($params['prodi'] ?? []);

            $raw       = $this->repo->getBandingkanData(
                prodiFilter:    $prodiFilter,
                jenjang:        $params['jenjang']         ?? null,
                jurusan:        $params['jurusan']         ?? null,
                tahunLulus:     $params['tahun_lulus']     ?? null,
                mingguSnapshot: $params['minggu_snapshot'] ?? null,
            );

            $data      = [];
            $prodiList = [];

            foreach ($raw->groupBy('nama_prodi') as $namaProdi => $rows) {
                $first = $rows->first();
                $data[] = [
                    'nama_prodi' => $namaProdi,
                    'jenjang'    => $first['jenjang'],
                    'metode'     => $rows->groupBy('kode_field')->map(function ($group) {
                        $totalCount  = $group->sum('count');
                        $weightedAvg = $totalCount > 0
                            ? $group->sum(fn($r) => $r['avg_skor'] * $r['count']) / $totalCount
                            : 0.0;
                        $r0 = $group->first();
                        return [
                            'kode_field'      => $r0['kode_field'],
                            'label'           => $r0['label'],
                            'avg_skor'        => round($weightedAvg, 2),
                            'count_responden' => $totalCount,
                        ];
                    })->values()->toArray(),
                ];
                $prodiList[] = $namaProdi;
            }

            return ['data' => $data, 'prodiList' => array_values(array_unique($prodiList))];
        }, self::TTL, ['analytics-dashboard']);

        return new MetodePembelajaranBandingkanDTO(
            data:      $cached['data'],
            prodiList: $cached['prodiList'],
            filters:   $this->activeFilters($params),
        );
    }

    // ── DrillDown tidak di-cache ──────────────────────────────────

    public function getDrillDown(array $params): MetodePembelajaranDrillDownDTO
    {
        $page      = max(1, (int) ($params['page']     ?? 1));
        $perPage   = min(100, max(5, (int) ($params['per_page'] ?? 15)));
        $kodeField = $params['kode_field'] ?? '';

        $result = $this->repo->getDetailAlumni(
            kodeField:      $kodeField,
            jenjang:        $params['jenjang']         ?? null,
            jurusan:        $params['jurusan']         ?? null,
            namaProdi:      $params['nama_prodi']      ?? null,
            tahunLulus:     $params['tahun_lulus']     ?? null,
            mingguSnapshot: $params['minggu_snapshot'] ?? null,
            search:         $params['search']          ?? null,
            page:           $page,
            perPage:        $perPage,
        );

        return new MetodePembelajaranDrillDownDTO(
            data:        $result['data'],
            page:        $page,
            perPage:     $perPage,
            totalOnPage: $result['total_on_page'],
            filters:     $this->activeFilters(
                array_merge($params, ['kode_field' => $kodeField]),
                ['kode_field', 'jenjang', 'jurusan', 'nama_prodi', 'tahun_lulus', 'minggu_snapshot'],
            ),
        );
    }

    // ── Helpers ───────────────────────────────────────────────────

    private function key(string $prefix, array $params): string
    {
        $relevant = array_diff_key($params, array_flip(['page', 'per_page', 'search']));
        ksort($relevant);
        return $prefix . ':' . md5(json_encode($relevant));
    }

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