<?php

namespace App\Services\Analytical;

use App\DTOs\Analytical\KompetensiGap\KompetensiGapDTO;
use App\DTOs\Analytical\KompetensiGap\KompetensiGapBandingkanDTO;
use App\DTOs\Analytical\KompetensiGap\KompetensiGapDrillDownDTO;
use App\Repositories\Analytical\KompetensiGapRepository;
use Illuminate\Support\Collection;

class KompetensiGapService
{
    public function __construct(
        private readonly KompetensiGapRepository $repo,
    ) {}

    public function getGap(array $params): KompetensiGapDTO
    {
        $raw = $this->repo->getGapData(
            jenjang:        $params['jenjang']         ?? null,
            jurusan:        $params['jurusan']         ?? null,
            namaProdi:      $params['nama_prodi']      ?? null,
            tahunLulus:     $params['tahun_lulus']     ?? null,
            mingguSnapshot: $params['minggu_snapshot'] ?? null,
        );

        $data = $this->joinKategori($raw)->values()->toArray();

        return new KompetensiGapDTO(
            data:    $data,
            filters: $this->activeFilters($params),
        );
    }

    public function getBandingkan(array $params): KompetensiGapBandingkanDTO
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

        // Group by nama_prodi, kemudian per prodi join A+B per kode_field
        $grouped   = $raw->groupBy('nama_prodi');
        $data      = [];
        $prodiList = [];

        foreach ($grouped as $namaProdi => $rows) {
            $first      = $rows->first();
            $indikator  = $this->joinKategori($rows)->values()->toArray();

            $data[] = [
                'nama_prodi' => $namaProdi,
                'jenjang'    => $first['jenjang'],
                'indikator'  => $indikator,
            ];

            $prodiList[] = $namaProdi;
        }

        return new KompetensiGapBandingkanDTO(
            data:      $data,
            prodiList: array_values(array_unique($prodiList)),
            filters:   $this->activeFilters($params),
        );
    }

    public function getDrillDown(array $params): KompetensiGapDrillDownDTO
    {
        $page      = max(1, (int) ($params['page']     ?? 1));
        $perPage   = min(100, max(5, (int) ($params['per_page'] ?? 15)));
        $grupGap = $params['grup_gap'] ?? '';

        $result = $this->repo->getDetailAlumni(
            grupGap:      $grupGap,
            jenjang:        $params['jenjang']         ?? null,
            jurusan:        $params['jurusan']         ?? null,
            namaProdi:      $params['nama_prodi']      ?? null,
            tahunLulus:     $params['tahun_lulus']     ?? null,
            mingguSnapshot: $params['minggu_snapshot'] ?? null,
            search:         $params['search']          ?? null,
            page:           $page,
            perPage:        $perPage,
        );

        return new KompetensiGapDrillDownDTO(
            data:        $result['data'],
            page:        $page,
            perPage:     $perPage,
            totalOnPage: $result['total_on_page'],
            filters:     $this->activeFilters(array_merge($params, ['grup_gap' => $grupGap]),
                ['grup_gap', 'jenjang', 'jurusan', 'nama_prodi', 'tahun_lulus', 'minggu_snapshot']),
        );
    }

    private function joinKategori(Collection $rows): Collection
    {
        // Group by kode_field
        $byKode = $rows->groupBy('grup_gap');

        return $byKode->map(function (Collection $items, string $grupGap) {
            $rowA = $items->firstWhere('kategori', 'Kompetensi_A');
            $rowB = $items->firstWhere('kategori', 'Kompetensi_B');

            // Ambil label dari salah satu (harusnya sama)
            $label = $rowA['label'] ?? $rowB['label'] ?? '';

            $kodeField = $rowA['kode_field']
                ?? $rowB['kode_field']
                ?? '';

            $skorLulus      = isset($rowA) ? round($rowA['avg_skor'], 2) : null;
            $skorDibutuhkan = isset($rowB) ? round($rowB['avg_skor'], 2) : null;

            // gap = B - A (positif = perlu ditingkatkan)
            $gap = (isset($rowA) && isset($rowB))
                ? round($skorDibutuhkan - $skorLulus, 2)
                : null;

            // count_responden: ambil dari A (seharusnya sama dengan B)
            $count = $rowA['count'] ?? $rowB['count'] ?? 0;

            return [
                'kode_field'      => $kodeField,
                'grup_gap'        => $grupGap,
                'label'           => $label,
                'skor_lulus'      => $skorLulus,
                'skor_dibutuhkan' => $skorDibutuhkan,
                'gap'             => $gap,
                'count_responden' => $count,
            ];
        });
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