<?php

namespace App\Repositories\Analytical;

use Illuminate\Support\Collection;

class SebaranInstansiRepository extends BaseAnalyticalRepository
{
    private const FILTER_BEKERJA = [
        'member'   => 'FactTracerStudy.status_alumni_sk',
        'operator' => 'equals',
        'values'   => ['1'],
    ];

    // ──────────────────────────────────────────────────────────────
    //  1. JENIS — distribusi jenis instansi
    // ──────────────────────────────────────────────────────────────

    /**
     *
     * @return Collection<array{jenis, count}>
     */
    public function getJenisData(
        ?string $jenjang        = null,
        ?string $jurusan        = null,
        ?string $namaProdi      = null,
        ?string $tahunLulus     = null,
        ?string $mingguSnapshot = null,
    ): Collection {
        $filters = array_merge(
            $this->buildGlobalFilters(
                jenjang:        $jenjang,
                jurusan:        $jurusan,
                namaProdi:      $namaProdi,
                tahunLulus:     $tahunLulus,
                mingguSnapshot: $mingguSnapshot,
            ),
            [self::FILTER_BEKERJA],
        );

        return $this->cube->load([
            'measures'   => ['FactTracerStudy.count_alumni'],
            'dimensions' => ['DimPerusahaan.label_jenis_perusahaan'],
            'filters'    => $filters,
            'order'      => [['FactTracerStudy.count_alumni', 'desc']],
        ])->map(fn($r) => [
            'jenis' => $r['DimPerusahaan.label_jenis_perusahaan'] ?? '',
            'count' => (int) ($r['FactTracerStudy.count_alumni'] ?? 0),
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    //  2. TINGKAT — distribusi tingkat instansi per prodi
    // ──────────────────────────────────────────────────────────────

    /**
     *
     * @return Collection<array{nama_prodi, jenjang, jurusan, label_tingkat, count}>
     */
    public function getTingkatData(
        ?string $jenjang        = null,
        ?string $jurusan        = null,
        ?string $namaProdi      = null,
        ?string $tahunLulus     = null,
        ?string $mingguSnapshot = null,
    ): Collection {
        $filters = array_merge(
            $this->buildGlobalFilters(
                jenjang:        $jenjang,
                jurusan:        $jurusan,
                namaProdi:      $namaProdi,
                tahunLulus:     $tahunLulus,
                mingguSnapshot: $mingguSnapshot,
            ),
            [self::FILTER_BEKERJA],
        );

        return $this->cube->load([
            'measures'   => ['FactTracerStudy.count_alumni'],
            'dimensions' => [
                'DimPerusahaan.label_tingkat_instansi',
                'DimProdi.jenjang',
                'DimProdi.jurusan',
                'DimProdi.nama_prodi',
            ],
            'filters' => $filters,
            'order'   => [
                ['DimProdi.nama_prodi',         'asc'],
                ['FactTracerStudy.count_alumni', 'desc'],
            ],
        ])->map(fn($r) => [
            'nama_prodi'    => $r['DimProdi.nama_prodi']                    ?? '',
            'jenjang'       => $r['DimProdi.jenjang']                       ?? '',
            'jurusan'       => $r['DimProdi.jurusan']                       ?? '',
            'label_tingkat' => $r['DimPerusahaan.label_tingkat_instansi']   ?? '',
            'count'         => (int) ($r['FactTracerStudy.count_alumni']    ?? 0),
        ]);
    }

    /**
     *
     * @return Collection<array{tahun_lulus, label_tingkat, count}>
     */
    public function getTingkatPerTahun(
        ?string $jenjang        = null,
        ?string $jurusan        = null,
        ?string $namaProdi      = null,
        ?string $tahunLulus     = null,
        ?string $mingguSnapshot = null,
    ): Collection {
        $filters = array_merge(
            $this->buildGlobalFilters(
                jenjang:        $jenjang,
                jurusan:        $jurusan,
                namaProdi:      $namaProdi,
                tahunLulus:     $tahunLulus,
                mingguSnapshot: $mingguSnapshot,
            ),
            [self::FILTER_BEKERJA],
        );

        return $this->cube->load([
            'measures'   => ['FactTracerStudy.count_alumni'],
            'dimensions' => [
                'DimPerusahaan.label_tingkat_instansi',
                'DimAlumni.tahun_lulus',
            ],
            'filters' => $filters,
            'order'   => [
                ['DimAlumni.tahun_lulus',        'asc'],
                ['FactTracerStudy.count_alumni',  'desc'],
            ],
        ])->map(fn($r) => [
            'tahun_lulus'   => $r['DimAlumni.tahun_lulus']                  ?? '',
            'label_tingkat' => $r['DimPerusahaan.label_tingkat_instansi']   ?? '',
            'count'         => (int) ($r['FactTracerStudy.count_alumni']    ?? 0),
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    //  3a. BANDINGKAN — jenis instansi per prodi
    // ──────────────────────────────────────────────────────────────

    /**
     *
     * @return Collection<array{nama_prodi, jenjang, jurusan, label_jenis, count}>
     */
    public function getBandingkanJenis(
        array   $prodiFilter    = [],
        ?string $jenjang        = null,
        ?string $jurusan        = null,
        ?string $tahunLulus     = null,
        ?string $mingguSnapshot = null,
    ): Collection {
        $extra = $this->buildProdiFilter($prodiFilter);
        $extra[] = self::FILTER_BEKERJA;

        $filters = $this->buildGlobalFilters(
            jenjang:        $jenjang,
            jurusan:        $jurusan,
            tahunLulus:     $tahunLulus,
            mingguSnapshot: $mingguSnapshot,
            extra:          $extra,
        );

        return $this->cube->load([
            'measures'   => ['FactTracerStudy.count_alumni'],
            'dimensions' => [
                'DimPerusahaan.label_jenis_perusahaan',
                'DimProdi.jenjang',
                'DimProdi.jurusan',
                'DimProdi.nama_prodi',
            ],
            'filters' => $filters,
            'order'   => [
                ['DimProdi.nama_prodi',         'asc'],
                ['FactTracerStudy.count_alumni', 'desc'],
            ],
        ])->map(fn($r) => [
            'nama_prodi' => $r['DimProdi.nama_prodi']                    ?? '',
            'jenjang'    => $r['DimProdi.jenjang']                       ?? '',
            'jurusan'    => $r['DimProdi.jurusan']                       ?? '',
            'label_jenis'=> $r['DimPerusahaan.label_jenis_perusahaan']   ?? '',
            'count'      => (int) ($r['FactTracerStudy.count_alumni']    ?? 0),
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    //  3b. BANDINGKAN — tingkat instansi per prodi
    // ──────────────────────────────────────────────────────────────

    /**
     * @return Collection<array{nama_prodi, jenjang, jurusan, label_tingkat, count}>
     */
    public function getBandingkanTingkat(
        array   $prodiFilter    = [],
        ?string $jenjang        = null,
        ?string $jurusan        = null,
        ?string $tahunLulus     = null,
        ?string $mingguSnapshot = null,
    ): Collection {
        $extra = $this->buildProdiFilter($prodiFilter);
        $extra[] = self::FILTER_BEKERJA;

        $filters = $this->buildGlobalFilters(
            jenjang:        $jenjang,
            jurusan:        $jurusan,
            tahunLulus:     $tahunLulus,
            mingguSnapshot: $mingguSnapshot,
            extra:          $extra,
        );

        return $this->cube->load([
            'measures'   => ['FactTracerStudy.count_alumni'],
            'dimensions' => [
                'DimPerusahaan.label_tingkat_instansi',
                'DimProdi.jenjang',
                'DimProdi.jurusan',
                'DimProdi.nama_prodi',
            ],
            'filters' => $filters,
            'order'   => [
                ['DimProdi.nama_prodi',         'asc'],
                ['FactTracerStudy.count_alumni', 'desc'],
            ],
        ])->map(fn($r) => [
            'nama_prodi'    => $r['DimProdi.nama_prodi']                   ?? '',
            'jenjang'       => $r['DimProdi.jenjang']                      ?? '',
            'jurusan'       => $r['DimProdi.jurusan']                      ?? '',
            'label_tingkat' => $r['DimPerusahaan.label_tingkat_instansi']  ?? '',
            'count'         => (int) ($r['FactTracerStudy.count_alumni']   ?? 0),
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    //  4. LOKASI — top kota dan provinsi
    // ──────────────────────────────────────────────────────────────

    /**
     *
     * @return Collection<array{nama_kota, nama_provinsi, count}>
     */
    public function getTopKota(
        ?string $jenjang        = null,
        ?string $jurusan        = null,
        ?string $namaProdi      = null,
        ?string $tahunLulus     = null,
        ?string $mingguSnapshot = null,
        int     $limit          = 15,
    ): Collection {
        $filters = array_merge(
            $this->buildGlobalFilters(
                jenjang:        $jenjang,
                jurusan:        $jurusan,
                namaProdi:      $namaProdi,
                tahunLulus:     $tahunLulus,
                mingguSnapshot: $mingguSnapshot,
            ),
            [self::FILTER_BEKERJA],
        );

        return $this->cube->load([
            'measures'   => ['FactTracerStudy.count_alumni'],
            'dimensions' => [
                'DimPerusahaan.nama_kota',
                'DimPerusahaan.nama_provinsi',
            ],
            'filters' => $filters,
            'order'   => [['FactTracerStudy.count_alumni', 'desc']],
            'limit'   => $limit,
        ])->map(fn($r) => [
            'nama_kota'    => $r['DimPerusahaan.nama_kota']     ?? '',
            'nama_provinsi'=> $r['DimPerusahaan.nama_provinsi'] ?? '',
            'count'        => (int) ($r['FactTracerStudy.count_alumni'] ?? 0),
        ]);
    }

    /**
     * @return Collection<array{nama_provinsi, count}>
     */
    public function getTopProvinsi(
        ?string $jenjang        = null,
        ?string $jurusan        = null,
        ?string $namaProdi      = null,
        ?string $tahunLulus     = null,
        ?string $mingguSnapshot = null,
        int     $limit          = 15,
    ): Collection {
        $filters = array_merge(
            $this->buildGlobalFilters(
                jenjang:        $jenjang,
                jurusan:        $jurusan,
                namaProdi:      $namaProdi,
                tahunLulus:     $tahunLulus,
                mingguSnapshot: $mingguSnapshot,
            ),
            [self::FILTER_BEKERJA],
        );

        return $this->cube->load([
            'measures'   => ['FactTracerStudy.count_alumni'],
            'dimensions' => ['DimPerusahaan.nama_provinsi'],
            'filters'    => $filters,
            'order'      => [['FactTracerStudy.count_alumni', 'desc']],
            'limit'      => $limit,
        ])->map(fn($r) => [
            'nama_provinsi'=> $r['DimPerusahaan.nama_provinsi'] ?? '',
            'count'        => (int) ($r['FactTracerStudy.count_alumni'] ?? 0),
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    //  5. DRILL-DOWN — alumni individual per jenis / tingkat
    // ──────────────────────────────────────────────────────────────

    /**
     *
     * @return array{data: array, page: int, per_page: int, total_on_page: int}
     */
    public function getDetailAlumni(
        ?string $jenisInstansi   = null,
        ?string $tingkatInstansi = null,
        ?string $jenjang         = null,
        ?string $namaProdi       = null,
        ?string $tahunLulus      = null,
        ?string $mingguSnapshot  = null,
        ?string $search          = null,
        int     $page            = 1,
        int     $perPage         = 15,
    ): array {
        $filters = array_merge(
            $this->buildGlobalFilters(
                jenjang:        $jenjang,
                namaProdi:      $namaProdi,
                tahunLulus:     $tahunLulus,
                mingguSnapshot: $mingguSnapshot,
            ),
            [self::FILTER_BEKERJA],
        );

        if ($jenisInstansi !== null && $jenisInstansi !== '') {
            $filters[] = [
                'member'   => 'DimPerusahaan.label_jenis_perusahaan',
                'operator' => 'equals',
                'values'   => [$jenisInstansi],
            ];
        }

        if ($tingkatInstansi !== null && $tingkatInstansi !== '') {
            $filters[] = [
                'member'   => 'DimPerusahaan.label_tingkat_instansi',
                'operator' => 'equals',
                'values'   => [$tingkatInstansi],
            ];
        }

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
                'DimPerusahaan.nama_kota',
                'DimPerusahaan.label_jenis_perusahaan',
                'DimPerusahaan.label_tingkat_instansi',
                'DimStatusAlumni.label',
            ],
            'filters' => $filters,
            'order'   => [['DimAlumni.nama', 'asc']],
            'limit'   => $perPage,
            'offset'  => ($page - 1) * $perPage,
        ]);

        $data = $result->map(fn($r) => [
            'nama'             => $r['DimAlumni.nama']                              ?? '',
            'nim'              => $r['DimAlumni.nim']                               ?? '',
            'nama_prodi'       => $r['DimProdi.nama_prodi']                         ?? '',
            'jenjang'          => $r['DimProdi.jenjang']                            ?? '',
            'tahun_lulus'      => $r['DimAlumni.tahun_lulus']                       ?? '',
            'nama_kota'        => $r['DimPerusahaan.nama_kota']                     ?? '',
            'jenis_instansi'   => $r['DimPerusahaan.label_jenis_perusahaan']        ?? '',
            'tingkat_instansi' => $r['DimPerusahaan.label_tingkat_instansi']        ?? '',
            'status'           => $r['DimStatusAlumni.label']                       ?? '',
        ])->toArray();

        return [
            'data'          => $data,
            'page'          => $page,
            'per_page'      => $perPage,
            'total_on_page' => count($data),
        ];
    }

    // ──────────────────────────────────────────────────────────────
    //  PRIVATE HELPERS
    // ──────────────────────────────────────────────────────────────

    private function buildProdiFilter(array $prodiFilter): array
    {
        if (empty($prodiFilter)) {
            return [];
        }

        return [[
            'member'   => 'DimProdi.nama_prodi',
            'operator' => 'equals',
            'values'   => $prodiFilter,
        ]];
    }
}