<?php

namespace App\Services\Analytical;

use App\DTOs\Analytical\Wirausaha\WirausahaBarDTO;
use App\DTOs\Analytical\Wirausaha\WirausahaPieDTO;
use App\DTOs\Analytical\Wirausaha\WirausahaDrillDownDTO;
use App\DTOs\Analytical\Wirausaha\WirausahaBandingkanDTO;
use App\Repositories\Analytical\WirausahaRepository;

class WirausahaService
{
    public function __construct(
        private readonly WirausahaRepository $repo,
    ) {}

    public function getBar(array $params): WirausahaBarDTO
    {
        // Ambil dua query paralel
        $wirausaha = $this->repo->getBarData(
            jenjang:        $params['jenjang']         ?? null,
            jurusan:        $params['jurusan']         ?? null,
            namaProdi:      $params['nama_prodi']      ?? null,
            tahunLulus:     $params['tahun_lulus']     ?? null,
            mingguSnapshot: $params['minggu_snapshot'] ?? null,
        );

        $totals = $this->repo->getBarDataTotal(
            jenjang:        $params['jenjang']         ?? null,
            jurusan:        $params['jurusan']         ?? null,
            namaProdi:      $params['nama_prodi']      ?? null,
            tahunLulus:     $params['tahun_lulus']     ?? null,
            mingguSnapshot: $params['minggu_snapshot'] ?? null,
        );

        // Index total by "nama_prodi|tahun_lulus"
        $totalMap = $totals->keyBy(fn($r) => $r['nama_prodi'] . '|' . $r['tahun_lulus']);

        $data = $wirausaha->map(function ($r) use ($totalMap) {
            $key         = $r['nama_prodi'] . '|' . $r['tahun_lulus'];
            $totalAlumni = $totalMap->get($key)['count_alumni'] ?? 0;
            $pct         = $totalAlumni > 0
                ? round($r['count_wirausaha'] / $totalAlumni * 100, 1)
                : 0.0;

            return [
                'nama_prodi'               => $r['nama_prodi'],
                'jenjang'                  => $r['jenjang'],
                'tahun_lulus'              => $r['tahun_lulus'],
                'count_alumni'             => $totalAlumni,
                'count_wirausaha'          => $r['count_wirausaha'],
                'pct_wirausaha'            => $pct,
                'avg_masa_tunggu_wirausaha'=> round($r['avg_masa_tunggu_wirausaha'], 1),
            ];
        })->values()->toArray();

        return new WirausahaBarDTO(
            data:    $data,
            filters: $this->activeFilters($params),
        );
    }

    public function getPie(array $params): WirausahaPieDTO
    {
        $posisiRaw = $this->repo->getPiePosisi(
            jenjang:        $params['jenjang']         ?? null,
            jurusan:        $params['jurusan']         ?? null,
            namaProdi:      $params['nama_prodi']      ?? null,
            tahunLulus:     $params['tahun_lulus']     ?? null,
            mingguSnapshot: $params['minggu_snapshot'] ?? null,
        );

        $kotaRaw = $this->repo->getKotaData(
            jenjang:        $params['jenjang']         ?? null,
            jurusan:        $params['jurusan']         ?? null,
            namaProdi:      $params['nama_prodi']      ?? null,
            tahunLulus:     $params['tahun_lulus']     ?? null,
            mingguSnapshot: $params['minggu_snapshot'] ?? null,
        );

        $total = $posisiRaw->sum('count');

        // Pisahkan top-3 dan sisanya
        $top3      = $posisiRaw->take(3);
        $lainnya   = $posisiRaw->skip(3);
        $countLain = $lainnya->sum('count');

        $posisi = $top3->map(fn($r) => [
            'label' => $r['label'],
            'count' => $r['count'],
            'pct'   => $total > 0 ? round($r['count'] / $total * 100, 1) : 0.0,
        ])->values()->toArray();

        // Tambahkan "Lainnya" hanya kalau ada data di rank 4+
        if ($countLain > 0) {
            $posisi[] = [
                'label' => 'Lainnya',
                'count' => $countLain,
                'pct'   => $total > 0 ? round($countLain / $total * 100, 1) : 0.0,
            ];
        }

        return new WirausahaPieDTO(
            posisi:      $posisi,
            sebaranKota: $kotaRaw->values()->toArray(),
            total:       $total,
            filters:     $this->activeFilters($params),
        );
    }

    public function getDrillDown(array $params): WirausahaDrillDownDTO
    {
        $page    = max(1, (int) ($params['page']     ?? 1));
        $perPage = min(100, max(5, (int) ($params['per_page'] ?? 15)));
        $tingkat = $params['tingkat'];

        $result = $this->repo->getDetailAlumni(
            tingkat:        $tingkat,
            jenjang:        $params['jenjang']         ?? null,
            namaProdi:      $params['nama_prodi']      ?? null,
            tahunLulus:     $params['tahun_lulus']     ?? null,
            mingguSnapshot: $params['minggu_snapshot'] ?? null,
            search:         $params['search']          ?? null,
            page:           $page,
            perPage:        $perPage,
        );

        return new WirausahaDrillDownDTO(
            data:        $result['data'],
            tingkat:     $tingkat,
            page:        $page,
            perPage:     $perPage,
            totalOnPage: $result['total_on_page'],
            filters:     $this->activeFilters($params, [
                'jenjang', 'nama_prodi', 'tahun_lulus', 'minggu_snapshot',
            ]),
        );
    }

    public function getBandingkan(array $params): WirausahaBandingkanDTO
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

        $totals = $this->repo->getBandingkanTotal(
            prodiFilter:    $prodiFilter,
            jenjang:        $params['jenjang']         ?? null,
            jurusan:        $params['jurusan']         ?? null,
            tahunLulus:     $params['tahun_lulus']     ?? null,
            mingguSnapshot: $params['minggu_snapshot'] ?? null,
        );

        // Index total by nama_prodi
        $totalMap = $totals->keyBy('nama_prodi');

        // Group raw by nama_prodi
        $grouped = $raw->groupBy('nama_prodi');

        $chart     = [];
        $prodiList = [];

        foreach ($grouped as $namaProdi => $rows) {
            $first        = $rows->first();
            $totalAlumni  = $totalMap->get($namaProdi)['count_alumni'] ?? 0;
            $countWir     = $rows->sum('count_wirausaha');
            $pctWir       = $totalAlumni > 0 ? round($countWir / $totalAlumni * 100, 1) : 0.0;

            // Weighted avg masa tunggu
            $weightedSum = $rows->sum(fn($r) => $r['avg_masa_tunggu'] * $r['count_wirausaha']);
            $avgMasTunggu = $countWir > 0 ? round($weightedSum / $countWir, 1) : 0.0;

            // Breakdown per tingkat
            $tingkatBreakdown = $rows->groupBy('label_tingkat')->map(function ($tRows, $label) use ($countWir) {
                $tCount = $tRows->sum('count_wirausaha');
                return [
                    'label' => $label,
                    'count' => $tCount,
                    'pct'   => $countWir > 0 ? round($tCount / $countWir * 100, 1) : 0.0,
                ];
            })->values()->toArray();

            $chart[]     = [
                'nama_prodi'               => $namaProdi,
                'jenjang'                  => $first['jenjang'],
                'total_alumni'             => $totalAlumni,
                'count_wirausaha'          => $countWir,
                'pct_wirausaha'            => $pctWir,
                'avg_masa_tunggu_wirausaha'=> $avgMasTunggu,
                'tingkat'                  => $tingkatBreakdown,
            ];

            $prodiList[] = $namaProdi;
        }

        return new WirausahaBandingkanDTO(
            chart:     $chart,
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