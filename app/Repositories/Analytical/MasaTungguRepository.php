<?php

namespace App\Repositories\Analytical;

use Illuminate\Support\Collection;

class MasaTungguRepository extends BaseAnalyticalRepository
{
    /** Ambang lama (standar DIKTI, dulu hardcode di FactTracerStudy.js's count_masa_tunggu_cepat). */
    private const BATAS_CEPAT_DEFAULT = 6.0;

    // ──────────────────────────────────────────────────────────────
    //  1. BAR — avg masa tunggu + pct cepat per prodi × tahun
    // ──────────────────────────────────────────────────────────────

    /**
     * Pre-agg: FactTracerStudy.utama (untuk count_alumni/count_terserap/avg --
     * "cepat" TIDAK LAGI dari measure count_masa_tunggu_cepat yang batasnya
     * dibakukan 6 bulan di Cube.js; sekarang query ad hoc terpisah dengan
     * $batasCepatBulan dinamis, lihat cepatCountsByGroup()).
     *
     * @return Collection<array{nama_prodi, jenjang, jurusan, tahun_lulus,
     *                          count_alumni, count_terserap,
     *                          count_masa_tunggu_cepat, avg_masa_tunggu_bekerja}>
     */
    public function getBarData(
        ?string $jenjang        = null,
        ?string $jurusan        = null,
        ?array  $idProdiIn      = null,
        ?string $namaProdi      = null,
        ?string $tahunLulus     = null,
        ?string $mingguSnapshot = null,
        float   $batasCepatBulan = self::BATAS_CEPAT_DEFAULT,
    ): Collection {
        $filters = $this->buildGlobalFilters(
            jenjang:        $jenjang,
            jurusan:        $jurusan,
            idProdiIn:      $idProdiIn,
            namaProdi:      $namaProdi,
            tahunLulus:     $tahunLulus,
            mingguSnapshot: $mingguSnapshot,
        );

        $dims = ['DimProdi.jenjang', 'DimProdi.jurusan', 'DimProdi.nama_prodi', 'DimAlumni.tahun_lulus'];

        $raw = $this->cube->load([
            'measures' => [
                'FactTracerStudy.count_alumni',
                'FactTracerStudy.count_terserap',
                'FactTracerStudy.avg_masa_tunggu_bekerja',
                'FactTracerStudy.median_masa_tunggu_bekerja',
            ],
            'dimensions' => $dims,
            'filters' => $filters,
            'order'   => [
                ['DimAlumni.tahun_lulus', 'asc'],
                ['DimProdi.nama_prodi',   'asc'],
            ],
        ]);

        $cepatByGroup = $this->cepatCountsByGroup($dims, $filters, $batasCepatBulan);

        return $raw->map(fn($r) => [
            'nama_prodi'                 => $r['DimProdi.nama_prodi']                       ?? '',
            'jenjang'                    => $r['DimProdi.jenjang']                          ?? '',
            'jurusan'                    => $r['DimProdi.jurusan']                          ?? '',
            'tahun_lulus'                => $r['DimAlumni.tahun_lulus']                     ?? '',
            'count_alumni'               => (int)   ($r['FactTracerStudy.count_alumni']               ?? 0),
            'count_terserap'             => (int)   ($r['FactTracerStudy.count_terserap']             ?? 0),
            'count_masa_tunggu_cepat'    => $cepatByGroup->get(
                self::rowKey($dims, $r), 0
            ),
            'avg_masa_tunggu_bekerja'    => (float) ($r['FactTracerStudy.avg_masa_tunggu_bekerja']    ?? 0),
            'median_masa_tunggu_bekerja' => (float) ($r['FactTracerStudy.median_masa_tunggu_bekerja'] ?? 0),
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    //  FR-026 — Pola pencarian kerja: rata-rata bulan sebelum lulus mulai
    //  mencari kerja + rata-rata masa tunggu (durasi), per prodi × tahun.
    //  Konteks penjelas pola masa tunggu — BUKAN chart besar berdiri sendiri.
    // ──────────────────────────────────────────────────────────────

    /**
     * avg_bulan_sebelum_lulus (f302) & avg_bulan_sesudah_lulus (f303): kedua
     * sisi sekarang data ASLI — f303 dipetakan ke role bulan_sesudah_lulus
     * via migration `2026_08_06_000001_map_f303_to_bulan_sesudah_lulus`,
     * dan AlumniFactBuilderService sudah diubah untuk resolve keduanya
     * (dulu bulan_sesudah_lulus hardcode null). Alumni hanya mengisi SATU
     * dari f302/f303 (show_if f301), jadi count_mulai_sebelum/count_mulai_sesudah
     * di sini saling eksklusif per alumni.
     *
     * @return Collection<array{nama_prodi, jenjang, jurusan, tahun_lulus,
     *                          count_alumni, avg_bulan_sebelum_lulus,
     *                          avg_bulan_sesudah_lulus, count_mulai_sebelum,
     *                          count_mulai_sesudah, avg_masa_tunggu_bekerja}>
     */
    public function getPolaPencarianKerja(
        ?string $jenjang        = null,
        ?string $jurusan        = null,
        ?array  $idProdiIn      = null,
        ?string $namaProdi      = null,
        ?string $tahunLulus     = null,
        ?string $mingguSnapshot = null,
    ): Collection {
        $filters = $this->buildGlobalFilters(
            jenjang:        $jenjang,
            jurusan:        $jurusan,
            idProdiIn:      $idProdiIn,
            namaProdi:      $namaProdi,
            tahunLulus:     $tahunLulus,
            mingguSnapshot: $mingguSnapshot,
            extra: [[
                // Hanya alumni bekerja di perusahaan — sejalan dengan denominator
                // masa tunggu di getBarData().
                'member'   => 'FactTracerStudy.perusahaan_sk',
                'operator' => 'set',
            ]],
        );

        $dims = ['DimProdi.jenjang', 'DimProdi.jurusan', 'DimProdi.nama_prodi', 'DimAlumni.tahun_lulus'];

        $raw = $this->cube->load([
            'measures' => [
                'FactTracerStudy.count_alumni',
                'FactTracerStudy.avg_bulan_sebelum_lulus',
                'FactTracerStudy.avg_bulan_sesudah_lulus',
                'FactTracerStudy.avg_masa_tunggu_bekerja',
            ],
            'dimensions' => $dims,
            'filters'    => $filters,
            'order'      => [
                ['DimAlumni.tahun_lulus', 'asc'],
                ['DimProdi.nama_prodi',   'asc'],
            ],
        ]);

        $sebelumCounts = $this->countByGroupWhereSet($dims, $filters, 'FactTracerStudy.bulan_sebelum_lulus');
        $sesudahCounts = $this->countByGroupWhereSet($dims, $filters, 'FactTracerStudy.bulan_sesudah_lulus');

        return $raw->map(function ($r) use ($dims, $sebelumCounts, $sesudahCounts) {
            $key = self::rowKey($dims, $r);

            return [
                'nama_prodi'              => $r['DimProdi.nama_prodi']  ?? '',
                'jenjang'                 => $r['DimProdi.jenjang']     ?? '',
                'jurusan'                 => $r['DimProdi.jurusan']     ?? '',
                'tahun_lulus'             => $r['DimAlumni.tahun_lulus'] ?? '',
                'count_alumni'            => (int)   ($r['FactTracerStudy.count_alumni']            ?? 0),
                'avg_bulan_sebelum_lulus' => (float) ($r['FactTracerStudy.avg_bulan_sebelum_lulus']  ?? 0),
                'avg_bulan_sesudah_lulus' => (float) ($r['FactTracerStudy.avg_bulan_sesudah_lulus']  ?? 0),
                'count_mulai_sebelum'     => $sebelumCounts->get($key, 0),
                'count_mulai_sesudah'     => $sesudahCounts->get($key, 0),
                'avg_masa_tunggu_bekerja' => (float) ($r['FactTracerStudy.avg_masa_tunggu_bekerja']  ?? 0),
            ];
        });
    }

    /**
     * Jumlah alumni per grup dimensi yang punya nilai pada $member (operator
     * 'set') — dipakai untuk menghitung berapa alumni mulai cari kerja
     * sebelum vs sesudah lulus (mengisi salah satu dari f302/f303).
     */
    private function countByGroupWhereSet(array $dimensions, array $baseFilters, string $member): Collection
    {
        $filters = array_merge($baseFilters, [
            ['member' => $member, 'operator' => 'set'],
        ]);

        return $this->cube->load([
            'measures'   => ['FactTracerStudy.count_alumni'],
            'dimensions' => $dimensions,
            'filters'    => $filters,
        ])->keyBy(fn ($r) => self::rowKey($dimensions, $r))
          ->map(fn ($r) => (int) ($r['FactTracerStudy.count_alumni'] ?? 0));
    }

    // ──────────────────────────────────────────────────────────────
    //  FR-027 — Tren median masa tunggu per tahun (basis prediksi)
    // ──────────────────────────────────────────────────────────────

    /**
     * Median masa tunggu per tahun_lulus (semua prodi digabung, atau
     * ter-filter jenjang/jurusan/prodi) — dipakai sebagai data historis
     * untuk proyeksi tren periode berikutnya (regresi linier sederhana,
     * lihat MasaTungguService::getPrediksi()).
     *
     * @return Collection<array{tahun_lulus:string, count_alumni:int, median_masa_tunggu_bekerja:float}>
     */
    public function getMedianTrendPerTahun(
        ?string $jenjang        = null,
        ?string $jurusan        = null,
        ?array  $idProdiIn      = null,
        ?string $namaProdi      = null,
        ?string $mingguSnapshot = null,
    ): Collection {
        $filters = $this->buildGlobalFilters(
            jenjang:        $jenjang,
            jurusan:        $jurusan,
            idProdiIn:      $idProdiIn,
            namaProdi:      $namaProdi,
            mingguSnapshot: $mingguSnapshot,
            extra: [[
                'member'   => 'FactTracerStudy.perusahaan_sk',
                'operator' => 'set',
            ]],
        );

        return $this->cube->load([
            'measures'   => ['FactTracerStudy.count_alumni', 'FactTracerStudy.median_masa_tunggu_bekerja'],
            'dimensions' => ['DimAlumni.tahun_lulus'],
            'filters'    => $filters,
            'order'      => [['DimAlumni.tahun_lulus', 'asc']],
        ])->map(fn($r) => [
            'tahun_lulus'                => $r['DimAlumni.tahun_lulus'] ?? '',
            'count_alumni'               => (int)   ($r['FactTracerStudy.count_alumni']               ?? 0),
            'median_masa_tunggu_bekerja' => (float) ($r['FactTracerStudy.median_masa_tunggu_bekerja'] ?? 0),
        ])->filter(fn($r) => $r['tahun_lulus'] !== '' && $r['count_alumni'] > 0)
          ->values();
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
        ?array  $idProdiIn      = null,
        ?string $namaProdi      = null,
        ?string $tahunLulus     = null,
        ?string $mingguSnapshot = null,
    ): Collection {
        $filters = $this->buildGlobalFilters(
            jenjang:        $jenjang,
            jurusan:        $jurusan,
            idProdiIn:      $idProdiIn,
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
        ?array  $idProdiIn      = null,
        ?string $namaProdi      = null,
        ?string $tahunLulus     = null,
        ?string $mingguSnapshot = null,
        ?string $search         = null,
        int     $page           = 1,
        int     $perPage        = 15,
        float   $batasCepatBulan = self::BATAS_CEPAT_DEFAULT,
    ): array {
        $rentangFilters = $this->buildRentangFilters($rentang, $batasCepatBulan);

        $filters = $this->buildGlobalFilters(
            jenjang:        $jenjang,
            jurusan:        $jurusan,
            idProdiIn:      $idProdiIn,
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
     * pct_cepat = alumni dengan masa_tunggu_bekerja ≤ $batasCepatBulan / total_bekerja × 100.
     * Distribusi rentang 0-3/3-6/>6bulan TETAP granularitas tetap (bucket
     * histogram, bukan definisi lulus/tidak) -- HANYA pct_cepat yang
     * dinamis mengikuti $batasCepatBulan, dihitung terpisah dari bucket-nya
     * (tidak lagi sekadar count03+count36, supaya benar walau ambang jatuh
     * di tengah bucket 3-6, mis. ambang 4 bulan).
     *
     * @param  array<string>  $prodiFilter  Kosong = semua prodi.
     * @return array{data: array, prodi_list: array<string>}
     */
    public function getDistribusiPerProdi(
        array   $prodiFilter    = [],
        ?string $jenjang        = null,
        ?string $jurusan        = null,
        ?array  $idProdiIn      = null,
        ?string $tahunLulus     = null,
        ?string $mingguSnapshot = null,
        float   $batasCepatBulan = self::BATAS_CEPAT_DEFAULT,
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
            idProdiIn:      $idProdiIn,
            tahunLulus:     $tahunLulus,
            mingguSnapshot: $mingguSnapshot,
            extra:          $extra,
        );

        $dims = ['DimProdi.jenjang', 'DimProdi.jurusan', 'DimProdi.nama_prodi', 'DimAlumni.tahun_lulus'];

        $raw = $this->cube->load([
            'measures' => [
                'FactTracerStudy.count_tunggu_0_3_bulan',
                'FactTracerStudy.count_tunggu_3_6_bulan',
                'FactTracerStudy.count_tunggu_lebih_6_bulan',
                'FactTracerStudy.avg_masa_tunggu_bekerja',
                'FactTracerStudy.min_masa_tunggu_bekerja',
                'FactTracerStudy.max_masa_tunggu_bekerja',
            ],
            'dimensions' => $dims,
            'filters' => $filters,
            'order'   => [['DimProdi.nama_prodi', 'asc']],
        ]);

        $cepatByGroup = $this->cepatCountsByGroup($dims, $filters, $batasCepatBulan);

        $data      = [];
        $prodiList = [];

        foreach ($raw as $r) {
            $count03    = (int) ($r['FactTracerStudy.count_tunggu_0_3_bulan']     ?? 0);
            $count36    = (int) ($r['FactTracerStudy.count_tunggu_3_6_bulan']     ?? 0);
            $countMore  = (int) ($r['FactTracerStudy.count_tunggu_lebih_6_bulan'] ?? 0);
            $totalBekj  = $count03 + $count36 + $countMore;
            $cepat      = $cepatByGroup->get(self::rowKey($dims, $r), 0);

            $pctCepat = $totalBekj > 0 ? round($cepat / $totalBekj * 100, 1) : 0.0;

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

    /** Filter status: hanya Bekerja/Wiraswasta (yang punya nilai masa_tunggu_bekerja). */
    private function statusEligibleFilter(): array
    {
        // IKU 2 Kemendikbud: Terserap = bekerja + wirausaha + studi lanjut
        //   option_code "1" = Bekerja (full time / part time)
        //   option_code "3" = Wiraswasta
        //   option_code "6" = Melanjutkan pendidikan sambil bekerja
        //   option_code "7" = Melanjutkan pendidikan sambil wiraswasta
        return [
            'member'   => 'DimStatusAlumni.label',
            'operator' => 'equals',
            'values'   => ['Bekerja (full time / part time)', 'Wiraswasta', 'Melanjutkan pendidikan sambil bekerja', 'Melanjutkan pendidikan sambil wiraswasta'],
        ];
    }

    /**
     * "Cepat" per grup dimensi, ambang DINAMIS -- pengganti measure
     * count_masa_tunggu_cepat (dulu hardcode ≤6 bulan di Cube.js, sekarang
     * ambang datang dari threshold_configs.param_value indikator
     * employment_time per LAM version, dikirim FE via useLamFilter). Ad hoc
     * filter, bukan measure -- Cube.js measure terkompilasi tidak bisa
     * menerima parameter di query time, jadi jalur ini pakai teknik yang
     * SAMA dengan buildRentangFilters (filter langsung ke
     * masa_tunggu_bekerja), bukan measure bernama.
     *
     * @param  array<string>  $dimensions   Dimension utk group by (harus sama dgn query utama, supaya rowKey cocok)
     * @return Collection  keyed by rowKey($dimensions, $row) -> int count
     */
    private function cepatCountsByGroup(array $dimensions, array $baseFilters, float $batasBulan): Collection
    {
        $filters = array_merge($baseFilters, [
            $this->statusEligibleFilter(),
            ['member' => 'FactTracerStudy.masa_tunggu_bekerja', 'operator' => 'set'],
            // gte 0 (BUKAN gt 0) -- masa tunggu persis 0 bulan (sudah bekerja
            // saat/sebelum lulus) tetap termasuk "cepat", bahkan yang paling
            // cepat dari semua. Dulu dikecualikan, menyebabkan angka "cepat"
            // lebih rendah dari seharusnya dibanding bucket histogram 0-3 bulan.
            ['member' => 'FactTracerStudy.masa_tunggu_bekerja', 'operator' => 'gte', 'values' => ['0']],
            ['member' => 'FactTracerStudy.masa_tunggu_bekerja', 'operator' => 'lte', 'values' => [(string) $batasBulan]],
        ]);

        return $this->cube->load([
            'measures'   => ['FactTracerStudy.count_alumni'],
            'dimensions' => $dimensions,
            'filters'    => $filters,
        ])->keyBy(fn ($r) => self::rowKey($dimensions, $r))
          ->map(fn ($r) => (int) ($r['FactTracerStudy.count_alumni'] ?? 0));
    }

    /** Kunci gabungan nilai dimension (pipe-separated) -- dipakai merge dua cube->load() berbeda by group. */
    private static function rowKey(array $dimensions, array $row): string
    {
        return implode('|', array_map(fn ($d) => $row[$d] ?? '', $dimensions));
    }

    /**
     * Bangun filter Cube.js untuk rentang masa tunggu (histogram 0-3/3-6/>6,
     * granularitas TETAP -- tidak berubah oleh ambang cepat dinamis).
     *
     * 'cepat' TERPISAH dari histogram di atas -- ini bukan salah satu bucket
     * tetap, tapi persis filter yang dipakai cepatCountsByGroup() (gt 0, lte
     * $batasCepatBulan). Drill-down dari bar "% Lulusan ≤ N Bulan" HARUS
     * pakai 'cepat' (bukan '0-3' yang di-hardcode) -- kalau tidak, daftar
     * alumni yang muncul cuma mencakup bucket 0-3 padahal angka di bar
     * mencakup 0-3 DAN 3-6 (kalau ambang 6 bulan). Bug ini pernah membuat
     * jumlah baris drill-down (40) tidak cocok dengan angka di bar (54).
     */
    private function buildRentangFilters(string $rentang, float $batasCepatBulan = self::BATAS_CEPAT_DEFAULT): array
    {
        $statusFilter = $this->statusEligibleFilter();

        $notNullFilter = [
            'member'   => 'FactTracerStudy.masa_tunggu_bekerja',
            'operator' => 'set',   // Cube.js: "set" = IS NOT NULL
        ];

        return match ($rentang) {
            'cepat' => [
                $statusFilter,
                $notNullFilter,
                // gte 0 -- lihat catatan identik di cepatCountsByGroup().
                ['member' => 'FactTracerStudy.masa_tunggu_bekerja', 'operator' => 'gte', 'values' => ['0']],
                ['member' => 'FactTracerStudy.masa_tunggu_bekerja', 'operator' => 'lte', 'values' => [(string) $batasCepatBulan]],
            ],
            '0-3' => [
                $statusFilter,
                $notNullFilter,
                ['member' => 'FactTracerStudy.masa_tunggu_bekerja', 'operator' => 'gte', 'values' => ['0']],
                ['member' => 'FactTracerStudy.masa_tunggu_bekerja', 'operator' => 'lt',  'values' => ['3']],
            ],
            '3-6' => [
                $statusFilter,
                $notNullFilter,
                ['member' => 'FactTracerStudy.masa_tunggu_bekerja', 'operator' => 'gte', 'values' => ['3']],
                ['member' => 'FactTracerStudy.masa_tunggu_bekerja', 'operator' => 'lte', 'values' => ['6']],
            ],
            '>6' => [
                $statusFilter,
                $notNullFilter,
                ['member' => 'FactTracerStudy.masa_tunggu_bekerja', 'operator' => 'gt',  'values' => ['6']],
            ],
            default => [],
        };
    }
}