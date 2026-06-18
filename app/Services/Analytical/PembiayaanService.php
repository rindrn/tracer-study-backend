<?php

namespace App\Services\Analytical;

use App\DTOs\Analytical\Pembiayaan\PembiayaanPieDTO;
use App\DTOs\Analytical\Pembiayaan\PembiayaanPerProdiDTO;
use App\DTOs\Analytical\Pembiayaan\PembiayaanBandingkanDTO;
use App\DTOs\Analytical\Pembiayaan\PembiayaanDrillDownDTO;
use App\DTOs\Analytical\Pembiayaan\PembiayaanAntarPeriodeDTO;
use App\Repositories\Analytical\PembiayaanRepository;

class PembiayaanService
{
    public function __construct(
        private readonly PembiayaanRepository $repo,
    ) {}

    public function getPie(array $params): PembiayaanPieDTO
    {
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

        // Pie data
        $data = $pieRaw->map(fn($r) => [
            'sumber_biaya' => $r['sumber_biaya'],
            'count'        => $r['count'],
            'pct'          => $total > 0 ? round($r['count'] / $total * 100, 1) : 0.0,
        ])->values()->toArray();

        // Grouped bar per tahun
        $groupedBar = [];
        $tahunGroups = $perTahunRaw->groupBy('tahun_lulus');

        foreach ($tahunGroups as $tahun => $rows) {
            $totalTahun = $rows->sum('count');

            $sumber = $rows->map(fn($r) => [
                'label' => $r['sumber_biaya'],
                'count' => $r['count'],
                'pct'   => $totalTahun > 0 ? round($r['count'] / $totalTahun * 100, 1) : 0.0,
            ])->values()->toArray();

            $groupedBar[] = [
                'tahun_lulus' => $tahun,
                'sumber'      => $sumber,
            ];
        }

        return new PembiayaanPieDTO(
            data:       $data,
            groupedBar: $groupedBar,
            total:      $total,
            filters:    $this->activeFilters($params),
        );
    }

    public function getPerProdi(array $params): PembiayaanPerProdiDTO
    {
        $raw = $this->repo->getPerProdiData(
            jenjang:        $params['jenjang']         ?? null,
            jurusan:        $params['jurusan']         ?? null,
            namaProdi:      $params['nama_prodi']      ?? null,
            tahunLulus:     $params['tahun_lulus']     ?? null,
            mingguSnapshot: $params['minggu_snapshot'] ?? null,
        );

        $data = $this->reshapePerProdi($raw);

        return new PembiayaanPerProdiDTO(
            data:    $data,
            filters: $this->activeFilters($params),
        );
    }

    public function getBandingkan(array $params): PembiayaanBandingkanDTO
    {
        $prodiFilter = $params['prodi'] ?? [];
        if (is_string($prodiFilter)) {
            $prodiFilter = [$prodiFilter];
        }

        $raw = $this->repo->getBandingkanData(
            prodiFilter:    $prodiFilter,
            jenjang:        $params['jenjang']         ?? null,
            jurusan:        $params['jurusan']         ?? null,
            tahunLulus:     $params['tahun_lulus']     ?? null,
            mingguSnapshot: $params['minggu_snapshot'] ?? null,
        );

        $data      = $this->reshapePerProdi($raw);
        $prodiList = array_column($data, 'nama_prodi');

        return new PembiayaanBandingkanDTO(
            data:      $data,
            prodiList: array_values(array_unique($prodiList)),
            filters:   $this->activeFilters($params),
        );
    }

    private function reshapePerProdi(\Illuminate\Support\Collection $raw): array
    {
        $grouped = $raw->groupBy('nama_prodi');
        $data    = [];

        foreach ($grouped as $namaProdi => $rows) {
            $first      = $rows->first();
            $total      = $rows->sum('count');

            $sumber = $rows->map(fn($r) => [
                'label' => $r['sumber_biaya'],
                'count' => $r['count'],
                'pct'   => $total > 0 ? round($r['count'] / $total * 100, 1) : 0.0,
            ])->values()->toArray();

            $data[] = [
                'nama_prodi' => $namaProdi,
                'jenjang'    => $first['jenjang'],
                'total'      => $total,
                'sumber'     => $sumber,
            ];
        }

        return $data;
    }

    public function getAntarPeriode(array $params): PembiayaanAntarPeriodeDTO
    {
        $raw = $this->repo->getPerTahunData(
            jenjang:        $params['jenjang']         ?? null,
            jurusan:        $params['jurusan']         ?? null,
            namaProdi:      $params['nama_prodi']      ?? null,
            tahunLulus:     null, // endpoint ini tidak menerima filter tahun_lulus
            mingguSnapshot: $params['minggu_snapshot'] ?? null,
        );

        $tahunGroups = $raw->groupBy('tahun_lulus')->sortKeys();

        $data           = [];
        $availableTahun = [];

        foreach ($tahunGroups as $tahun => $rows) {
            $totalTahun = $rows->sum('count');

            $sumber = $rows->map(fn($r) => [
                'label' => $r['sumber_biaya'],
                'count' => $r['count'],
                'pct'   => $totalTahun > 0 ? round($r['count'] / $totalTahun * 100, 1) : 0.0,
            ])->values()->toArray();

            $data[] = [
                'tahun_lulus' => $tahun,
                'total'       => $totalTahun,
                'sumber'      => $sumber,
            ];

            $availableTahun[] = $tahun;
        }

        return new PembiayaanAntarPeriodeDTO(
            data:           $data,
            availableTahun: $availableTahun,
            filters:        $this->activeFilters($params, ['jenjang', 'jurusan', 'nama_prodi', 'minggu_snapshot']),
        );
    }

    public function getDrillDown(array $params): PembiayaanDrillDownDTO
    {
        $page    = max(1, (int) ($params['page']     ?? 1));
        $perPage = min(100, max(5, (int) ($params['per_page'] ?? 15)));

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