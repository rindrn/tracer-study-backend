<?php

namespace App\Services\Analytical;

use App\DTOs\Analytical\Pembiayaan\PembiayaanPieDTO;
use App\DTOs\Analytical\Pembiayaan\PembiayaanPerProdiDTO;
use App\DTOs\Analytical\Pembiayaan\PembiayaanBandingkanDTO;
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