<?php

namespace App\Services\Analytical;

use App\DTOs\Analytical\Kompetensi\KompetensiGapDTO;
use App\DTOs\Analytical\Kompetensi\KompetensiGapBandingkanDTO;
use App\Repositories\Analytical\KompetensiRepository;
use Illuminate\Support\Collection;

class KompetensiService
{
    public function __construct(
        private readonly KompetensiRepository $repo,
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

    private function joinKategori(Collection $rows): Collection
    {
        // Group by kode_field
        $byKode = $rows->groupBy('kode_field');

        return $byKode->map(function (Collection $items, string $kodeField) {
            $rowA = $items->firstWhere('kategori', 'Kompetensi_A');
            $rowB = $items->firstWhere('kategori', 'Kompetensi_B');

            // Ambil label dari salah satu (harusnya sama)
            $label = $rowA['label'] ?? $rowB['label'] ?? '';

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