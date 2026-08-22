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
        ?array  $idProdiIn      = null,
        ?string $namaProdi      = null,
        ?string $tahunLulus     = null,
        ?string $mingguSnapshot = null,
    ): Collection {
        $filters = array_merge(
            $this->buildGlobalFilters(
                jenjang:        $jenjang,
                jurusan:        $jurusan,
                idProdiIn:      $idProdiIn,
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
                'DimIndikatorEvaluasi.kode_field',
                'DimIndikatorEvaluasi.grup_gap',
                'DimIndikatorEvaluasi.kategori_pertanyaan',
            ], $extraDimensions),
            'filters' => $filters,
            'order'   => [['DimIndikatorEvaluasi.grup_gap', 'asc']],
        ])->map(fn($r) => [
            'grup_gap' => $r['DimIndikatorEvaluasi.grup_gap']               ?? '',
            'kode_field' => $r['DimIndikatorEvaluasi.kode_field']           ?? '',
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
        ?array  $idProdiIn      = null,
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
                idProdiIn:      $idProdiIn,
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
        string  $grupGap,
        ?string $jenjang        = null,
        ?string $jurusan        = null,
        ?array  $idProdiIn      = null,
        ?string $namaProdi      = null,
        ?string $tahunLulus     = null,
        ?string $mingguSnapshot = null,
        ?string $search         = null,
        int     $page           = 1,
        int     $perPage        = 15,
    ): array {
        $filters = $this->buildGlobalFilters(
            jenjang:        $jenjang,
            jurusan:        $jurusan,
            idProdiIn:      $idProdiIn,
            namaProdi:      $namaProdi,
            tahunLulus:     $tahunLulus,
            mingguSnapshot: $mingguSnapshot,
        );

        // Filter grup_gap
        $filters[] = [
            'member'   => 'DimIndikatorEvaluasi.grup_gap',
            'operator' => 'equals',
            'values'   => [$grupGap],
        ];

        if ($search !== null && $search !== '') {
            $filters[] = [
                'member'   => 'DimAlumni.nama',
                'operator' => 'contains',
                'values'   => [$search],
            ];
        }

        // Tidak include FILTER_KATEGORI di sini — split per query A dan B di bawah

        // 2 query terpisah karena Cube pre-agg hanya return 1 kategori per alumni
        // ketika kategori_pertanyaan ada di dimensions (deduplication issue)
        $baseQuery = [
            'measures'   => ['FactRangeEvaluasi.avg_skor'],
            'dimensions' => [
                'DimAlumni.nama',
                'DimAlumni.nim',
                'DimProdi.nama_prodi',
                'DimProdi.jenjang',
                'DimAlumni.tahun_lulus',
            ],
            'order'  => [['DimAlumni.nama', 'asc']],
            'limit'  => $perPage,
            'offset' => ($page - 1) * $perPage,
        ];

        $filtersA = array_merge($filters, [[
            'member'   => 'DimIndikatorEvaluasi.kategori_pertanyaan',
            'operator' => 'equals',
            'values'   => ['Kompetensi_A'],
        ]]);

        $filtersB = array_merge($filters, [[
            'member'   => 'DimIndikatorEvaluasi.kategori_pertanyaan',
            'operator' => 'equals',
            'values'   => ['Kompetensi_B'],
        ]]);

        // Hapus FILTER_KATEGORI dari $filters karena sudah di-split per query
        // (filters sudah tidak punya FILTER_KATEGORI karena kita replace di atas)
        $resultA = $this->cube->load(array_merge($baseQuery, ['filters' => $filtersA]));
        $resultB = $this->cube->load(array_merge($baseQuery, ['filters' => $filtersB]));

        // Index B by nim untuk lookup cepat
        $mapB = $resultB->keyBy(fn($r) => $r['DimAlumni.nim'] ?? '');

        $data = $resultA->map(function ($r) use ($mapB) {
            $nim  = $r['DimAlumni.nim'] ?? '';
            $rowB = $mapB->get($nim);

            $skorLulus      = round((float) ($r['FactRangeEvaluasi.avg_skor'] ?? 0), 2);
            $skorDibutuhkan = $rowB ? round((float) ($rowB['FactRangeEvaluasi.avg_skor'] ?? 0), 2) : null;
            $gap = $skorDibutuhkan !== null
                ? round($skorDibutuhkan - $skorLulus, 2)
                : null;

            return [
                'nama'            => $r['DimAlumni.nama']      ?? '',
                'nim'             => $nim,
                'nama_prodi'      => $r['DimProdi.nama_prodi'] ?? '',
                'jenjang'         => $r['DimProdi.jenjang']    ?? '',
                'tahun_lulus'     => $r['DimAlumni.tahun_lulus'] ?? '',
                'skor_lulus'      => $skorLulus,
                'skor_dibutuhkan' => $skorDibutuhkan,
                'gap'             => $gap,
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