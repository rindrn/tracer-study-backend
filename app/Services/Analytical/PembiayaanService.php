<?php

namespace App\Services\Analytical;

use App\DTOs\Analytical\Pembiayaan\PembiayaanPieDTO;
use App\DTOs\Analytical\Pembiayaan\PembiayaanPerProdiDTO;
use App\DTOs\Analytical\Pembiayaan\PembiayaanBandingkanDTO;
use App\DTOs\Analytical\Pembiayaan\PembiayaanDrillDownDTO;
use App\DTOs\Analytical\Pembiayaan\PembiayaanAntarPeriodeDTO;
use App\Repositories\Analytical\PembiayaanRepository;
use App\Traits\WithCache;

class PembiayaanService
{
    use WithCache;

    private const TTL = 3600;

    public function __construct(
        private readonly PembiayaanRepository $repo,
    ) {}

    public function getPie(array $params): PembiayaanPieDTO
    {
        $key = $this->key('pembiayaan:pie', $params);

        $cached = $this->remember($key, function () use ($params) {
            $pieRaw = $this->repo->getPieData(
                jenjang:        $params['jenjang']         ?? null,
                jurusan:        $params['jurusan']         ?? null,
                namaProdi:      $params['nama_prodi']      ?? null,
                tahunLulus:     $params['tahun_lulus']     ?? null,
                mingguSnapshot: $params['minggu_snapshot'] ?? null,
            );

            $perTahunRaw = $this->repo->getPerTahunData(
                jenjang:        $params['jenjang']         ?? null,
                jurusan:        $params['jurusan']         ?? null,
                namaProdi:      $params['nama_prodi']      ?? null,
                tahunLulus:     $params['tahun_lulus']     ?? null,
                mingguSnapshot: $params['minggu_snapshot'] ?? null,
            );

            $total = $pieRaw->sum('count');

            $data = $pieRaw->map(fn($r) => [
                'sumber_biaya' => $r['sumber_biaya'],
                'count'        => $r['count'],
                'pct'          => $total > 0 ? round($r['count'] / $total * 100, 1) : 0.0,
            ])->values()->toArray();

            $groupedBar = [];
            foreach ($perTahunRaw->groupBy('tahun_lulus') as $tahun => $rows) {
                $totalTahun = $rows->sum('count');
                $groupedBar[] = [
                    'tahun_lulus' => $tahun,
                    'sumber'      => $rows->map(fn($r) => [
                        'label' => $r['sumber_biaya'],
                        'count' => $r['count'],
                        'pct'   => $totalTahun > 0 ? round($r['count'] / $totalTahun * 100, 1) : 0.0,
                    ])->values()->toArray(),
                ];
            }

            return ['data' => $data, 'groupedBar' => $groupedBar, 'total' => $total];
        }, self::TTL);

        return new PembiayaanPieDTO(
            data:       $cached['data'],
            groupedBar: $cached['groupedBar'],
            total:      $cached['total'],
            filters:    $this->activeFilters($params),
        );
    }

    public function getPerProdi(array $params): PembiayaanPerProdiDTO
    {
        $key = $this->key('pembiayaan:per_prodi', $params);

        $data = $this->remember($key, function () use ($params) {
            $raw = $this->repo->getPerProdiData(
                jenjang:        $params['jenjang']         ?? null,
                jurusan:        $params['jurusan']         ?? null,
                namaProdi:      $params['nama_prodi']      ?? null,
                tahunLulus:     $params['tahun_lulus']     ?? null,
                mingguSnapshot: $params['minggu_snapshot'] ?? null,
            );
            return $this->reshapePerProdi($raw);
        }, self::TTL);

        return new PembiayaanPerProdiDTO(
            data:    $data,
            filters: $this->activeFilters($params),
        );
    }

    public function getBandingkan(array $params): PembiayaanBandingkanDTO
    {
        $key = $this->key('pembiayaan:bandingkan', $params);

        $cached = $this->remember($key, function () use ($params) {
            $prodiFilter = is_string($params['prodi'] ?? null)
                ? [$params['prodi']]
                : ($params['prodi'] ?? []);

            $raw  = $this->repo->getBandingkanData(
                prodiFilter:    $prodiFilter,
                jenjang:        $params['jenjang']         ?? null,
                jurusan:        $params['jurusan']         ?? null,
                tahunLulus:     $params['tahun_lulus']     ?? null,
                mingguSnapshot: $params['minggu_snapshot'] ?? null,
            );
            $data      = $this->reshapePerProdi($raw);
            $prodiList = array_values(array_unique(array_column($data, 'nama_prodi')));
            return ['data' => $data, 'prodiList' => $prodiList];
        }, self::TTL);

        return new PembiayaanBandingkanDTO(
            data:      $cached['data'],
            prodiList: $cached['prodiList'],
            filters:   $this->activeFilters($params),
        );
    }

    public function getAntarPeriode(array $params): PembiayaanAntarPeriodeDTO
    {
        $key = $this->key('pembiayaan:antar_periode', $params);

        $cached = $this->remember($key, function () use ($params) {
            $raw         = $this->repo->getPerTahunData(
                jenjang:        $params['jenjang']         ?? null,
                jurusan:        $params['jurusan']         ?? null,
                namaProdi:      $params['nama_prodi']      ?? null,
                tahunLulus:     null,
                mingguSnapshot: $params['minggu_snapshot'] ?? null,
            );

            $data           = [];
            $availableTahun = [];

            foreach ($raw->groupBy('tahun_lulus')->sortKeys() as $tahun => $rows) {
                $totalTahun = $rows->sum('count');
                $data[] = [
                    'tahun_lulus' => $tahun,
                    'total'       => $totalTahun,
                    'sumber'      => $rows->map(fn($r) => [
                        'label' => $r['sumber_biaya'],
                        'count' => $r['count'],
                        'pct'   => $totalTahun > 0 ? round($r['count'] / $totalTahun * 100, 1) : 0.0,
                    ])->values()->toArray(),
                ];
                $availableTahun[] = $tahun;
            }

            return ['data' => $data, 'availableTahun' => $availableTahun];
        }, self::TTL);

        return new PembiayaanAntarPeriodeDTO(
            data:           $cached['data'],
            availableTahun: $cached['availableTahun'],
            filters:        $this->activeFilters($params, ['jenjang', 'jurusan', 'nama_prodi', 'minggu_snapshot']),
        );
    }

    // ── DrillDown tidak di-cache ──────────────────────────────────

    public function getDrillDown(array $params): PembiayaanDrillDownDTO
    {
        $page        = max(1, (int) ($params['page']     ?? 1));
        $perPage     = min(100, max(5, (int) ($params['per_page'] ?? 15)));
        $sumberBiaya = $params['sumber_biaya'] ?? null;

        $result = $this->repo->getDetailAlumni(
            sumberBiaya:    $sumberBiaya,
            jenjang:        $params['jenjang']         ?? null,
            jurusan:        $params['jurusan']         ?? null,
            namaProdi:      $params['nama_prodi']      ?? null,
            tahunLulus:     $params['tahun_lulus']     ?? null,
            mingguSnapshot: $params['minggu_snapshot'] ?? null,
            search:         $params['search']          ?? null,
            page:           $page,
            perPage:        $perPage,
        );

        return new PembiayaanDrillDownDTO(
            data:        $result['data'],
            page:        $page,
            perPage:     $perPage,
            totalOnPage: $result['total_on_page'],
            filters:     $this->activeFilters($params, [
                'sumber_biaya', 'tahun_lulus', 'jenjang', 'jurusan', 'nama_prodi',
            ]),
        );
    }

    // ── Helpers ───────────────────────────────────────────────────

    private function reshapePerProdi(\Illuminate\Support\Collection $raw): array
    {
        $data = [];
        foreach ($raw->groupBy('nama_prodi') as $namaProdi => $rows) {
            $first = $rows->first();
            $total = $rows->sum('count');
            $data[] = [
                'nama_prodi' => $namaProdi,
                'jenjang'    => $first['jenjang'],
                'total'      => $total,
                'sumber'     => $rows->map(fn($r) => [
                    'label' => $r['sumber_biaya'],
                    'count' => $r['count'],
                    'pct'   => $total > 0 ? round($r['count'] / $total * 100, 1) : 0.0,
                ])->values()->toArray(),
            ];
        }
        return $data;
    }

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