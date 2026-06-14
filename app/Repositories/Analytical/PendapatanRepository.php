<?php

namespace App\Repositories\Analytical;

use Illuminate\Support\Collection;

/**
 * PendapatanRepository
 *
 * Query ke Cube.js untuk segmen "Pendapatan Lulusan":
 *   1. Avg gaji + % ≥ 1,2× UMP per tahun_lulus  → dual-axis chart
 *   2. Proporsi above/below UMP per tahun_lulus               → grouped bar
 *   3. Detail alumni per segmen UMP atau per tahun_lulus      → drill-down
 *   4. Perbandingan above/below UMP per prodi                 → halaman Bandingkan
 *
 * Pre-agg Cube.js yang dipakai: FactTracerStudy.distribusi_gaji
 * (cover measures: avg/min/max_take_home_pay, count_above_ump,
 *  count_below_ump, count_dengan_data_ump, count_alumni
 *  dimensions: DimProdi.*, DimStatusAlumni.label,
 *  DimAlumni.tahun_lulus, DimWaktu.minggu_snapshot)
 */
class PendapatanRepository extends BaseAnalyticalRepository
{
    // ──────────────────────────────────────────────────────────────
    //  1. DUAL-AXIS — avg gaji + % ≥ 1,2× UMP per tahun
    // ──────────────────────────────────────────────────────────────

    /**
     * Agregat gaji dan flag UMP per tahun_lulus.
     *
     * Pre-agg: FactTracerStudy.distribusi_gaji
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
    ): Collection {
        $filters = $this->buildGlobalFilters(
            jenjang:        $jenjang,
            jurusan:        $jurusan,
            namaProdi:      $namaProdi,
            mingguSnapshot: $mingguSnapshot,
        );

        // Hanya query avg_take_home_pay — count_above_ump memerlukan kolom
        // flag_above_ump yang belum ada di fact_tracer_study (UMP belum di-ETL).
        $raw = $this->cube->load([
            'measures' => [
                'FactTracerStudy.avg_take_home_pay',
                'FactTracerStudy.count_alumni',
            ],
            'dimensions' => [
                'DimAlumni.tahun_lulus',
            ],
            'filters' => $filters,
            'order'   => [['DimAlumni.tahun_lulus', 'asc']],
        ]);

        return $raw->map(function ($r) {
            return [
                'tahun_lulus'      => $r['DimAlumni.tahun_lulus']                  ?? '',
                'avg_gaji'         => (int) round($r['FactTracerStudy.avg_take_home_pay'] ?? 0),
                'total_alumni_ump' => 0,
                'count_above_ump'  => 0,
                'pct_above_ump'    => null,   // null = tidak ada data UMP → line tidak dirender
            ];
        });
    }

    // ──────────────────────────────────────────────────────────────
    //  2. GROUPED BAR — proporsi below/above UMP per tahun lulus
    // ──────────────────────────────────────────────────────────────

    /**
     * Proporsi < 1,2× UMP vs ≥ 1,2× UMP per tahun lulus (tanpa split jenjang).
     *
     * Pre-agg: FactTracerStudy.distribusi_gaji ✅
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
    ): Collection {
        $filters = $this->buildGlobalFilters(
            jenjang:        $jenjang,
            jurusan:        $jurusan,
            namaProdi:      $namaProdi,
            mingguSnapshot: $mingguSnapshot,
        );

        // Data UMP (flag_above_ump) belum ada di OLAP — return koleksi kosong
        // agar FE menampilkan "Belum ada data" daripada 503.
        return collect();
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
    ): array {
        $extra = [];

        // Filter flag_above_ump dari fact table langsung
        if ($segmenUmp === 'above_ump') {
            $extra[] = [
                'member'   => 'FactTracerStudy.flag_above_ump',
                'operator' => 'equals',
                'values'   => ['1'],
            ];
        } elseif ($segmenUmp === 'below_ump') {
            $extra[] = [
                'member'   => 'FactTracerStudy.flag_above_ump',
                'operator' => 'equals',
                'values'   => ['0'],
            ];
        }

        // Hanya alumni yang punya data gaji + UMP ref (flag_above_ump NOT NULL)
        // Cube.js tidak punya operator 'set', pakai notEquals null workaround
        // via filter member pada dimension langsung:
        $extra[] = [
            'member'   => 'FactTracerStudy.flag_above_ump',
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
     * Pre-agg: FactTracerStudy.distribusi_gaji
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
                'FactTracerStudy.count_above_ump',
                'FactTracerStudy.count_below_ump',
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

        $normalized = $raw->map(fn($r) => [
            'nama_prodi'          => $r['DimProdi.nama_prodi']                    ?? '',
            'jenjang'             => $r['DimProdi.jenjang']                       ?? '',
            'jurusan'             => $r['DimProdi.jurusan']                       ?? '',
            'count_above_ump'     => (int) ($r['FactTracerStudy.count_above_ump']     ?? 0),
            'count_below_ump'     => (int) ($r['FactTracerStudy.count_below_ump']     ?? 0),
            'total'               => (int) ($r['FactTracerStudy.count_dengan_data_ump'] ?? 0),
            'avg_gaji'            => (int) round($r['FactTracerStudy.avg_take_home_pay'] ?? 0),
        ]);

        return $this->reshapeUmpPerProdi($normalized, $prodiFilter);
    }

    // ──────────────────────────────────────────────────────────────
    //  5. BANDINGKAN KELOMPOK GAJI PER PRODI
    // ──────────────────────────────────────────────────────────────

    /**
     * Distribusi kelompok gaji (< 5jt, 5-8jt, 8-12jt, > 12jt) per prodi.
     * Membuat 4 Cube.js queries terfilter, satu per rentang gaji.
     *
     * @return array{chart: array, table: array, prodi_list: array<string>}
     */
    public function getKelompokGajiPerProdi(
        array   $prodiFilter    = [],
        ?string $jenjang        = null,
        ?string $jurusan        = null,
        ?string $tahunLulus     = null,
        ?string $mingguSnapshot = null,
    ): array {
        $ranges = [
            ['label' => '< 5 jt',  'gte' => null,       'lt'  => '5000000' ],
            ['label' => '5-8 jt',  'gte' => '5000000',  'lt'  => '8000000' ],
            ['label' => '8-12 jt', 'gte' => '8000000',  'lt'  => '12000000'],
            ['label' => '> 12 jt', 'gte' => '12000000', 'lt'  => null      ],
        ];

        // Kumpulkan count per prodi per rentang
        $perProdi = []; // [ nama_prodi => ['jenjang'=>..., 'jurusan'=>..., 'counts'=>[label=>n]] ]

        foreach ($ranges as $range) {
            $extra = [];
            if (!empty($prodiFilter)) {
                $extra[] = ['member' => 'DimProdi.nama_prodi', 'operator' => 'equals', 'values' => $prodiFilter];
            }
            if ($range['gte'] !== null) {
                $extra[] = ['member' => 'FactTracerStudy.take_home_pay', 'operator' => 'gte', 'values' => [$range['gte']]];
            }
            if ($range['lt'] !== null) {
                $extra[] = ['member' => 'FactTracerStudy.take_home_pay', 'operator' => 'lt',  'values' => [$range['lt']]];
            }

            $filters = $this->buildGlobalFilters(
                jenjang: $jenjang, jurusan: $jurusan, tahunLulus: $tahunLulus,
                mingguSnapshot: $mingguSnapshot, extra: $extra,
            );

            $raw = $this->cube->load([
                'measures'   => ['FactTracerStudy.count_alumni'],
                'dimensions' => ['DimProdi.nama_prodi', 'DimProdi.jenjang', 'DimProdi.jurusan'],
                'filters'    => $filters,
                'order'      => [['DimProdi.nama_prodi', 'asc']],
            ]);

            foreach ($raw as $r) {
                $prodi = $r['DimProdi.nama_prodi'] ?? '';
                if ($prodi === '') continue;
                if (!isset($perProdi[$prodi])) {
                    $perProdi[$prodi] = [
                        'jenjang' => $r['DimProdi.jenjang'] ?? '',
                        'jurusan' => $r['DimProdi.jurusan'] ?? '',
                        'counts'  => [],
                    ];
                }
                $perProdi[$prodi]['counts'][$range['label']] = (int) ($r['FactTracerStudy.count_alumni'] ?? 0);
            }
        }

        // Query total + avg_gaji per prodi (satu query, tanpa filter range)
        $extraTotal = [];
        if (!empty($prodiFilter)) {
            $extraTotal[] = ['member' => 'DimProdi.nama_prodi', 'operator' => 'equals', 'values' => $prodiFilter];
        }
        $filtersTotal = $this->buildGlobalFilters(
            jenjang: $jenjang, jurusan: $jurusan, tahunLulus: $tahunLulus,
            mingguSnapshot: $mingguSnapshot, extra: $extraTotal,
        );
        $rawTotal = $this->cube->load([
            'measures'   => ['FactTracerStudy.count_alumni', 'FactTracerStudy.avg_take_home_pay'],
            'dimensions' => ['DimProdi.nama_prodi', 'DimProdi.jenjang', 'DimProdi.jurusan'],
            'filters'    => $filtersTotal,
            'order'      => [['DimProdi.jurusan', 'asc'], ['DimProdi.nama_prodi', 'asc']],
        ]);

        $rangeLabels = array_column($ranges, 'label');
        $chart       = [];

        foreach ($rawTotal as $r) {
            $prodi = $r['DimProdi.nama_prodi'] ?? '';
            if ($prodi === '') continue;

            $total   = (int) ($r['FactTracerStudy.count_alumni']       ?? 0);
            $avgGaji = (int) round($r['FactTracerStudy.avg_take_home_pay'] ?? 0);
            $counts  = $perProdi[$prodi]['counts'] ?? [];

            $statuses = [];
            foreach ($rangeLabels as $label) {
                $cnt        = $counts[$label] ?? 0;
                $statuses[] = [
                    'label' => $label,
                    'count' => $cnt,
                    'pct'   => $total > 0 ? round($cnt / $total * 100, 1) : 0.0,
                ];
            }

            $chart[] = [
                'nama_prodi' => $prodi,
                'jenjang'    => $perProdi[$prodi]['jenjang'] ?? ($r['DimProdi.jenjang'] ?? ''),
                'jurusan'    => $perProdi[$prodi]['jurusan'] ?? ($r['DimProdi.jurusan'] ?? ''),
                'total'      => $total,
                'avg_gaji'   => $avgGaji,
                'statuses'   => $statuses,
            ];
        }

        return [
            'chart'      => $chart,
            'table'      => $chart,
            'prodi_list' => array_values(array_unique(array_column($chart, 'nama_prodi'))),
        ];
    }

    private function reshapeUmpPerProdi(
        \Illuminate\Support\Collection $raw,
        array $prodiFilter = [],
    ): array {
        $chart     = [];
        $prodiList = [];

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
                    'label' => '≥ 1,2× UMP',
                    'count' => $above,
                    'pct'   => $total > 0 ? round($above / $total * 100, 1) : 0.0,
                ],
                [
                    'label' => '< 1,2× UMP',
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