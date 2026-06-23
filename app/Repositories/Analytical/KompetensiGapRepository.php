<?php

namespace App\Repositories\Analytical;

use Illuminate\Support\Collection;

class KompetensiGapRepository extends BaseAnalyticalRepository
{
    // Filter kategori: hanya ambil Kompetensi_A dan Kompetensi_B
    private const FILTER_KATEGORI = [
        'member'   => 'DimIndikatorEvaluasi.kategori_pertanyaan',
        'operator' => 'in',
        'values'   => ['Kompetensi_A', 'Kompetensi_B'],
    ];

    /**
     *
     * @return Collection<array{grup_gap, label, kategori, avg_skor, count}>
     */
    public function getGapData(
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
            [self::FILTER_KATEGORI],
        );

        // Cube.js pre-agg rollup hanya aktif kalau semua dimension yang
        // di-filter juga ada di array 'dimensions' query. Tanpa ini, Cube
        // ignore filter atau fallback ke raw SQL tanpa rollup.
        // Dimension ini ditambahkan kondisional — kalau filter tidak aktif,
        // tidak perlu masuk dimensions supaya data tidak melebar per-prodi.
        $extraDimensions = [];
        if ($jenjang !== null && $jenjang !== '')        $extraDimensions[] = 'DimProdi.jenjang';
        if ($jurusan !== null && $jurusan !== '')        $extraDimensions[] = 'DimProdi.jurusan';
        if ($namaProdi !== null && $namaProdi !== '')    $extraDimensions[] = 'DimProdi.nama_prodi';
        if ($tahunLulus !== null && $tahunLulus !== '')  $extraDimensions[] = 'DimAlumni.tahun_lulus';
        if ($mingguSnapshot !== null && $mingguSnapshot !== '') $extraDimensions[] = 'DimWaktu.minggu_snapshot';

        return $this->cube->load([
            'measures'   => [
                'FactRangeEvaluasi.avg_skor',
                'FactRangeEvaluasi.count',
            ],
            'dimensions' => array_merge([
                'DimIndikatorEvaluasi.label_pertanyaan',
                'DimIndikatorEvaluasi.grup_gap',
                'DimIndikatorEvaluasi.kategori_pertanyaan',
            ], $extraDimensions),
            'filters' => $filters,
            'order'   => [['DimIndikatorEvaluasi.grup_gap', 'asc']],
        ])->map(fn($r) => [
            'grup_gap' => $r['DimIndikatorEvaluasi.grup_gap']               ?? '',
            'label'      => $r['DimIndikatorEvaluasi.label_pertanyaan']     ?? '',
            'kategori'   => $r['DimIndikatorEvaluasi.kategori_pertanyaan']  ?? '',
            'avg_skor'   => (float) ($r['FactRangeEvaluasi.avg_skor']       ?? 0),
            'count'      => (int)   ($r['FactRangeEvaluasi.count']          ?? 0),
        ]);
    }

    /**
     *
     * @param  array<string>  $prodiFilter  Kosong = semua prodi.
     * @return Collection<array{grup_gap, label, kategori, jenjang, jurusan, nama_prodi, tahun_lulus, avg_skor, count}>
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

        $filters = array_merge(
            $this->buildGlobalFilters(
                jenjang:        $jenjang,
                jurusan:        $jurusan,
                tahunLulus:     $tahunLulus,
                mingguSnapshot: $mingguSnapshot,
                extra:          $extra,
            ),
            [self::FILTER_KATEGORI],
        );

        return $this->cube->load([
            'measures'   => [
                'FactRangeEvaluasi.avg_skor',
                'FactRangeEvaluasi.count',
            ],
            'dimensions' => [
                'DimIndikatorEvaluasi.grup_gap',
                'DimIndikatorEvaluasi.label_pertanyaan',
                'DimIndikatorEvaluasi.kategori_pertanyaan',
                'DimProdi.jenjang',
                'DimProdi.jurusan',
                'DimProdi.nama_prodi',
                'DimAlumni.tahun_lulus',
            ],
            'filters' => $filters,
            'order'   => [
                ['DimProdi.nama_prodi',             'asc'],
                ['DimIndikatorEvaluasi.grup_gap', 'asc'],
            ],
        ])->map(fn($r) => [
            'grup_gap'  => $r['DimIndikatorEvaluasi.grup_gap']          ?? '',
            'label'       => $r['DimIndikatorEvaluasi.label_pertanyaan']    ?? '',
            'kategori'    => $r['DimIndikatorEvaluasi.kategori_pertanyaan'] ?? '',
            'nama_prodi'  => $r['DimProdi.nama_prodi']                      ?? '',
            'jenjang'     => $r['DimProdi.jenjang']                         ?? '',
            'jurusan'     => $r['DimProdi.jurusan']                         ?? '',
            'tahun_lulus' => $r['DimAlumni.tahun_lulus']                    ?? '',
            'avg_skor'    => (float) ($r['FactRangeEvaluasi.avg_skor']      ?? 0),
            'count'       => (int)   ($r['FactRangeEvaluasi.count']         ?? 0),
        ]);
    }

    /**
     * Drill-down: data individual alumni per indikator yang diklik.
     * TIDAK pakai pre-agg — query ke FactRangeEvaluasi raw.
     *
     * Setiap alumni punya 2 baris (Kompetensi_A & Kompetensi_B) per kode_field.
     * Query ambil kedua kategori sekaligus, lalu join per alumni di PHP
     * untuk dapat skor_lulus + skor_dibutuhkan berdampingan.
     *
     * @return array{data: array, page: int, per_page: int, total_on_page: int}
     */
    public function getDetailAlumni(
        string  $kodeField,
        ?string $jenjang        = null,
        ?string $jurusan        = null,
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
                jurusan:        $jurusan,
                namaProdi:      $namaProdi,
                tahunLulus:     $tahunLulus,
                mingguSnapshot: $mingguSnapshot,
            ),
            [
                [
                    'member'   => 'DimIndikatorEvaluasi.kode_field',
                    'operator' => 'equals',
                    'values'   => [$kodeField],
                ],
                self::FILTER_KATEGORI,
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
            'measures'   => ['FactRangeEvaluasi.avg_skor'],
            'dimensions' => [
                'DimAlumni.nama',
                'DimAlumni.nim',
                'DimProdi.nama_prodi',
                'DimProdi.jenjang',
                'DimAlumni.tahun_lulus',
                'DimIndikatorEvaluasi.kategori_pertanyaan',
                'DimIndikatorEvaluasi.kode_field',
            ],
            'filters' => $filters,
            'order'   => [['DimAlumni.nama', 'asc']],
            'limit'   => $perPage * 2, // ×2 karena setiap alumni ada 2 baris (A+B)
            'offset'  => ($page - 1) * $perPage * 2,
        ]);

        // Join baris A+B per alumni → 1 row dengan skor_lulus + skor_dibutuhkan
        $byAlumni = $result->groupBy(
            fn($r) => ($r['DimAlumni.nim'] ?? '') . '||' . ($r['DimAlumni.nama'] ?? '')
        );

        $data = $byAlumni->map(function ($rows) {
            $rowA  = $rows->firstWhere('DimIndikatorEvaluasi.kategori_pertanyaan', 'Kompetensi_A');
            $rowB  = $rows->firstWhere('DimIndikatorEvaluasi.kategori_pertanyaan', 'Kompetensi_B');
            $first = $rows->first();

            return [
                'nama'            => $first['DimAlumni.nama']        ?? '',
                'nim'             => $first['DimAlumni.nim']         ?? '',
                'nama_prodi'      => $first['DimProdi.nama_prodi']   ?? '',
                'jenjang'         => $first['DimProdi.jenjang']      ?? '',
                'tahun_lulus'     => $first['DimAlumni.tahun_lulus'] ?? '',
                'skor_lulus'      => isset($rowA)
                    ? round((float) ($rowA['FactRangeEvaluasi.avg_skor'] ?? 0), 2)
                    : null,
                'skor_dibutuhkan' => isset($rowB)
                    ? round((float) ($rowB['FactRangeEvaluasi.avg_skor'] ?? 0), 2)
                    : null,
            ];
        })->values()->toArray();

        return [
            'data'          => $data,
            'page'          => $page,
            'per_page'      => $perPage,
            'total_on_page' => count($data),
        ];
    }
}