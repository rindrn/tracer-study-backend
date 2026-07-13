<?php

namespace App\Repositories\Analytical;

use Illuminate\Support\Collection;

/**
 * PendapatanRepository
 *
 * Query ke Cube.js untuk segmen "Pendapatan Lulusan":
 *   1. Avg gaji + % ≥ ambang UMP per tahun_lulus  → dual-axis chart
 *   2. Proporsi above/below UMP per tahun_lulus               → grouped bar
 *   3. Detail alumni per segmen UMP atau per tahun_lulus      → drill-down
 *   4. Perbandingan above/below UMP per prodi                 → halaman Bandingkan
 *
 * AMBANG UMP DINAMIS: setiap method di sini menerima $ambangMultiplier
 * (default 1.2 -- nilai lama yang dulu hardcode di AlumniFactBuilderService).
 * Sumbernya sekarang threshold_configs.param_value milik indikator
 * 'salary_above_ump' per LAM version, di-resolve FE lewat useLamFilter lalu
 * dikirim sebagai query param ambang_ump_multiplier (lihat
 * PendapatanController). "Above/below" TIDAK LAGI dihitung dari
 * fact_tracer_study.flag_above_ump (kolom itu tetap ada, dipakai measure
 * count_above_ump/count_below_ump versi lama yang masih hardcode 1.2x) --
 * dihitung ULANG di query time via dimension FactTracerStudy.salary_ump_multiplier
 * (take_home_pay / nilai_ump, lihat model/cubes/FactTracerStudy.js), supaya
 * ambang bisa berapa pun tanpa perlu ETL ulang atau redeploy Cube.js.
 *
 * Pre-agg Cube.js yang dipakai: FactTracerStudy.distribusi_gaji (untuk
 * total/avg/min/max -- tidak bergantung ambang). Query "above" ambang
 * dinamis TIDAK match pre-agg manapun (measure count_alumni + filter ad hoc
 * pada dimension baru), jadi query langsung ke fact table -- dampak performa
 * dapat diabaikan pada volume data SmartTracer saat ini.
 */
class PendapatanRepository extends BaseAnalyticalRepository
{
    private const AMBANG_DEFAULT = 1.2;

    // ──────────────────────────────────────────────────────────────
    //  Helper: hitung "above ambang" per grup dimensi, ad hoc filter
    // ──────────────────────────────────────────────────────────────

    /**
     * @param  array<string>  $dimensions  Dimension Cube.js untuk group by (mis. DimAlumni.tahun_lulus)
     * @param  array          $baseFilters Filter global (buildGlobalFilters) TANPA filter ambang
     * @return Collection  Baris {..dimensions.., count_above: int} keyed by gabungan nilai dimension (pipe-separated)
     */
    private function countAboveAmbang(array $dimensions, array $baseFilters, float $ambangMultiplier): Collection
    {
        $filters = array_merge($baseFilters, [
            ['member' => 'FactTracerStudy.salary_ump_multiplier', 'operator' => 'set'],
            ['member' => 'FactTracerStudy.salary_ump_multiplier', 'operator' => 'gte', 'values' => [(string) $ambangMultiplier]],
        ]);

        return $this->cube->load([
            'measures'   => ['FactTracerStudy.count_alumni'],
            'dimensions' => $dimensions,
            'filters'    => $filters,
        ])->keyBy(fn ($r) => implode('|', array_map(fn ($d) => $r[$d] ?? '', $dimensions)))
          ->map(fn ($r) => (int) ($r['FactTracerStudy.count_alumni'] ?? 0));
    }

    private static function rowKey(array $values): string
    {
        return implode('|', $values);
    }

    // ──────────────────────────────────────────────────────────────
    //  1. DUAL-AXIS — avg gaji + % ≥ ambang UMP per tahun
    // ──────────────────────────────────────────────────────────────

    /**
     * Agregat gaji dan proporsi di atas ambang UMP per tahun_lulus.
     *
     * @return Collection<array{
     *   tahun_lulus: string,
     *   avg_gaji: float,
     *   min_gaji: float,
     *   max_gaji: float,
     *   total_alumni_ump: int,
     *   count_above_ump: int,
     *   pct_above_ump: float
     * }>
     */
    public function getGajiPerTahun(
        ?string $jenjang        = null,
        ?string $jurusan        = null,
        ?string $namaProdi      = null,
        ?string $mingguSnapshot = null,
        float   $ambangMultiplier = self::AMBANG_DEFAULT,
    ): Collection {
        $filters = $this->buildGlobalFilters(
            jenjang:        $jenjang,
            jurusan:        $jurusan,
            namaProdi:      $namaProdi,
            mingguSnapshot: $mingguSnapshot,
        );

        $raw = $this->cube->load([
            'measures' => [
                'FactTracerStudy.avg_take_home_pay',
                'FactTracerStudy.count_dengan_data_ump',
            ],
            'dimensions' => [
                'DimAlumni.tahun_lulus',
            ],
            'filters' => $filters,
            'order'   => [['DimAlumni.tahun_lulus', 'asc']],
        ]);

        $aboveByTahun = $this->countAboveAmbang(['DimAlumni.tahun_lulus'], $filters, $ambangMultiplier);

        return $raw->map(function ($r) use ($aboveByTahun) {
            $tahun = $r['DimAlumni.tahun_lulus'] ?? '';
            $total = (int) ($r['FactTracerStudy.count_dengan_data_ump'] ?? 0);
            $above = $aboveByTahun->get(self::rowKey([$tahun]), 0);

            return [
                'tahun_lulus'     => $tahun,
                'avg_gaji' => (int) round($r['FactTracerStudy.avg_take_home_pay'] ?? 0),
                'total_alumni_ump'=> $total,
                'count_above_ump' => $above,
                'pct_above_ump'   => $total > 0 ? round($above / $total * 100, 1) : 0.0,
            ];
        });
    }

    // ──────────────────────────────────────────────────────────────
    //  2. GROUPED BAR — proporsi below/above UMP per tahun lulus
    // ──────────────────────────────────────────────────────────────

    /**
     * Proporsi < ambang UMP vs ≥ ambang UMP per tahun lulus (tanpa split jenjang).
     *
     * @return Collection<array{
     *   tahun_lulus: string,
     *   total_alumni_ump: int,
     *   count_below_ump: int,
     *   count_above_ump: int,
     *   pct_below_ump: float,
     *   pct_above_ump: float
     * }>
     */
    public function getProporsiUmpPerTahun(
        ?string $jenjang        = null,
        ?string $jurusan        = null,
        ?string $namaProdi      = null,
        ?string $mingguSnapshot = null,
        float   $ambangMultiplier = self::AMBANG_DEFAULT,
    ): Collection {
        $filters = $this->buildGlobalFilters(
            jenjang:        $jenjang,
            jurusan:        $jurusan,
            namaProdi:      $namaProdi,
            mingguSnapshot: $mingguSnapshot,
        );

        $raw = $this->cube->load([
            'measures'   => ['FactTracerStudy.count_dengan_data_ump'],
            'dimensions' => ['DimAlumni.tahun_lulus'],
            'filters'    => $filters,
            'order'      => [['DimAlumni.tahun_lulus', 'asc']],
        ]);

        $aboveByTahun = $this->countAboveAmbang(['DimAlumni.tahun_lulus'], $filters, $ambangMultiplier);

        return $raw->map(function ($r) use ($aboveByTahun) {
            $tahun = $r['DimAlumni.tahun_lulus'] ?? '';
            $total = (int) ($r['FactTracerStudy.count_dengan_data_ump'] ?? 0);
            $above = $aboveByTahun->get(self::rowKey([$tahun]), 0);
            $below = max(0, $total - $above);

            return [
                'tahun_lulus'     => $tahun,
                'total_alumni_ump'=> $total,
                'count_above_ump' => $above,
                'count_below_ump' => $below,
                'pct_above_ump'   => $total > 0 ? round($above / $total * 100, 1) : 0.0,
                'pct_below_ump'   => $total > 0 ? round($below / $total * 100, 1) : 0.0,
            ];
        });
    }

    // ──────────────────────────────────────────────────────────────
    //  3. DRILL-DOWN — detail alumni per segmen UMP / per tahun
    // ──────────────────────────────────────────────────────────────

    /**
     * List alumni untuk modal drill-down.
     * TIDAK pakai pre-agg — data individual (nama, NIM, gaji).
     *
     * $segmenUmp: 'above_ump' | 'below_ump' | null (semua)
     *
     * @return array{data: array, page: int, per_page: int, total_on_page: int}
     */
    public function getDetailAlumni(
        ?string $segmenUmp      = null,
        ?string $jenjang        = null,
        ?string $jurusan        = null,
        ?string $namaProdi      = null,
        ?string $tahunLulus     = null,
        ?string $mingguSnapshot = null,
        ?string $search         = null,
        int     $page           = 1,
        int     $perPage        = 15,
        float   $ambangMultiplier = self::AMBANG_DEFAULT,
    ): array {
        $extra = [];

        // Filter FactTracerStudy.salary_ump_multiplier (dinamis) -- BUKAN
        // flag_above_ump lagi (itu 1.2x hardcode dari ETL).
        if ($segmenUmp === 'above_ump') {
            $extra[] = ['member' => 'FactTracerStudy.salary_ump_multiplier', 'operator' => 'gte', 'values' => [(string) $ambangMultiplier]];
        } elseif ($segmenUmp === 'below_ump') {
            $extra[] = ['member' => 'FactTracerStudy.salary_ump_multiplier', 'operator' => 'lt', 'values' => [(string) $ambangMultiplier]];
        }

        // Hanya alumni yang punya data gaji + UMP ref
        $extra[] = [
            'member'   => 'FactTracerStudy.salary_ump_multiplier',
            'operator' => 'set',
        ];

        if ($tahunLulus !== null && $tahunLulus !== '') {
            $extra[] = [
                'member'   => 'DimAlumni.tahun_lulus',
                'operator' => 'equals',
                'values'   => [$tahunLulus],
            ];
        }

        $filters = $this->buildGlobalFilters(
            jenjang:        $jenjang,
            jurusan:        $jurusan,
            namaProdi:      $namaProdi,
            mingguSnapshot: $mingguSnapshot,
            extra:          $extra,
        );

        if ($search !== null && $search !== '') {
            $filters[] = [
                'member'   => 'DimAlumni.nama',
                'operator' => 'contains',
                'values'   => [$search],
            ];
        }

        $result = $this->cube->load([
            'measures'   => ['FactTracerStudy.count_alumni'],
            'dimensions' => [
                'DimAlumni.nama',
                'DimAlumni.nim',
                'DimProdi.nama_prodi',
                'DimAlumni.tahun_lulus',
                'DimPerusahaan.company_name',
            ],
            'filters' => $filters,
            'order'   => [['DimAlumni.nama', 'asc']],
            'limit'   => $perPage,
            'offset'  => ($page - 1) * $perPage,
        ]);

        $data = $result->map(fn($r) => [
            'nama'          => $r['DimAlumni.nama']              ?? '',
            'nim'           => $r['DimAlumni.nim']               ?? '',
            'nama_prodi'    => $r['DimProdi.nama_prodi']         ?? '',
            'tahun_lulus'   => $r['DimAlumni.tahun_lulus']       ?? '',
            'perusahaan'    => $r['DimPerusahaan.company_name']  ?? '-',
        ])->toArray();

        return [
            'data'          => $data,
            'page'          => $page,
            'per_page'      => $perPage,
            'total_on_page' => count($data),
        ];
    }

    // ──────────────────────────────────────────────────────────────
    //  4. BANDINGKAN PER PRODI
    // ──────────────────────────────────────────────────────────────

    /**
     * Above/below UMP per prodi untuk halaman Bandingkan.
     *
     * @param  array<string> $prodiFilter  Kosong = semua prodi
     * @return array{chart: array, table: array, prodi_list: array<string>}
     */
    public function getUmpPerProdi(
        array   $prodiFilter    = [],
        ?string $jenjang        = null,
        ?string $jurusan        = null,
        ?string $tahunLulus     = null,
        ?string $mingguSnapshot = null,
        float   $ambangMultiplier = self::AMBANG_DEFAULT,
    ): array {
        $extra = [];

        if (!empty($prodiFilter)) {
            $extra[] = [
                'member'   => 'DimProdi.nama_prodi',
                'operator' => 'equals',
                'values'   => $prodiFilter,
            ];
        }

        $filters = $this->buildGlobalFilters(
            jenjang:        $jenjang,
            jurusan:        $jurusan,
            tahunLulus:     $tahunLulus,
            mingguSnapshot: $mingguSnapshot,
            extra:          $extra,
        );

        $raw = $this->cube->load([
            'measures' => [
                'FactTracerStudy.count_dengan_data_ump',
                'FactTracerStudy.avg_take_home_pay',
            ],
            'dimensions' => [
                'DimProdi.nama_prodi',
                'DimProdi.jenjang',
                'DimProdi.jurusan',
            ],
            'filters' => $filters,
            'order'   => [
                ['DimProdi.jurusan',    'asc'],
                ['DimProdi.nama_prodi', 'asc'],
            ],
        ]);

        $aboveByProdi = $this->countAboveAmbang(
            ['DimProdi.nama_prodi', 'DimProdi.jenjang', 'DimProdi.jurusan'],
            $filters,
            $ambangMultiplier,
        );

        $normalized = $raw->map(function ($r) use ($aboveByProdi) {
            $namaProdi = $r['DimProdi.nama_prodi'] ?? '';
            $jenjang   = $r['DimProdi.jenjang']    ?? '';
            $jurusan   = $r['DimProdi.jurusan']    ?? '';
            $total     = (int) ($r['FactTracerStudy.count_dengan_data_ump'] ?? 0);
            $above     = $aboveByProdi->get(self::rowKey([$namaProdi, $jenjang, $jurusan]), 0);

            return [
                'nama_prodi'          => $namaProdi,
                'jenjang'             => $jenjang,
                'jurusan'             => $jurusan,
                'count_above_ump'     => $above,
                'count_below_ump'     => max(0, $total - $above),
                'total'               => $total,
                'avg_gaji'            => (int) round($r['FactTracerStudy.avg_take_home_pay'] ?? 0),
            ];
        });

        return $this->reshapeUmpPerProdi($normalized, $prodiFilter, $ambangMultiplier);
    }

    private function reshapeUmpPerProdi(
        \Illuminate\Support\Collection $raw,
        array $prodiFilter = [],
        float $ambangMultiplier = self::AMBANG_DEFAULT,
    ): array {
        $chart     = [];
        $prodiList = [];
        $labelAbove = '≥ ' . rtrim(rtrim(number_format($ambangMultiplier, 1, ',', '.'), '0'), ',') . '× UMP';
        $labelBelow = '< ' . rtrim(rtrim(number_format($ambangMultiplier, 1, ',', '.'), '0'), ',') . '× UMP';

        foreach ($raw as $r) {
            $namaProdi = $r['nama_prodi'];
            $jenjang   = $r['jenjang'];
            $jurusan   = $r['jurusan'];
            $total     = $r['total'];
            $above     = $r['count_above_ump'];
            $below     = $r['count_below_ump'];
            $avgGaji   = $r['avg_gaji'];

            // Skip baris kosong dari Cube.js
            if ($namaProdi === '' && $jenjang === '') {
                continue;
            }

            $statuses = [
                [
                    'label' => $labelAbove,
                    'count' => $above,
                    'pct'   => $total > 0 ? round($above / $total * 100, 1) : 0.0,
                ],
                [
                    'label' => $labelBelow,
                    'count' => $below,
                    'pct'   => $total > 0 ? round($below / $total * 100, 1) : 0.0,
                ],
            ];

            $prodiList[] = $namaProdi;
            $chart[] = [
                'nama_prodi' => $namaProdi,
                'jenjang'    => $jenjang,
                'jurusan'    => $jurusan,
                'total'      => $total,
                'avg_gaji'   => $avgGaji,
                'statuses'   => $statuses,
            ];
        }

        usort($chart, fn($a, $b) =>
            [$a['jenjang'], $a['jurusan'], $a['nama_prodi']]
            <=>
            [$b['jenjang'], $b['jurusan'], $b['nama_prodi']]
        );

        return [
            'chart'      => $chart,
            'table'      => $chart,
            'prodi_list' => array_values(array_unique($prodiList)),
        ];
    }
}
