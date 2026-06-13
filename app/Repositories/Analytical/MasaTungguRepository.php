<?php

namespace App\Repositories\Analytical;

use Illuminate\Support\Collection;

class MasaTungguRepository extends BaseAnalyticalRepository
{
    // ──────────────────────────────────────────────────────────────
    //  1. BAR — avg masa tunggu + pct cepat per prodi × tahun
    // ──────────────────────────────────────────────────────────────

    /**
     * Pre-agg: FactTracerStudy.utama ✅
     *
     * @return Collection<array{nama_prodi, jenjang, jurusan, tahun_lulus,
     *                          count_alumni, count_terserap,
     *                          count_masa_tunggu_cepat, avg_masa_tunggu_bekerja}>
     */
    public function getBarData(
        ?string $jenjang        = null,
        ?string $jurusan        = null,
        ?string $namaProdi      = null,
        ?string $tahunLulus     = null,
        ?string $mingguSnapshot = null,
    ): Collection {
        $filters = $this->buildGlobalFilters(
            jenjang:        $jenjang,
            jurusan:        $jurusan,
            namaProdi:      $namaProdi,
            tahunLulus:     $tahunLulus,
            mingguSnapshot: $mingguSnapshot,
        );

        return $this->cube->load([
            'measures' => [
                'FactTracerStudy.count_alumni',
                'FactTracerStudy.count_terserap',
                'FactTracerStudy.count_masa_tunggu_cepat',
                'FactTracerStudy.avg_masa_tunggu_bekerja',
            ],
            'dimensions' => [
                'DimProdi.jenjang',
                'DimProdi.jurusan',
                'DimProdi.nama_prodi',
                'DimAlumni.tahun_lulus',
            ],
            'filters' => $filters,
            'order'   => [
                ['DimAlumni.tahun_lulus', 'asc'],
                ['DimProdi.nama_prodi',   'asc'],
            ],
        ])->map(fn($r) => [
            'nama_prodi'              => $r['DimProdi.nama_prodi']                    ?? '',
            'jenjang'                 => $r['DimProdi.jenjang']                       ?? '',
            'jurusan'                 => $r['DimProdi.jurusan']                       ?? '',
            'tahun_lulus'             => $r['DimAlumni.tahun_lulus']                  ?? '',
            'count_alumni'            => (int)   ($r['FactTracerStudy.count_alumni']            ?? 0),
            'count_terserap'          => (int)   ($r['FactTracerStudy.count_terserap']          ?? 0),
            'count_masa_tunggu_cepat' => (int)   ($r['FactTracerStudy.count_masa_tunggu_cepat'] ?? 0),
            'avg_masa_tunggu_bekerja' => (float) ($r['FactTracerStudy.avg_masa_tunggu_bekerja'] ?? 0),
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    //  2. DISTRIBUSI — flat rows per prodi × tahun
    // ──────────────────────────────────────────────────────────────

    /**
     * Pre-agg: FactTracerStudy.distribusi_masa_tunggu ✅
     *
     * @return Collection<array{nama_prodi, jenjang, tahun_lulus,
     *                          count_tunggu_0_3_bulan, count_tunggu_3_6_bulan,
     *                          count_tunggu_lebih_6_bulan,
     *                          avg_masa_tunggu_bekerja, min_masa_tunggu_bekerja,
     *                          max_masa_tunggu_bekerja}>
     */
    public function getDistribusiData(
        ?string $jenjang        = null,
        ?string $jurusan        = null,
        ?string $namaProdi      = null,
        ?string $tahunLulus     = null,
        ?string $mingguSnapshot = null,
    ): Collection {
        $filters = $this->buildGlobalFilters(
            jenjang:        $jenjang,
            jurusan:        $jurusan,
            namaProdi:      $namaProdi,
            tahunLulus:     $tahunLulus,
            mingguSnapshot: $mingguSnapshot,
        );

        return $this->cube->load([
            'measures' => [
                'FactTracerStudy.count_tunggu_0_3_bulan',
                'FactTracerStudy.count_tunggu_3_6_bulan',
                'FactTracerStudy.count_tunggu_lebih_6_bulan',
                'FactTracerStudy.avg_masa_tunggu_bekerja',
                'FactTracerStudy.min_masa_tunggu_bekerja',
                'FactTracerStudy.max_masa_tunggu_bekerja',
            ],
            'dimensions' => [
                'DimProdi.jenjang',
                'DimProdi.jurusan',
                'DimProdi.nama_prodi',
                'DimAlumni.tahun_lulus',
            ],
            'filters' => $filters,
            'order'   => [['DimProdi.nama_prodi', 'asc']],
        ])->map(fn($r) => [
            'nama_prodi'                 => $r['DimProdi.nama_prodi']                         ?? '',
            'jenjang'                    => $r['DimProdi.jenjang']                            ?? '',
            'tahun_lulus'                => $r['DimAlumni.tahun_lulus']                       ?? '',
            'count_tunggu_0_3_bulan'     => (int)   ($r['FactTracerStudy.count_tunggu_0_3_bulan']     ?? 0),
            'count_tunggu_3_6_bulan'     => (int)   ($r['FactTracerStudy.count_tunggu_3_6_bulan']     ?? 0),
            'count_tunggu_lebih_6_bulan' => (int)   ($r['FactTracerStudy.count_tunggu_lebih_6_bulan'] ?? 0),
            'avg_masa_tunggu_bekerja'    => (float) ($r['FactTracerStudy.avg_masa_tunggu_bekerja']    ?? 0),
            'min_masa_tunggu_bekerja'    => (int)   ($r['FactTracerStudy.min_masa_tunggu_bekerja']    ?? 0),
            'max_masa_tunggu_bekerja'    => (int)   ($r['FactTracerStudy.max_masa_tunggu_bekerja']    ?? 0),
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    //  3. DRILL-DOWN — alumni individual per rentang masa tunggu
    // ──────────────────────────────────────────────────────────────

    /**
     * TIDAK pakai pre-agg — data individual alumni.
     *
     * @return array{data: array, page: int, per_page: int, total_on_page: int}
     */
    public function getDetailAlumniByRentang(
        string  $rentang,
        ?string $jenjang        = null,
        ?string $jurusan        = null,
        ?string $namaProdi      = null,
        ?string $tahunLulus     = null,
        ?string $mingguSnapshot = null,
        ?string $search         = null,
        int     $page           = 1,
        int     $perPage        = 15,
    ): array {
        $rentangFilters = $this->buildRentangFilters($rentang);

        $filters = $this->buildGlobalFilters(
            jenjang:        $jenjang,
            jurusan:        $jurusan,
            namaProdi:      $namaProdi,
            tahunLulus:     $tahunLulus,
            mingguSnapshot: $mingguSnapshot,
            extra:          $rentangFilters,
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
                'DimProdi.jenjang',
                'DimAlumni.tahun_lulus',
                'FactTracerStudy.masa_tunggu_bekerja',
                'DimStatusAlumni.label',
            ],
            'filters' => $filters,
            'order'   => [['DimAlumni.nama', 'asc']],
            'limit'   => $perPage,
            'offset'  => ($page - 1) * $perPage,
        ]);

        $data = $result->map(fn($r) => [
            'nama'                => $r['DimAlumni.nama']                         ?? '',
            'nim'                 => $r['DimAlumni.nim']                          ?? '',
            'nama_prodi'          => $r['DimProdi.nama_prodi']                    ?? '',
            'jenjang'             => $r['DimProdi.jenjang']                       ?? '',
            'tahun_lulus'         => $r['DimAlumni.tahun_lulus']                  ?? '',
            'masa_tunggu_bekerja' => (int) ($r['FactTracerStudy.masa_tunggu_bekerja'] ?? 0),
            'status'              => $r['DimStatusAlumni.label']                  ?? '',
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
     * pct_cepat = (count_0_3 + count_3_6) / total_bekerja × 100
     * (≤ 6 bulan = "cepat" sesuai standar DIKTI)
     *
     * @param  array<string>  $prodiFilter  Kosong = semua prodi.
     * @return array{data: array, prodi_list: array<string>}
     */
    public function getDistribusiPerProdi(
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
                'FactTracerStudy.count_tunggu_0_3_bulan',
                'FactTracerStudy.count_tunggu_3_6_bulan',
                'FactTracerStudy.count_tunggu_lebih_6_bulan',
                'FactTracerStudy.avg_masa_tunggu_bekerja',
                'FactTracerStudy.min_masa_tunggu_bekerja',
                'FactTracerStudy.max_masa_tunggu_bekerja',
            ],
            'dimensions' => [
                'DimProdi.jenjang',
                'DimProdi.jurusan',
                'DimProdi.nama_prodi',
                'DimAlumni.tahun_lulus',
            ],
            'filters' => $filters,
            'order'   => [['DimProdi.nama_prodi', 'asc']],
        ]);

        $data      = [];
        $prodiList = [];

        foreach ($raw as $r) {
            $count03    = (int)   ($r['FactTracerStudy.count_tunggu_0_3_bulan']     ?? 0);
            $count36    = (int)   ($r['FactTracerStudy.count_tunggu_3_6_bulan']     ?? 0);
            $countMore  = (int)   ($r['FactTracerStudy.count_tunggu_lebih_6_bulan'] ?? 0);
            $totalBekj  = $count03 + $count36 + $countMore;

            // pct_cepat = alumni ≤ 6 bulan / total bekerja × 100
            $pctCepat = $totalBekj > 0
                ? round(($count03 + $count36) / $totalBekj * 100, 1)
                : 0.0;

            $namaProdi = $r['DimProdi.nama_prodi'] ?? '';

            $data[] = [
                'nama_prodi'                 => $namaProdi,
                'jenjang'                    => $r['DimProdi.jenjang']                            ?? '',
                'tahun_lulus'                => $r['DimAlumni.tahun_lulus']                       ?? '',
                'count_tunggu_0_3_bulan'     => $count03,
                'count_tunggu_3_6_bulan'     => $count36,
                'count_tunggu_lebih_6_bulan' => $countMore,
                'avg_masa_tunggu_bekerja'    => (float) ($r['FactTracerStudy.avg_masa_tunggu_bekerja'] ?? 0),
                'min_masa_tunggu_bekerja'    => (int)   ($r['FactTracerStudy.min_masa_tunggu_bekerja'] ?? 0),
                'max_masa_tunggu_bekerja'    => (int)   ($r['FactTracerStudy.max_masa_tunggu_bekerja'] ?? 0),
                'pct_cepat'                  => $pctCepat,
            ];

            $prodiList[] = $namaProdi;
        }

        return [
            'data'       => $data,
            'prodi_list' => array_values(array_unique($prodiList)),
        ];
    }

    // ──────────────────────────────────────────────────────────────
    //  PRIVATE HELPERS
    // ──────────────────────────────────────────────────────────────

    /**
     * Bangun filter Cube.js untuk rentang masa tunggu.
     * Filter status di-restrict hanya Bekerja/Wirausaha (yang punya masa tunggu).
     */
    private function buildRentangFilters(string $rentang): array
    {
        $statusFilter = [
            'member'   => 'DimStatusAlumni.label',
            'operator' => 'equals',
            'values'   => ['Bekerja', 'Wirausaha'],
        ];

        return match ($rentang) {
            '0-3' => [
                $statusFilter,
                ['member' => 'FactTracerStudy.masa_tunggu_bekerja', 'operator' => 'gte', 'values' => ['0']],
                ['member' => 'FactTracerStudy.masa_tunggu_bekerja', 'operator' => 'lt',  'values' => ['3']],
            ],
            '3-6' => [
                $statusFilter,
                ['member' => 'FactTracerStudy.masa_tunggu_bekerja', 'operator' => 'gte', 'values' => ['3']],
                ['member' => 'FactTracerStudy.masa_tunggu_bekerja', 'operator' => 'lte', 'values' => ['6']],
            ],
            '>6' => [
                $statusFilter,
                ['member' => 'FactTracerStudy.masa_tunggu_bekerja', 'operator' => 'gt',  'values' => ['6']],
            ],
            default => [],
        };
    }
}