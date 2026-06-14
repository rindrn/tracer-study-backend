<?php

namespace App\Services\Analytical;

use App\DTOs\Analytical\MetodePembelajaran\MetodePembelajaranDTO;
use App\DTOs\Analytical\MetodePembelajaran\MetodePembelajaranBandingkanDTO;
use App\Repositories\Analytical\MetodePembelajaranRepository;

class MetodePembelajaranService
{
    public function __construct(
        private readonly MetodePembelajaranRepository $repo,
    ) {}

    public function getMetode(array $params): MetodePembelajaranDTO
    {
        $raw = $this->repo->getMetodeData(
            jenjang:        $params['jenjang']         ?? null,
            jurusan:        $params['jurusan']         ?? null,
            namaProdi:      $params['nama_prodi']      ?? null,
            tahunLulus:     $params['tahun_lulus']     ?? null,
            mingguSnapshot: $params['minggu_snapshot'] ?? null,
        );

        $data = $raw->map(fn($r) => [
            'kode_field'      => $r['kode_field'],
            'label'           => $r['label'],
            'avg_skor'        => round($r['avg_skor'], 2),
            'count_responden' => $r['count'],
        ])->values()->toArray();

        return new MetodePembelajaranDTO(
            data:    $data,
            filters: $this->activeFilters($params),
        );
    }

    public function getBandingkan(array $params): MetodePembelajaranBandingkanDTO
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

        // Group by nama_prodi → nested metode[]
        $grouped   = $raw->groupBy('nama_prodi');
        $data      = [];
        $prodiList = [];

        foreach ($grouped as $namaProdi => $rows) {
            $first = $rows->first();

            $metode = $rows->map(fn($r) => [
                'kode_field'      => $r['kode_field'],
                'label'           => $r['label'],
                'avg_skor'        => round($r['avg_skor'], 2),
                'count_responden' => $r['count'],
            ])->values()->toArray();

            $data[] = [
                'nama_prodi' => $namaProdi,
                'jenjang'    => $first['jenjang'],
                'metode'     => $metode,
            ];

            $prodiList[] = $namaProdi;
        }

        return new MetodePembelajaranBandingkanDTO(
            data:      $data,
            prodiList: array_values(array_unique($prodiList)),
            filters:   $this->activeFilters($params),
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