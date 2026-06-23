<?php

namespace App\Repositories\Analytical;

use Illuminate\Support\Collection;


class KesesuaianRepository extends BaseAnalyticalRepository
{
    // ──────────────────────────────────────────────────────────────
    //  1. BAR — sesuai vs tidak sesuai per prodi × tahun
    // ──────────────────────────────────────────────────────────────

    /**
     * Pre-agg: FactTracerStudy.distribusi_kesesuaian ✅
     *
     * @return Collection<array{nama_prodi, jenjang, tahun_lulus,
     *                          count_alumni, count_sesuai_bidang, count_tidak_sesuai_bidang}>
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
            'nama_prodi'               => $r['DimProdi.nama_prodi']                         ?? '',
            'jenjang'                  => $r['DimProdi.jenjang']                            ?? '',
            'tahun_lulus'              => $r['DimAlumni.tahun_lulus']                       ?? '',
            'count_alumni'             => (int) ($r['FactTracerStudy.count_alumni']              ?? 0),
            'count_sesuai_bidang'      => (int) ($r['FactTracerStudy.count_sesuai_bidang']       ?? 0),
            'count_tidak_sesuai_bidang'=> (int) ($r['FactTracerStudy.count_tidak_sesuai_bidang'] ?? 0),
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    //  2. PIE — distribusi tingkat kesesuaian
    // ──────────────────────────────────────────────────────────────

    /**
     * Hanya alumni Bekerja (status_alumni_sk = 1).
     * Pre-agg: FactTracerStudy.distribusi_kesesuaian ✅
     *
     * @return Collection<array{label, count}>
     */
    public function getPieData(
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
            // hanya alumni bekerja (status_alumni_sk = 1)
            [['member' => 'FactTracerStudy.status_alumni_sk', 'operator' => 'equals', 'values' => ['1']]],
        );

        return $this->cube->load([
            'measures'   => ['FactTracerStudy.count_alumni'],
            'dimensions' => ['DimKesesuaianBidang.label'],
            'filters'    => $filters,
            'order'      => [['FactTracerStudy.count_alumni', 'desc']],
        ])->map(fn($r) => [
            'label' => $r['DimKesesuaianBidang.label']      ?? '',
            'count' => (int) ($r['FactTracerStudy.count_alumni'] ?? 0),
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    //  3. ALASAN — frekuensi alasan kerja tidak sesuai (FactMultiSelect)
    // ──────────────────────────────────────────────────────────────

    /**
     * Pre-agg: FactMultiSelect.per_indikator ✅
     * Filter kategori_pertanyaan = 'AlasanKerjaTdkSesuai'.
     *
     * @return Collection<array{kode_field, label, count}>
     */
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
            'kode_field' => $r['DimIndikatorEvaluasi.kode_field']        ?? '',
            'label'      => $r['DimIndikatorEvaluasi.label_pertanyaan']  ?? '',
            'count'      => (int) ($r['FactMultiSelect.count_pilihan']   ?? 0),
        ]);
    }


    /**
     * TIDAK pakai pre-agg — data individual alumni.
     *
     * @return array{data: array, page: int, per_page: int, total_on_page: int}
     */
    public function getDetailAlumni(
        int     $kesesuaianSk,
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
            [['member'   => 'FactTracerStudy.kesesuaian_bidang_sk',
              'operator' => 'equals',
              'values'   => [(string) $kesesuaianSk]]],
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
            'nama'              => $r['DimAlumni.nama']              ?? '',
            'nim'               => $r['DimAlumni.nim']               ?? '',
            'nama_prodi'        => $r['DimProdi.nama_prodi']         ?? '',
            'jenjang'           => $r['DimProdi.jenjang']            ?? '',
            'tahun_lulus'       => $r['DimAlumni.tahun_lulus']       ?? '',
            'kesesuaian_bidang' => $r['DimKesesuaianBidang.label']   ?? '',
            'status'            => $r['DimStatusAlumni.label']       ?? '',
        ])->toArray();

        return [
            'data'          => $data,
            'page'          => $page,
            'per_page'      => $perPage,
            'total_on_page' => count($data),
        ];
    }

    // ──────────────────────────────────────────────────────────────
    //  DRILL-DOWN ALASAN — alumni per alasan kerja tidak sesuai
    //  Source: FactMultiSelect (bukan FactTracerStudy)
    // ──────────────────────────────────────────────────────────────

    /**
     * List alumni yang memilih alasan tertentu dari chart alasan kerja tidak sesuai.
     * Source: FactMultiSelect, filter by DimIndikatorEvaluasi.label_pertanyaan.
     * TIDAK pakai pre-agg — data individual alumni.
     *
     * @return array{data: array, page: int, per_page: int, total_on_page: int}
     */
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
            'nama'              => $r['DimAlumni.nama']                        ?? '',
            'nim'               => $r['DimAlumni.nim']                         ?? '',
            'nama_prodi'        => $r['DimProdi.nama_prodi']                   ?? '',
            'jenjang'           => $r['DimProdi.jenjang']                      ?? '',
            'tahun_lulus'       => $r['DimAlumni.tahun_lulus']                 ?? '',
            'alasan'            => $r['DimIndikatorEvaluasi.label_pertanyaan'] ?? '',
        ])->toArray();

        return [
            'data'          => $data,
            'page'          => $page,
            'per_page'      => $perPage,
            'total_on_page' => count($data),
        ];
    }
}