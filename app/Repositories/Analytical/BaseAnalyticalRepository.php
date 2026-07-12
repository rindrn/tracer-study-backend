<?php

namespace App\Repositories\Analytical;

use App\Services\CubeJsClient;

abstract class BaseAnalyticalRepository
{
    public function __construct(
        protected readonly CubeJsClient $cube,
    ) {}

    protected function buildGlobalFilters(
        ?string $jenjang        = null,
        ?string $jurusan        = null,
        ?string $namaProdi      = null,
        ?string $tahunLulus     = null,
        ?string $mingguSnapshot = null,
        array   $extra          = [],
    ): array {
        $filters = [];

        if ($jenjang !== null && $jenjang !== '') {
            $filters[] = [
                'member'   => 'DimProdi.jenjang',
                'operator' => 'equals',
                'values'   => [$jenjang],
            ];
        }

        if ($jurusan !== null && $jurusan !== '') {
            $filters[] = [
                'member'   => 'DimProdi.jurusan',
                'operator' => 'equals',
                'values'   => [$jurusan],
            ];
        }

        if ($namaProdi !== null && $namaProdi !== '') {
            $filters[] = [
                'member'   => 'DimProdi.nama_prodi',
                'operator' => 'equals',
                'values'   => [$namaProdi],
            ];
        }

        if ($tahunLulus !== null && $tahunLulus !== '') {
            $filters[] = [
                'member'   => 'DimAlumni.tahun_lulus',
                'operator' => 'equals',
                'values'   => [$tahunLulus],
            ];
        }

        if ($mingguSnapshot !== null && $mingguSnapshot !== '') {
            $filters[] = [
                'member'   => 'DimWaktu.minggu_snapshot',
                'operator' => 'equals',
                'values'   => [$mingguSnapshot],
            ];
        }

        // Merge filter tambahan spesifik endpoint
        return array_merge($filters, $extra);
    }

    /**
     * Helper: bangun filter dari raw array params (dari $request->query()).
     * Cocok dipakai di controller yang langsung forward query params ke repo.
     *
     * Params yang dikenali: jenjang, jurusan, nama_prodi, tahun_lulus, minggu_snapshot.
     * Params lain di-ignore aman.
     */
    protected function buildGlobalFiltersFromArray(array $params, array $extra = []): array
    {
        return $this->buildGlobalFilters(
            jenjang:        $params['jenjang']         ?? null,
            jurusan:        $params['jurusan']         ?? null,
            namaProdi:      $params['nama_prodi']      ?? null,
            tahunLulus:     $params['tahun_lulus']     ?? null,
            mingguSnapshot: $params['minggu_snapshot'] ?? null,
            extra:          $extra,
        );
    }

    /**
     * Helper: ambil nilai tahun yang tersedia untuk dropdown FE.
     * Tidak pakai pre-agg — metadata dimension kecil.
     *
     * @return array<string>
     */
    protected function fetchAvailableYears(): array
    {
        return $this->cube->load([
            'dimensions' => ['DimAlumni.tahun_lulus'],
            'order'      => [['DimAlumni.tahun_lulus', 'desc']],
        ])
        ->pluck('DimAlumni.tahun_lulus')
        ->filter()
        ->unique()
        ->values()
        ->toArray();
    }

    /**
     * Helper: ambil minggu snapshot yang tersedia untuk dropdown FE.
     * Tidak pakai pre-agg — metadata dimension kecil.
     *
     * @return array<string>
     */
    protected function fetchAvailableSnapshots(): array
    {
        return $this->cube->load([
            'dimensions' => [
                'DimWaktu.minggu_snapshot',
                'DimWaktu.tahun_snapshot',
            ],
            'order' => [['DimWaktu.tahun_snapshot', 'desc'], ['DimWaktu.minggu_snapshot', 'desc']],
        ])
        ->map(fn($r) => [
            'minggu_snapshot' => $r['DimWaktu.minggu_snapshot'],
            'tahun_snapshot'  => $r['DimWaktu.tahun_snapshot'],
        ])
        ->unique('minggu_snapshot')
        ->values()
        ->toArray();
    }
}
