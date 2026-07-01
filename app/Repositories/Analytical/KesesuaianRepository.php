<?php

namespace App\Repositories\Analytical;

use Illuminate\Support\Collection;


class KesesuaianRepository extends BaseAnalyticalRepository
{
    // ──────────────────────────────────────────────────────────────
    //  1. BAR — sesuai vs tidak sesuai per prodi × tahun
    // ──────────────────────────────────────────────────────────────

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
                'FactTracerStudy.count_sesuai_bidang',
                'FactTracerStudy.count_tidak_sesuai_bidang',
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
            'nama_prodi'               => $r['DimProdi.nama_prodi']                          ?? '',
            'jenjang'                  => $r['DimProdi.jenjang']                             ?? '',
            'tahun_lulus'              => $r['DimAlumni.tahun_lulus']                        ?? '',
            'count_alumni'             => (int) ($r['FactTracerStudy.count_alumni']           ?? 0),
            'count_sesuai_bidang'      => (int) ($r['FactTracerStudy.count_sesuai_bidang']    ?? 0),
            'count_tidak_sesuai_bidang'=> (int) ($r['FactTracerStudy.count_tidak_sesuai_bidang'] ?? 0),
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    //  2. PIE — distribusi tingkat kesesuaian
    // ──────────────────────────────────────────────────────────────
    public function getPieData(
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
            'dimensions' => ['DimKesesuaianBidang.label'],
            'filters'    => $filters,
            'order'      => [['FactTracerStudy.count_alumni', 'desc']],
        ])->map(fn($r) => [
            'label' => $r['DimKesesuaianBidang.label']           ?? '',
            'count' => (int) ($r['FactTracerStudy.count_alumni'] ?? 0),
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    //  3. ALASAN — frekuensi alasan kerja tidak sesuai
    // ──────────────────────────────────────────────────────────────

    public function getAlasanData(
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
            [['member'   => 'DimIndikatorEvaluasi.kategori_pertanyaan',
              'operator' => 'equals',
              'values'   => ['AlasanKerjaTdkSesuai']]],
        );

        return $this->cube->load([
            'measures'   => ['FactMultiSelect.count_pilihan'],
            'dimensions' => [
                'DimIndikatorEvaluasi.label_pertanyaan',
                'DimIndikatorEvaluasi.kode_field',
            ],
            'filters' => $filters,
            'order'   => [['FactMultiSelect.count_pilihan', 'desc']],
        ])->map(fn($r) => [
            'kode_field' => $r['DimIndikatorEvaluasi.kode_field']       ?? '',
            'label'      => $r['DimIndikatorEvaluasi.label_pertanyaan'] ?? '',
            'count'      => (int) ($r['FactMultiSelect.count_pilihan']  ?? 0),
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    //  4. DETAIL ALUMNI — per kesesuaian bidang
    // ──────────────────────────────────────────────────────────────

    public function getDetailAlumni(
        array|string|null $labelKesesuaian,
        ?string           $jenjang        = null,
        ?string           $namaProdi      = null,
        ?string           $tahunLulus     = null,
        ?string           $mingguSnapshot = null,
        ?string           $search         = null,
        int               $page           = 1,
        int               $perPage        = 15,
    ): array {
        $labels = match(true) {
            is_array($labelKesesuaian)  => $labelKesesuaian,
            is_string($labelKesesuaian) => [$labelKesesuaian],
            default                     => [],
        };

        $filters = array_merge(
            $this->buildGlobalFilters(
                jenjang:        $jenjang,
                namaProdi:      $namaProdi,
                tahunLulus:     $tahunLulus,
                mingguSnapshot: $mingguSnapshot,
            ),
            !empty($labels) ? [[
                'member'   => 'DimKesesuaianBidang.label',
                'operator' => 'equals',
                'values'   => $labels,
            ]] : [],
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
                'DimKesesuaianBidang.label',
                'DimStatusAlumni.label',
            ],
            'filters' => $filters,
            'order'   => [['DimAlumni.nama', 'asc']],
            'limit'   => $perPage,
            'offset'  => ($page - 1) * $perPage,
        ]);

        $data = $result->map(fn($r) => [
            'nama'              => $r['DimAlumni.nama']            ?? '',
            'nim'               => $r['DimAlumni.nim']             ?? '',
            'nama_prodi'        => $r['DimProdi.nama_prodi']       ?? '',
            'jenjang'           => $r['DimProdi.jenjang']          ?? '',
            'tahun_lulus'       => $r['DimAlumni.tahun_lulus']     ?? '',
            'kesesuaian_bidang' => $r['DimKesesuaianBidang.label'] ?? '',
            'status'            => $r['DimStatusAlumni.label']     ?? '',
        ])->toArray();

        return [
            'data'          => $data,
            'page'          => $page,
            'per_page'      => $perPage,
            'total_on_page' => count($data),
        ];
    }

    // ──────────────────────────────────────────────────────────────
    //  5. DRILL-DOWN ALASAN — alumni per alasan kerja tidak sesuai
    // ──────────────────────────────────────────────────────────────

    public function getDetailAlumniByAlasan(
        string  $labelPertanyaan,
        ?string $jenjang        = null,
        ?string $namaProdi      = null,
        ?string $tahunLulus     = null,
        ?string $mingguSnapshot = null,
        ?string $search         = null,
        int     $page           = 1,
        int     $perPage        = 15,
    ): array {
        $filters = array_merge(
            $this->buildGlobalFilters(
                jenjang:        $jenjang,
                namaProdi:      $namaProdi,
                tahunLulus:     $tahunLulus,
                mingguSnapshot: $mingguSnapshot,
            ),
            [
                [
                    'member'   => 'DimIndikatorEvaluasi.label_pertanyaan',
                    'operator' => 'equals',
                    'values'   => [$labelPertanyaan],
                ],
                [
                    'member'   => 'DimIndikatorEvaluasi.kategori_pertanyaan',
                    'operator' => 'equals',
                    'values'   => ['AlasanKerjaTdkSesuai'],
                ],
            ],
        );

        if ($search !== null && $search !== '') {
            $filters[] = [
                'member'   => 'DimAlumni.nama',
                'operator' => 'contains',
                'values'   => [$search],
            ];
        }

        $result = $this->cube->load([
            'measures'   => ['FactMultiSelect.count_pilihan'],
            'dimensions' => [
                'DimAlumni.nama',
                'DimAlumni.nim',
                'DimProdi.nama_prodi',
                'DimProdi.jenjang',
                'DimAlumni.tahun_lulus',
                'DimIndikatorEvaluasi.label_pertanyaan',
            ],
            'filters' => $filters,
            'order'   => [['DimAlumni.nama', 'asc']],
            'limit'   => $perPage,
            'offset'  => ($page - 1) * $perPage,
        ]);

        $data = $result->map(fn($r) => [
            'nama'        => $r['DimAlumni.nama']                        ?? '',
            'nim'         => $r['DimAlumni.nim']                         ?? '',
            'nama_prodi'  => $r['DimProdi.nama_prodi']                   ?? '',
            'jenjang'     => $r['DimProdi.jenjang']                      ?? '',
            'tahun_lulus' => $r['DimAlumni.tahun_lulus']                 ?? '',
            'alasan'      => $r['DimIndikatorEvaluasi.label_pertanyaan'] ?? '',
        ])->toArray();

        return [
            'data'          => $data,
            'page'          => $page,
            'per_page'      => $perPage,
            'total_on_page' => count($data),
        ];
    }

    // ──────────────────────────────────────────────────────────────
    //  6. BANDINGKAN — sesuai vs tidak sesuai per prodi terpilih
    // ──────────────────────────────────────────────────────────────

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
                'FactTracerStudy.count_sesuai_bidang',
                'FactTracerStudy.count_tidak_sesuai_bidang',
            ],
            'dimensions' => [
                'DimProdi.jenjang',
                'DimProdi.jurusan',
                'DimProdi.nama_prodi',
            ],
            'filters' => $filters,
            'order'   => [['DimProdi.nama_prodi', 'asc']],
        ])->map(fn($r) => [
            'nama_prodi'                => $r['DimProdi.nama_prodi']                              ?? '',
            'jenjang'                   => $r['DimProdi.jenjang']                                 ?? '',
            'count_alumni'              => (int) ($r['FactTracerStudy.count_alumni']               ?? 0),
            'count_sesuai_bidang'       => (int) ($r['FactTracerStudy.count_sesuai_bidang']        ?? 0),
            'count_tidak_sesuai_bidang' => (int) ($r['FactTracerStudy.count_tidak_sesuai_bidang']  ?? 0),
        ]);
    }
}
