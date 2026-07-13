<?php

namespace App\Services\Analytical;

use App\DTOs\Analytical\Pendapatan\PendapatanBandingkanDTO;
use App\DTOs\Analytical\Pendapatan\PendapatanDistribusiDTO;
use App\DTOs\Analytical\Pendapatan\PendapatanDrillDownDTO;
use App\DTOs\Analytical\Pendapatan\PendapatanBarDTO;
use App\Repositories\Analytical\PendapatanRepository;
use App\Traits\WithCache;

class PendapatanService
{
    use WithCache;

    private const TARGET_PCT = 60.0;
    private const TTL        = 3600; // 1 jam
    /** Ambang default (dulu hardcode di ETL) -- dipakai kalau FE tidak kirim ambang_ump_multiplier (mis. belum ada LAM/prodi terpilih). */
    private const AMBANG_DEFAULT = 1.2;

    public function __construct(
        private readonly PendapatanRepository $repo,
    ) {}

    private function ambang(array $params): float
    {
        return isset($params['ambang_ump_multiplier']) && $params['ambang_ump_multiplier'] !== ''
            ? (float) $params['ambang_ump_multiplier']
            : self::AMBANG_DEFAULT;
    }

    // ──────────────────────────────────────────────────────────────

    public function getBar(array $params): PendapatanBarDTO
    {
        $key = $this->key('pendapatan:bar', $params);

        $cached = $this->remember($key, function () use ($params) {
            $rows = $this->repo->getGajiPerTahun(
                jenjang:        $params['jenjang']         ?? null,
                jurusan:        $params['jurusan']         ?? null,
                namaProdi:      $params['nama_prodi']      ?? null,
                mingguSnapshot: $params['minggu_snapshot'] ?? null,
                ambangMultiplier: $this->ambang($params),
            );

            return [
                'rows'           => $rows->values()->toArray(),
                'availableTahun' => $rows->pluck('tahun_lulus')->unique()->sort()->values()->toArray(),
            ];
        }, self::TTL);

        return new PendapatanBarDTO(
            rows:           $cached['rows'],
            availableTahun: $cached['availableTahun'],
            filters:        array_filter($params),
        );
    }

    // ──────────────────────────────────────────────────────────────

    public function getDistribusi(array $params): PendapatanDistribusiDTO
    {
        $key = $this->key('pendapatan:distribusi', $params);

        $cached = $this->remember($key, function () use ($params) {
            $rows = $this->repo->getProporsiUmpPerTahun(
                jenjang:        $params['jenjang']         ?? null,
                jurusan:        $params['jurusan']         ?? null,
                namaProdi:      $params['nama_prodi']      ?? null,
                mingguSnapshot: $params['minggu_snapshot'] ?? null,
                ambangMultiplier: $this->ambang($params),
            );

            return [
                'rows'           => $rows->values()->toArray(),
                'availableTahun' => $rows->pluck('tahun_lulus')->unique()->sort()->values()->toArray(),
            ];
        }, self::TTL);

        return new PendapatanDistribusiDTO(
            rows:           $cached['rows'],
            availableTahun: $cached['availableTahun'],
            filters:        array_filter($params),
        );
    }

    // ──────────────────────────────────────────────────────────────
    // DrillDown tidak di-cache
    // ──────────────────────────────────────────────────────────────

    public function getDrillDown(array $params): PendapatanDrillDownDTO
    {
        $page    = (int) ($params['page']     ?? 1);
        $perPage = (int) ($params['per_page'] ?? 15);

        $ambang = $this->ambang($params);

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
            ambangMultiplier: $ambang,
        );

        $ambangLabel = rtrim(rtrim(number_format($ambang, 1, ',', '.'), '0'), ',');
        $segmenLabel = match ($params['segmen_ump'] ?? null) {
            'above_ump' => "≥ {$ambangLabel}× UMP",
            'below_ump' => "< {$ambangLabel}× UMP",
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

    public function getBandingkan(array $params): PendapatanBandingkanDTO
    {
        $key = $this->key('pendapatan:bandingkan', $params);

        $cached = $this->remember($key, function () use ($params) {
            return $this->repo->getUmpPerProdi(
                prodiFilter:    $params['prodi']           ?? [],
                jenjang:        $params['jenjang']         ?? null,
                jurusan:        $params['jurusan']         ?? null,
                tahunLulus:     $params['tahun_lulus']     ?? null,
                mingguSnapshot: $params['minggu_snapshot'] ?? null,
                ambangMultiplier: $this->ambang($params),
            );
        }, self::TTL);

        return new PendapatanBandingkanDTO(
            chart:     $cached['chart'],
            table:     $cached['table'],
            prodiList: $cached['prodi_list'],
            filters:   array_filter($params, fn($v, $k) => $k !== 'prodi', ARRAY_FILTER_USE_BOTH),
        );
    }

    // ──────────────────────────────────────────────────────────────

    private function key(string $prefix, array $params): string
    {
        $relevant = array_diff_key($params, array_flip(['page', 'per_page', 'search']));
        ksort($relevant);
        return $prefix . ':' . md5(json_encode($relevant));
    }
}