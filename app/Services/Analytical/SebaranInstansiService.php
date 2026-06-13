<?php

namespace App\Services\Analytical;

use App\DTOs\Analytical\SebaranInstansi\SebaranInstansiJenisDTO;
use App\DTOs\Analytical\SebaranInstansi\SebaranInstansiTingkatDTO;
use App\DTOs\Analytical\SebaranInstansi\SebaranInstansiBandingkanDTO;
use App\DTOs\Analytical\SebaranInstansi\SebaranInstansiLokasiDTO;
use App\DTOs\Analytical\SebaranInstansi\SebaranInstansiDrillDownDTO;
use App\Repositories\Analytical\SebaranInstansiRepository;

class SebaranInstansiService
{
    public function __construct(
        private readonly SebaranInstansiRepository $repo,
    ) {}

    public function getJenis(array $params): SebaranInstansiJenisDTO
    {
        $raw = $this->repo->getJenisData(
            jenjang:        $params['jenjang']         ?? null,
            jurusan:        $params['jurusan']         ?? null,
            namaProdi:      $params['nama_prodi']      ?? null,
            tahunLulus:     $params['tahun_lulus']     ?? null,
            mingguSnapshot: $params['minggu_snapshot'] ?? null,
        );

        $total = $raw->sum('count');

        $data = $raw->map(fn($r) => [
            'jenis' => $r['jenis'],
            'count' => $r['count'],
            'pct'   => $total > 0 ? round($r['count'] / $total * 100, 1) : 0.0,
        ])->values()->toArray();

        return new SebaranInstansiJenisDTO(
            data:    $data,
            total:   $total,
            filters: $this->activeFilters($params),
        );
    }


    public function getTingkat(array $params): SebaranInstansiTingkatDTO
    {
        $perProdiRaw = $this->repo->getTingkatData(
            jenjang:        $params['jenjang']         ?? null,
            jurusan:        $params['jurusan']         ?? null,
            namaProdi:      $params['nama_prodi']      ?? null,
            tahunLulus:     $params['tahun_lulus']     ?? null,
            mingguSnapshot: $params['minggu_snapshot'] ?? null,
        );

        $perTahunRaw = $this->repo->getTingkatPerTahun(
            jenjang:        $params['jenjang']         ?? null,
            jurusan:        $params['jurusan']         ?? null,
            namaProdi:      $params['nama_prodi']      ?? null,
            tahunLulus:     $params['tahun_lulus']     ?? null,
            mingguSnapshot: $params['minggu_snapshot'] ?? null,
        );

        // Reshape perProdi → nested tingkat[] per prodi
        $grouped = $perProdiRaw->groupBy('nama_prodi');
        $data = [];

        foreach ($grouped as $namaProdi => $rows) {
            $first       = $rows->first();
            $totalProdi  = $rows->sum('count');

            $tingkat = $rows->map(fn($r) => [
                'label' => $r['label_tingkat'],
                'count' => $r['count'],
                'pct'   => $totalProdi > 0 ? round($r['count'] / $totalProdi * 100, 1) : 0.0,
            ])->values()->toArray();

            $data[] = [
                'nama_prodi' => $namaProdi,
                'jenjang'    => $first['jenjang'],
                'tingkat'    => $tingkat,
            ];
        }

        // Reshape perTahun → grouped_bar (lokal/nasional/internasional per tahun)
        $groupedBar = [];
        $tahunGroups = $perTahunRaw->groupBy('tahun_lulus');

        foreach ($tahunGroups as $tahun => $rows) {
            $totalTahun    = $rows->sum('count');
            $countLokal    = $rows->firstWhere('label_tingkat', 'Lokal')['count']         ?? 0;
            $countNasional = $rows->firstWhere('label_tingkat', 'Nasional')['count']      ?? 0;
            $countInter    = $rows->firstWhere('label_tingkat', 'Internasional')['count'] ?? 0;

            $groupedBar[] = [
                'tahun_lulus'        => $tahun,
                'lokal'              => $countLokal,
                'lokal_pct'          => $totalTahun > 0 ? round($countLokal    / $totalTahun * 100, 1) : 0.0,
                'nasional'           => $countNasional,
                'nasional_pct'       => $totalTahun > 0 ? round($countNasional / $totalTahun * 100, 1) : 0.0,
                'internasional'      => $countInter,
                'internasional_pct'  => $totalTahun > 0 ? round($countInter    / $totalTahun * 100, 1) : 0.0,
            ];
        }

        return new SebaranInstansiTingkatDTO(
            data:       $data,
            groupedBar: $groupedBar,
            filters:    $this->activeFilters($params),
        );
    }

    public function getBandingkan(array $params): SebaranInstansiBandingkanDTO
    {
        $prodiFilter = $params['prodi'] ?? [];
        if (is_string($prodiFilter)) {
            $prodiFilter = [$prodiFilter];
        }

        $jenisRaw   = $this->repo->getBandingkanJenis(
            prodiFilter:    $prodiFilter,
            jenjang:        $params['jenjang']         ?? null,
            jurusan:        $params['jurusan']         ?? null,
            tahunLulus:     $params['tahun_lulus']     ?? null,
            mingguSnapshot: $params['minggu_snapshot'] ?? null,
        );

        $tingkatRaw = $this->repo->getBandingkanTingkat(
            prodiFilter:    $prodiFilter,
            jenjang:        $params['jenjang']         ?? null,
            jurusan:        $params['jurusan']         ?? null,
            tahunLulus:     $params['tahun_lulus']     ?? null,
            mingguSnapshot: $params['minggu_snapshot'] ?? null,
        );

        // Index by nama_prodi
        $jenisGrouped   = $jenisRaw->groupBy('nama_prodi');
        $tingkatGrouped = $tingkatRaw->groupBy('nama_prodi');

        $allProdi  = $jenisGrouped->keys()->merge($tingkatGrouped->keys())->unique()->sort()->values();
        $data      = [];
        $prodiList = [];

        foreach ($allProdi as $namaProdi) {
            $jenisRows   = $jenisGrouped->get($namaProdi, collect());
            $tingkatRows = $tingkatGrouped->get($namaProdi, collect());

            $first       = $jenisRows->first() ?? $tingkatRows->first();
            $totalJenis  = $jenisRows->sum('count');
            $totalTingkat= $tingkatRows->sum('count');
            $total       = max($totalJenis, $totalTingkat); // sama sumber, ambil max

            $jenisList = $jenisRows->map(fn($r) => [
                'label' => $r['label_jenis'],
                'count' => $r['count'],
                'pct'   => $total > 0 ? round($r['count'] / $total * 100, 1) : 0.0,
            ])->values()->toArray();

            $tingkatList = $tingkatRows->map(fn($r) => [
                'label' => $r['label_tingkat'],
                'count' => $r['count'],
                'pct'   => $total > 0 ? round($r['count'] / $total * 100, 1) : 0.0,
            ])->values()->toArray();

            $data[] = [
                'nama_prodi' => $namaProdi,
                'jenjang'    => $first['jenjang'] ?? '',
                'total'      => $total,
                'jenis'      => $jenisList,
                'tingkat'    => $tingkatList,
            ];

            $prodiList[] = $namaProdi;
        }

        return new SebaranInstansiBandingkanDTO(
            data:      $data,
            prodiList: $prodiList,
            filters:   $this->activeFilters($params),
        );
    }

    public function getLokasi(array $params): SebaranInstansiLokasiDTO
    {
        $limit = min(50, max(5, (int) ($params['limit'] ?? 15)));

        $topKota = $this->repo->getTopKota(
            jenjang:        $params['jenjang']         ?? null,
            jurusan:        $params['jurusan']         ?? null,
            namaProdi:      $params['nama_prodi']      ?? null,
            tahunLulus:     $params['tahun_lulus']     ?? null,
            mingguSnapshot: $params['minggu_snapshot'] ?? null,
            limit:          $limit,
        );

        $topProvinsi = $this->repo->getTopProvinsi(
            jenjang:        $params['jenjang']         ?? null,
            jurusan:        $params['jurusan']         ?? null,
            namaProdi:      $params['nama_prodi']      ?? null,
            tahunLulus:     $params['tahun_lulus']     ?? null,
            mingguSnapshot: $params['minggu_snapshot'] ?? null,
            limit:          $limit,
        );

        return new SebaranInstansiLokasiDTO(
            topKota:     $topKota->values()->toArray(),
            topProvinsi: $topProvinsi->values()->toArray(),
            filters:     $this->activeFilters($params),
        );
    }

    public function getDrillDown(array $params): SebaranInstansiDrillDownDTO
    {
        $page    = max(1, (int) ($params['page']     ?? 1));
        $perPage = min(100, max(5, (int) ($params['per_page'] ?? 15)));

        $jenisInstansi   = $params['jenis_instansi']   ?? null;
        $tingkatInstansi = $params['tingkat_instansi'] ?? null;

        $result = $this->repo->getDetailAlumni(
            jenisInstansi:   $jenisInstansi !== '' ? $jenisInstansi : null,
            tingkatInstansi: $tingkatInstansi !== '' ? $tingkatInstansi : null,
            jenjang:         $params['jenjang']         ?? null,
            namaProdi:       $params['nama_prodi']      ?? null,
            tahunLulus:      $params['tahun_lulus']     ?? null,
            mingguSnapshot:  $params['minggu_snapshot'] ?? null,
            search:          $params['search']          ?? null,
            page:            $page,
            perPage:         $perPage,
        );

        return new SebaranInstansiDrillDownDTO(
            data:            $result['data'],
            jenisInstansi:   $jenisInstansi ?: null,
            tingkatInstansi: $tingkatInstansi ?: null,
            page:            $page,
            perPage:         $perPage,
            totalOnPage:     $result['total_on_page'],
            filters:         $this->activeFilters($params, [
                'jenjang', 'nama_prodi', 'tahun_lulus', 'minggu_snapshot',
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