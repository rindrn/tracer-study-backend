<?php

namespace App\Services\Analytical;

use App\DTOs\Analytical\Pendapatan\PendapatanBandingkanDTO;
use App\DTOs\Analytical\Pendapatan\PendapatanDistribusiDTO;
use App\DTOs\Analytical\Pendapatan\PendapatanDrillDownDTO;
use App\DTOs\Analytical\Pendapatan\PendapatanBarDTO;
use App\Repositories\Analytical\PendapatanRepository;

/**
 * PendapatanService
 *
 * Orkestrasi logika bisnis untuk segmen Pendapatan Lulusan.
 * - Semua logika kalkulasi (pct, target) ada di sini, bukan di Repository/Controller.
 * - TARGET_PCT = standar LAM BAN-PT 3.0 Level Baik (60% ≥ 1,2× UMP).
 *   Kalau standar berubah, edit konstanta ini saja.
 */
class PendapatanService
{
    /**
     * Standar LAM BAN-PT 3.0 Level Baik: 60% alumni ≥ 1,2× UMP.
     * Ditampilkan sebagai garis merah putus-putus di dual-axis chart.
     */
    private const TARGET_PCT = 60.0;

    public function __construct(
        private readonly PendapatanRepository $repo,
    ) {}

    // ──────────────────────────────────────────────────────────────

    public function getBar(array $params): PendapatanBarDTO
    {
        $rows = $this->repo->getGajiPerTahun(
            jenjang:        $params['jenjang']         ?? null,
            jurusan:        $params['jurusan']         ?? null,
            namaProdi:      $params['nama_prodi']      ?? null,
            mingguSnapshot: $params['minggu_snapshot'] ?? null,
        );

        $availableTahun = $rows->pluck('tahun_lulus')
            ->unique()->sort()->values()->toArray();

        return new PendapatanBarDTO(
            rows:           $rows->values()->toArray(),
            availableTahun: $availableTahun,
            filters:        array_filter($params),
        );
    }

    // ──────────────────────────────────────────────────────────────

    public function getDistribusi(array $params): PendapatanDistribusiDTO
    {
        $rows = $this->repo->getProporsiUmpPerTahun(
            jenjang:        $params['jenjang']         ?? null,
            jurusan:        $params['jurusan']         ?? null,
            namaProdi:      $params['nama_prodi']      ?? null,
            mingguSnapshot: $params['minggu_snapshot'] ?? null,
        );

        $availableTahun = $rows->pluck('tahun_lulus')->unique()->sort()->values()->toArray();

        return new PendapatanDistribusiDTO(
            rows:           $rows->values()->toArray(),
            availableTahun: $availableTahun,
            filters:        array_filter($params),
        );
    }

    // ──────────────────────────────────────────────────────────────

    public function getDrillDown(array $params): PendapatanDrillDownDTO
    {
        $page    = (int) ($params['page']     ?? 1);
        $perPage = (int) ($params['per_page'] ?? 15);

        $result = $this->repo->getDetailAlumni(
            segmenUmp:      $params['segmen_ump']      ?? null,
            jenjang:        $params['jenjang']         ?? null,
            jurusan:        $params['jurusan']         ?? null,
            namaProdi:      $params['nama_prodi']      ?? null,
            tahunLulus:     $params['tahun_lulus']     ?? null,
            mingguSnapshot: $params['minggu_snapshot'] ?? null,
            search:         $params['search']          ?? null,
            page:           $page,
            perPage:        $perPage,
        );

        // Label segmen untuk response — human-readable
        $segmenLabel = match ($params['segmen_ump'] ?? null) {
            'above_ump' => '≥ 1,2× UMP',
            'below_ump' => '< 1,2× UMP',
            default     => $params['tahun_lulus'] ?? 'semua',
        };

        return new PendapatanDrillDownDTO(
            data:        $result['data'],
            segmen:      $segmenLabel,
            page:        $result['page'],
            perPage:     $result['per_page'],
            totalOnPage: $result['total_on_page'],
            filters:     array_filter($params, fn($v, $k) =>
                             !in_array($k, ['page', 'per_page', 'search']),
                             ARRAY_FILTER_USE_BOTH),
        );
    }

    // ──────────────────────────────────────────────────────────────

    public function getKelompokBandingkan(array $params): PendapatanBandingkanDTO
    {
        $result = $this->repo->getKelompokGajiPerProdi(
            prodiFilter:    $params['prodi']           ?? [],
            jenjang:        $params['jenjang']         ?? null,
            jurusan:        $params['jurusan']         ?? null,
            tahunLulus:     $params['tahun_lulus']     ?? null,
            mingguSnapshot: $params['minggu_snapshot'] ?? null,
        );

        return new PendapatanBandingkanDTO(
            chart:     $result['chart'],
            table:     $result['table'],
            prodiList: $result['prodi_list'],
            filters:   array_filter($params, fn($v, $k) => $k !== 'prodi', ARRAY_FILTER_USE_BOTH),
        );
    }

    public function getBandingkan(array $params): PendapatanBandingkanDTO
    {
        $result = $this->repo->getUmpPerProdi(
            prodiFilter:    $params['prodi']           ?? [],
            jenjang:        $params['jenjang']         ?? null,
            jurusan:        $params['jurusan']         ?? null,
            tahunLulus:     $params['tahun_lulus']     ?? null,
            mingguSnapshot: $params['minggu_snapshot'] ?? null,
        );

        return new PendapatanBandingkanDTO(
            chart:     $result['chart'],
            table:     $result['table'],
            prodiList: $result['prodi_list'],
            filters:   array_filter($params, fn($v, $k) => $k !== 'prodi', ARRAY_FILTER_USE_BOTH),
        );
    }
}