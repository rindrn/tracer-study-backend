<?php

namespace App\Repositories\Analytical;

use Illuminate\Support\Collection;

class WirausahaRepository extends BaseAnalyticalRepository
{
    // ──────────────────────────────────────────────────────────────
    //  1. BAR — count wirausaha + avg masa tunggu per prodi × tahun
    // ──────────────────────────────────────────────────────────────

    /**
     *
     * @return Collection<array{nama_prodi, jenjang, jurusan, tahun_lulus,
     *                          count_wirausaha, avg_masa_tunggu_wirausaha}>
     */
    public function getBarData(
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
            // hanya alumni wirausaha. status_alumni_sk BUKAN literal stabil --
            // dim_status_alumni Type1 membuat surrogate key baru per
            // questionnaire_id (mis. sk 3, 8, 13 sama-sama label "Wiraswasta"
            // tapi dari questionnaire berbeda), jadi equals ke satu angka
            // diam-diam membuang alumni yang jawab lewat questionnaire lain
            // -- persis kasus yang membuat pie & bar Wirausaha kehilangan
            // sebagian data padahal getKotaData() (di bawah) sudah benar
            // pakai pola contains ini.
            [['member' => 'DimStatusAlumni.id_status_alumni', 'operator' => 'contains', 'values' => [':f8:3']]],
        );

        return $this->cube->load([
            'measures' => [
                'FactTracerStudy.count_alumni',
                'FactTracerStudy.avg_masa_tunggu_wirausaha',
            ],
            'dimensions' => [
                'DimProdi.jenjang',
                'DimProdi.jurusan',
                'DimProdi.nama_prodi',
                'DimAlumni.tahun_lulus',
            ],
            'filters' => $filters,
            'order'   => [
                ['DimProdi.nama_prodi',  'asc'],
                ['DimAlumni.tahun_lulus','asc'],
            ],
        ])->map(fn($r) => [
            'nama_prodi'               => $r['DimProdi.nama_prodi']                         ?? '',
            'jenjang'                  => $r['DimProdi.jenjang']                            ?? '',
            'jurusan'                  => $r['DimProdi.jurusan']                            ?? '',
            'tahun_lulus'              => $r['DimAlumni.tahun_lulus']                       ?? '',
            'count_wirausaha'          => (int)   ($r['FactTracerStudy.count_alumni']               ?? 0),
            'avg_masa_tunggu_wirausaha'=> (float) ($r['FactTracerStudy.avg_masa_tunggu_wirausaha']  ?? 0),
        ]);
    }

    /**
     *
     * @return Collection<array{nama_prodi, tahun_lulus, count_alumni}>
     */
    public function getBarDataTotal(
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
            'dimensions' => [
                'DimProdi.nama_prodi',
                'DimAlumni.tahun_lulus',
            ],
            'filters' => $filters,
        ])->map(fn($r) => [
            'nama_prodi'  => $r['DimProdi.nama_prodi']           ?? '',
            'tahun_lulus' => $r['DimAlumni.tahun_lulus']         ?? '',
            'count_alumni'=> (int) ($r['FactTracerStudy.count_alumni'] ?? 0),
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    //  2a. PIE — distribusi POSISI/JABATAN wirausaha (Owner, Co-founder, dll)
    // ──────────────────────────────────────────────────────────────

    /**
     * @return Collection<array{label, count}>
     */
    public function getPiePosisi(
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
            // hanya alumni wirausaha -- lihat catatan di getBarData() soal
            // kenapa contains ':f8:3' dipakai, bukan equals ke status_alumni_sk.
            [['member' => 'DimStatusAlumni.id_status_alumni', 'operator' => 'contains', 'values' => [':f8:3']]],
        );

        return $this->cube->load([
            'measures'   => ['FactTracerStudy.count_alumni'],
            'dimensions' => ['DimWirausaha.jabatan'],
            'filters'    => $filters,
            'order'      => [['FactTracerStudy.count_alumni', 'desc']],
        ])->map(fn($r) => [
            'label' => $r['DimWirausaha.jabatan'] ?? '',
            'count' => (int) ($r['FactTracerStudy.count_alumni'] ?? 0),
        ])->filter(fn($r) => $r['label'] !== ''); // buang row kosong
    }

    // ──────────────────────────────────────────────────────────────
    //  2b. PIE — sebaran kota alumni wirausaha (top 15)
    // ──────────────────────────────────────────────────────────────

    /**
     *
     * @return Collection<array{nama_kota, nama_provinsi, count}>
     */
    public function getKotaData(
        ?string $jenjang        = null,
        ?string $jurusan        = null,
        ?string $namaProdi      = null,
        ?string $tahunLulus     = null,
        ?string $mingguSnapshot = null,
    ): Collection {
        $filters = array_merge(
            $this->buildGlobalFilters(
                jenjang: $jenjang,
                jurusan: $jurusan,
                namaProdi: $namaProdi,
                tahunLulus: $tahunLulus,
                mingguSnapshot: $mingguSnapshot,
            ),
            [
                [
                    'member' => 'DimStatusAlumni.id_status_alumni',
                    'operator' => 'contains',
                    'values' => [':f8:3'],
                ]
            ]
        );

        return $this->cube->load([
            'measures'   => ['FactTracerStudy.count_alumni'],
            'dimensions' => [
                'DimWirausaha.nama_kota',
                'DimWirausaha.nama_provinsi',
            ],
            'filters' => $filters,
            'order'   => [['FactTracerStudy.count_alumni', 'desc']],
            'limit'   => 15,
        ])->map(fn($r) => [
            'nama_kota'    => $r['DimWirausaha.nama_kota']     ?? '',
            'nama_provinsi'=> $r['DimWirausaha.nama_provinsi'] ?? '',
            'count'        => (int) ($r['FactTracerStudy.count_alumni'] ?? 0),
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    //  3. DRILL-DOWN — alumni wirausaha per tingkat
    // ──────────────────────────────────────────────────────────────

    /**
     *
     * @return array{data: array, page: int, per_page: int, total_on_page: int}
     */
    public function getDetailAlumni(
        ?string $jabatan        = null,
        ?string $jenjang        = null,
        ?string $namaProdi      = null,
        ?string $tahunLulus     = null,
        ?string $mingguSnapshot = null,
        ?string $search         = null,
        int     $page           = 1,
        int     $perPage        = 15,
    ): array {
        $extra = [
            // lihat catatan di getBarData() soal contains ':f8:3' vs equals status_alumni_sk.
            ['member' => 'DimStatusAlumni.id_status_alumni', 'operator' => 'contains', 'values' => [':f8:3']],
        ];

        // Filter jabatan hanya ditambahkan kalau tidak null
        if ($jabatan !== null && $jabatan !== '') {
            $extra[] = [
                'member'   => 'DimWirausaha.jabatan',
                'operator' => 'equals',
                'values'   => [$jabatan],
            ];
        }

        $filters = array_merge(
            $this->buildGlobalFilters(
                jenjang:        $jenjang,
                namaProdi:      $namaProdi,
                tahunLulus:     $tahunLulus,
                mingguSnapshot: $mingguSnapshot,
            ),
            $extra,
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
                'DimWirausaha.nama_kota',
                'DimWirausaha.nama_provinsi',
                'DimWirausaha.jabatan',
                'FactTracerStudy.masa_tunggu_wirausaha',
            ],
            'filters' => $filters,
            'order'   => [['DimAlumni.nama', 'asc']],
            'limit'   => $perPage,
            'offset'  => ($page - 1) * $perPage,
        ]);

        $data = $result->map(fn($r) => [
            'nama'                 => $r['DimAlumni.nama']                             ?? '',
            'nim'                  => $r['DimAlumni.nim']                              ?? '',
            'nama_prodi'           => $r['DimProdi.nama_prodi']                        ?? '',
            'jenjang'              => $r['DimProdi.jenjang']                           ?? '',
            'tahun_lulus'          => $r['DimAlumni.tahun_lulus']                      ?? '',
            'nama_kota'            => $r['DimWirausaha.nama_kota']                     ?? '',
            'nama_provinsi'        => $r['DimWirausaha.nama_provinsi']                 ?? '',
            'jabatan'              => $r['DimWirausaha.jabatan']                       ?? '',
            'masa_tunggu_wirausaha'=> (int) ($r['FactTracerStudy.masa_tunggu_wirausaha'] ?? 0),
        ])->toArray();

        return [
            'data'          => $data,
            'page'          => $page,
            'per_page'      => $perPage,
            'total_on_page' => count($data),
        ];
    }

    // ──────────────────────────────────────────────────────────────
    //  4. BANDINGKAN — distribusi per prodi × tingkat
    // ──────────────────────────────────────────────────────────────

    /**
     *
     * @param  array<string>  $prodiFilter  Kosong = semua prodi.
     * @return Collection<array{nama_prodi, jenjang, jurusan, tahun_lulus,
     *                          label_tingkat, count_wirausaha,
     *                          avg_masa_tunggu, min_masa_tunggu, max_masa_tunggu}>
     */
    public function getBandingkanData(
        array   $prodiFilter    = [],
        ?string $jenjang        = null,
        ?string $jurusan        = null,
        ?string $tahunLulus     = null,
        ?string $mingguSnapshot = null,
    ): Collection {
        $extra = [];
        if (!empty($prodiFilter)) {
            $extra[] = [
                'member'   => 'DimProdi.nama_prodi',
                'operator' => 'equals',
                'values'   => $prodiFilter,
            ];
        }

        // Hanya alumni wirausaha -- lihat catatan di getBarData() soal
        // contains ':f8:3' vs equals status_alumni_sk.
        $extra[] = [
            'member'   => 'DimStatusAlumni.id_status_alumni',
            'operator' => 'contains',
            'values'   => [':f8:3'],
        ];

        $filters = $this->buildGlobalFilters(
            jenjang:        $jenjang,
            jurusan:        $jurusan,
            tahunLulus:     $tahunLulus,
            mingguSnapshot: $mingguSnapshot,
            extra:          $extra,
        );

        return $this->cube->load([
            'measures' => [
                'FactTracerStudy.count_alumni',
                'FactTracerStudy.avg_masa_tunggu_wirausaha',
                'FactTracerStudy.min_masa_tunggu_wirausaha',
                'FactTracerStudy.max_masa_tunggu_wirausaha',
            ],
            'dimensions' => [
                'DimProdi.jenjang',
                'DimProdi.jurusan',
                'DimProdi.nama_prodi',
                'DimWirausaha.jabatan',
                'DimAlumni.tahun_lulus',
            ],
            'filters' => $filters,
            'order'   => [
                ['DimProdi.nama_prodi',                'asc'],
                ['DimWirausaha.jabatan','asc'],
            ],
        ])->map(fn($r) => [
            'nama_prodi'        => $r['DimProdi.nama_prodi']                            ?? '',
            'jenjang'           => $r['DimProdi.jenjang']                               ?? '',
            'jurusan'           => $r['DimProdi.jurusan']                               ?? '',
            'tahun_lulus'       => $r['DimAlumni.tahun_lulus']                          ?? '',
            'jabatan'           => $r['DimWirausaha.jabatan']                           ?? '',
            'count_wirausaha'   => (int)   ($r['FactTracerStudy.count_alumni']                  ?? 0),
            'avg_masa_tunggu'   => (float) ($r['FactTracerStudy.avg_masa_tunggu_wirausaha']      ?? 0),
            'min_masa_tunggu'   => (int)   ($r['FactTracerStudy.min_masa_tunggu_wirausaha']      ?? 0),
            'max_masa_tunggu'   => (int)   ($r['FactTracerStudy.max_masa_tunggu_wirausaha']      ?? 0),
        ]);
    }

    /**
     *
     * @param  array<string>  $prodiFilter
     * @return Collection<array{nama_prodi, count_alumni}>
     */
    public function getBandingkanTotal(
        array   $prodiFilter    = [],
        ?string $jenjang        = null,
        ?string $jurusan        = null,
        ?string $tahunLulus     = null,
        ?string $mingguSnapshot = null,
    ): Collection {
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

        return $this->cube->load([
            'measures'   => ['FactTracerStudy.count_alumni'],
            'dimensions' => ['DimProdi.nama_prodi'],
            'filters'    => $filters,
        ])->map(fn($r) => [
            'nama_prodi'  => $r['DimProdi.nama_prodi']            ?? '',
            'count_alumni'=> (int) ($r['FactTracerStudy.count_alumni'] ?? 0),
        ]);
    }
}