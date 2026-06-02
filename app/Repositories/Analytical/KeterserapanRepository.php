<?php

namespace App\Repositories\Analytical;

use Illuminate\Support\Collection;

/**
 * KeterserapanRepository
 *
 * Query ke Cube.js untuk segmen "Tingkat Keterserapan Lulusan":
 *   1. Distribusi status per tahun lulus   → bar stacked 100%
 *   2. Distribusi status snapshot terkini  → pie chart
 *   3. Detail alumni per status dan per tahun_lulus → drill-down list (raw, no pre-agg)
 *   4. Perbandingan keterserapan per prodi → halaman Bandingkan
 *
 * Pre-agg Cube.js yang dipakai: FactTracerStudy.utama
 * (sudah cover dimensi: DimProdi.*, DimStatusAlumni.label,
 *  DimAlumni.tahun_lulus, DimWaktu.minggu_snapshot)
 *
 * Taruh di: app/Repositories/Analytical/KeterserapanRepository.php
 */
class KeterserapanRepository extends BaseAnalyticalRepository
{
    // ──────────────────────────────────────────────────────────────
    //  1. BAR STACKED — distribusi status per tahun lulus
    // ──────────────────────────────────────────────────────────────

    /**
     * Count alumni group by (tahun_lulus × status) untuk bar stacked 100%.
     *
     * Pre-agg: FactTracerStudy.utama ✅
     *
     * @return Collection<array{tahun_lulus:string, status:string, count:int}>
     */
    public function getDistribusiStatusPerTahun(
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
            // tahun_lulus sengaja tidak difilter di sini
            // karena tahun_lulus adalah sumbu X-nya
        );

        return $this->cube->load([
            'measures'   => ['FactTracerStudy.count_alumni'],
            'dimensions' => [
                'DimAlumni.tahun_lulus',
                'DimStatusAlumni.label',
            ],
            'filters' => $filters,
            'order'   => [
                ['DimAlumni.tahun_lulus',   'asc'],
                ['DimStatusAlumni.label',   'asc'],
            ],
        ])->map(fn($r) => [
            'tahun_lulus' => $r['DimAlumni.tahun_lulus']       ?? '',
            'status'      => $r['DimStatusAlumni.label']        ?? '',
            'count'       => (int) ($r['FactTracerStudy.count_alumni'] ?? 0),
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    //  2. PIE — distribusi status snapshot terkini
    // ──────────────────────────────────────────────────────────────

    /**
     * Count alumni group by status saja (tidak per tahun).
     * Biasanya dipanggil dengan filter minggu_snapshot tertentu.
     *
     * Pre-agg: FactTracerStudy.utama ✅
     *
     * @return Collection<array{status:string, count:int}>
     */
    public function getDistribusiStatusSnapshot(
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
            'measures'   => ['FactTracerStudy.count_alumni'],
            'dimensions' => ['DimStatusAlumni.label'],
            'filters'    => $filters,
            'order'      => [['FactTracerStudy.count_alumni', 'desc']],
        ])->map(fn($r) => [
            'status' => $r['DimStatusAlumni.label']        ?? '',
            'count'  => (int) ($r['FactTracerStudy.count_alumni'] ?? 0),
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    //  3. DRILL-DOWN — detail alumni per status (raw, no pre-agg)
    // ──────────────────────────────────────────────────────────────

    /**
     * List alumni per status untuk modal drill-down.
     *
     * TIDAK pakai pre-agg — data individual (nama, NIM) bukan agregat.
     * Query langsung ke Cube.js tanpa Redis cache.
     *
     * @return array{data: array, page: int, per_page: int, total_on_page: int}
     */
    public function getDetailAlumniByStatus(
        string  $statusLabel,
        ?string $jenjang        = null,
        ?string $jurusan        = null,
        ?string $namaProdi      = null,
        ?string $tahunLulus     = null,
        ?string $mingguSnapshot = null,
        ?string $search         = null,  // search by nama / NIM
        int     $page           = 1,
        int     $perPage        = 15,
    ): array {
        $filters = $this->buildGlobalFilters(
            jenjang:        $jenjang,
            jurusan:        $jurusan,
            namaProdi:      $namaProdi,
            tahunLulus:     $tahunLulus,
            mingguSnapshot: $mingguSnapshot,
            extra: [
                [
                    'member'   => 'DimStatusAlumni.label',
                    'operator' => 'equals',
                    'values'   => [$statusLabel],
                ],
            ],
        );

        // Search by nama alumni (contains)
        if ($search !== null && $search !== '') {
            $filters[] = [
                'member'   => 'DimAlumni.nama',
                'operator' => 'contains',
                'values'   => [$search],
            ];
        }

        $result = $this->cube->load([
            // FactTracerStudy.count_alumni sebagai anchor join.
            // Tanpa measure/dimension dari fact table, Cube.js tidak tahu
            // join path antara DimAlumni, DimProdi, dan DimStatusAlumni.
            // count_alumni di-include tapi tidak dipakai di response FE.
            'measures'   => ['FactTracerStudy.count_alumni'],
            'dimensions' => [
                'DimAlumni.nama',
                'DimAlumni.nim',
                'DimProdi.nama_prodi',
                'DimProdi.jenjang',
                'DimAlumni.tahun_lulus',
            ],
            'filters' => $filters,
            'order'   => [['DimAlumni.nama', 'asc']],
            'limit'   => $perPage,
            'offset'  => ($page - 1) * $perPage,
        ]);

        $data = $result->map(fn($r) => [
            'nama'        => $r['DimAlumni.nama']       ?? '',
            'nim'         => $r['DimAlumni.nim']        ?? '',
            'nama_prodi'  => $r['DimProdi.nama_prodi']  ?? '',
            'jenjang'     => $r['DimProdi.jenjang']     ?? '',
            'tahun_lulus' => $r['DimAlumni.tahun_lulus'] ?? '',
        ])->toArray();

        return [
            'data'          => $data,
            'page'          => $page,
            'per_page'      => $perPage,
            'total_on_page' => count($data),
        ];
    }

    // ──────────────────────────────────────────────────────────────
    //  4. PERBANDINGAN PER PRODI — halaman Bandingkan
    // ──────────────────────────────────────────────────────────────

    /**
     * Distribusi status per prodi (untuk halaman Bandingkan Prodi).
     * Satu query menghasilkan data untuk bar stacked + tabel ringkasan.
     *
     * Pre-agg: FactTracerStudy.utama ✅
     * @param  array<string>  $prodiFilter  Daftar nama_prodi yang dipilih chip.
     *                                      Kosong = semua prodi.
     * @return array{chart: array, table: array, prodi_list: array<string>}
     */
    public function getDistribusiStatusPerProdi(
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

        // ✅ Sama persis dengan query pie — hanya tambah DimProdi.*
        //    dan DimAlumni.tahun_lulus sebagai breakdown per tahun.
        //    TIDAK ada groupBy PHP manual — biarkan Cube.js yang aggregate.
        $raw = $this->cube->load([
            'measures'   => ['FactTracerStudy.count_alumni'],
            'dimensions' => [
                'DimProdi.nama_prodi',      // ← wajib ada agar nama_prodi muncul
                'DimProdi.jenjang',
                'DimProdi.jurusan',
                'DimStatusAlumni.label',    // ← wajib ada agar label status muncul
                'DimAlumni.tahun_lulus',    // ← untuk breakdown per_tahun
                'DimWaktu.minggu_snapshot',
            ],
            'filters' => $filters,
            'order'   => [
                ['DimProdi.jurusan',      'asc'],
                ['DimProdi.nama_prodi',   'asc'],
                ['DimAlumni.tahun_lulus', 'asc'],
                ['DimStatusAlumni.label', 'asc'],
            ],
        ]);

        $normalized = $raw->map(fn($r) => [
        'nama_prodi'     => $r['DimProdi.nama_prodi']              ?? '',
        'jenjang'        => $r['DimProdi.jenjang']                 ?? '',
        'jurusan'        => $r['DimProdi.jurusan']                 ?? '',
        'status'         => $r['DimStatusAlumni.label']            ?? '',
        'tahun_lulus'    => $r['DimAlumni.tahun_lulus']            ?? '',
        'minggu_snapshot'=> $r['DimWaktu.minggu_snapshot']         ?? '',
        'count'          => (int) ($r['FactTracerStudy.count_alumni'] ?? 0),
        ]);

        return $this->reshapePerProdi($normalized, $prodiFilter);
    }

    // ──────────────────────────────────────────────────────────────
    //  5. DRILL-DOWN — detail alumni per tahun lulus (klik bar)
    // ──────────────────────────────────────────────────────────────

    /**
     * List alumni per tahun lulus untuk modal drill-down bar chart.
     *
     * $statusLabel null   → semua status (atau exclude Belum Bekerja jika $excludeStatus diisi)
     * $statusLabel string → filter status spesifik (label DW)
     * $excludeStatus      → dipakai saat FE kirim "terserap" (exclude Belum Bekerja)
     *
     * TIDAK pakai pre-agg — data individual.
     */
    public function getDetailAlumniByTahun(
        string  $tahunLulus,
        ?string $statusLabel    = null,
        ?string $excludeStatus  = 'Belum Bekerja',  // aktif hanya jika $statusLabel null & FE kirim 'terserap'
        ?string $jenjang        = null,
        ?string $jurusan        = null,
        ?string $namaProdi      = null,
        ?string $mingguSnapshot = null,
        ?string $search         = null,
        int     $page           = 1,
        int     $perPage        = 15,
    ): array {
        $extra = [
            [
                'member'   => 'DimAlumni.tahun_lulus',
                'operator' => 'equals',
                'values'   => [$tahunLulus],
            ],
        ];

        if ($statusLabel !== null && $statusLabel !== '') {
            // Filter status spesifik
            $extra[] = [
                'member'   => 'DimStatusAlumni.label',
                'operator' => 'equals',
                'values'   => [$statusLabel],
            ];
        } elseif ($excludeStatus !== null && $excludeStatus !== '') {
            // Shorthand 'terserap': exclude status tidak terserap
            $extra[] = [
                'member'   => 'DimStatusAlumni.label',
                'operator' => 'notEquals',
                'values'   => [$excludeStatus],
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
                'DimProdi.jenjang',
                'DimAlumni.tahun_lulus',
                'DimStatusAlumni.label',  // tampil di kolom tabel modal
            ],
            'filters' => $filters,
            'order'   => [['DimAlumni.nama', 'asc']],
            'limit'   => $perPage,
            'offset'  => ($page - 1) * $perPage,
        ]);

        $data = $result->map(fn($r) => [
            'nama'        => $r['DimAlumni.nama']        ?? '',
            'nim'         => $r['DimAlumni.nim']         ?? '',
            'nama_prodi'  => $r['DimProdi.nama_prodi']   ?? '',
            'jenjang'     => $r['DimProdi.jenjang']      ?? '',
            'tahun_lulus' => $r['DimAlumni.tahun_lulus'] ?? '',
            'status'      => $r['DimStatusAlumni.label'] ?? '',
        ])->toArray();

        return [
            'data'          => $data,
            'page'          => $page,
            'per_page'      => $perPage,
            'total_on_page' => count($data),
        ];
    }

    // ──────────────────────────────────────────────────────────────
    //  METADATA HELPERS (untuk available_tahun di response bar)
    // ──────────────────────────────────────────────────────────────

    /**
     * Tahun lulus yang tersedia untuk dropdown filter.
     * Tidak pakai pre-agg.
     */
    public function getAvailableTahunLulus(): array
    {
        return $this->fetchAvailableYears();
    }

    /**
     * Minggu snapshot yang tersedia untuk dropdown filter.
     * Tidak pakai pre-agg.
     */
    public function getAvailableSnapshots(): array
    {
        return $this->fetchAvailableSnapshots();
    }

    // ──────────────────────────────────────────────────────────────
    //  PRIVATE
    // ──────────────────────────────────────────────────────────────

    /**
     * Reshape baris flat Cube.js → struktur chart + table per prodi.
     *
     * Setiap baris dari Cube.js sudah merupakan kombinasi unik:
     *   nama_prodi × status_label × tahun_lulus
     * Tinggal di-group secara PHP untuk struktur nested.
     */
    private function reshapePerProdi(
        \Illuminate\Support\Collection $raw,
        array $prodiFilter = [],
    ): array {
        $normalizedFilter = collect($prodiFilter)
            ->map(fn($s) => mb_strtolower(trim($s)))
            ->toArray();

        $grouped = $raw->groupBy(function ($r) {
            return "{$r['jenjang']}||{$r['jurusan']}||{$r['nama_prodi']}";
        });

        $chart = [];
        $prodiList = [];

        foreach ($grouped as $groupKey => $rows) {
            $first     = $rows->first();
            $jenjang   = $first['jenjang'];
            $jurusan   = $first['jurusan'];
            $namaProdi = $first['nama_prodi'];

            $displayName = $namaProdi !== '' ? $namaProdi : "{$jenjang} {$jurusan}";

            // filter prodi[] — sama seperti sebelumnya
            if (!empty($normalizedFilter)) {
                $displayLower = mb_strtolower($displayName);
                $jurusanLower = mb_strtolower($jurusan);

                $match = false;
                foreach ($normalizedFilter as $pf) {
                    if (
                        $pf === $displayLower
                        || str_contains($displayLower, $pf)
                        || str_contains($jurusanLower, preg_replace('/^(d\d+|s\d+)\s+/i', '', $pf))
                    ) {
                        $match = true;
                        break;
                    }
                }
                if (!$match) continue;
            }

            $prodiList[] = $displayName;
            $total       = $rows->sum('count'); // ✅ langsung pakai key 'count'

            // statuses (semua tahun digabung)
            $statuses = $rows
                ->groupBy('status') // ✅ bukan 'DimStatusAlumni.label'
                ->map(function ($sRows, $label) use ($total) {
                    $count = $sRows->sum('count');
                    return [
                        'label' => $label,
                        'count' => $count,
                        'pct'   => $total > 0 ? round($count / $total * 100, 1) : 0.0,
                    ];
                })
                ->sortBy('label')
                ->values()
                ->toArray();

            // per_tahun
            $perTahun = $rows
                ->groupBy('tahun_lulus') // ✅ bukan 'DimAlumni.tahun_lulus'
                ->map(function ($tahunRows, $tahun) {
                    $tahunTotal = $tahunRows->sum('count');
                    $tahunStatus = $tahunRows
                        ->groupBy('status')
                        ->map(function ($sRows, $label) use ($tahunTotal) {
                            $c = $sRows->sum('count');
                            return [
                                'label' => $label,
                                'count' => $c,
                                'pct'   => $tahunTotal > 0 ? round($c / $tahunTotal * 100, 1) : 0.0,
                            ];
                        })
                        ->sortBy('label')
                        ->values()
                        ->toArray();

                    return [
                        'tahun_lulus' => (string) $tahun,
                        'total'       => $tahunTotal,
                        'statuses'    => $tahunStatus,
                    ];
                })
                ->sortBy('tahun_lulus')
                ->values()
                ->toArray();

            $chart[] = [
                'nama_prodi' => $displayName,
                'jenjang'    => $jenjang,
                'jurusan'    => $jurusan,
                'total'      => $total,
                'statuses'   => $statuses,
                'per_tahun'  => $perTahun,
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