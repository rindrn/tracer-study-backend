<?php

namespace App\Repositories\Analytical;

use Illuminate\Support\Collection;

class MetodePembelajaranRepository extends BaseAnalyticalRepository
{
    // Filter kategori: hanya MetodePembelajaran
    private const FILTER_KATEGORI = [
        'member'   => 'DimIndikatorEvaluasi.kategori_pertanyaan',
        'operator' => 'equals',
        'values'   => ['MetodePembelajaran'],
    ];

    /**
     *
     * @return Collection<array{kode_field, label, avg_skor, count}>
     */
    public function getMetodeData(
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

        return $this->cube->load([
            'measures'   => [
                'FactRangeEvaluasi.avg_skor',
                'FactRangeEvaluasi.count',
            ],
            'dimensions' => [
                'DimIndikatorEvaluasi.label_pertanyaan',
                'DimIndikatorEvaluasi.kode_field',
            ],
            'filters' => $filters,
            'order'   => [['DimIndikatorEvaluasi.kode_field', 'asc']],
        ])->map(fn($r) => [
            'kode_field' => $r['DimIndikatorEvaluasi.kode_field']       ?? '',
            'label'      => $r['DimIndikatorEvaluasi.label_pertanyaan'] ?? '',
            'avg_skor'   => (float) ($r['FactRangeEvaluasi.avg_skor']   ?? 0),
            'count'      => (int)   ($r['FactRangeEvaluasi.count']      ?? 0),
        ]);
    }

    /**
     *
     * @param  array<string>  $prodiFilter  Kosong = semua prodi.
     * @return Collection<array{kode_field, label, nama_prodi, jenjang, jurusan, tahun_lulus, avg_skor, count}>
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
                'DimIndikatorEvaluasi.kode_field',
                'DimIndikatorEvaluasi.label_pertanyaan',
                'DimProdi.jenjang',
                'DimProdi.jurusan',
                'DimProdi.nama_prodi',
                'DimAlumni.tahun_lulus',
            ],
            'filters' => $filters,
            'order'   => [
                ['DimProdi.nama_prodi',             'asc'],
                ['DimIndikatorEvaluasi.kode_field', 'asc'],
            ],
        ])->map(fn($r) => [
            'kode_field'  => $r['DimIndikatorEvaluasi.kode_field']       ?? '',
            'label'       => $r['DimIndikatorEvaluasi.label_pertanyaan'] ?? '',
            'nama_prodi'  => $r['DimProdi.nama_prodi']                   ?? '',
            'jenjang'     => $r['DimProdi.jenjang']                      ?? '',
            'jurusan'     => $r['DimProdi.jurusan']                      ?? '',
            'tahun_lulus' => $r['DimAlumni.tahun_lulus']                 ?? '',
            'avg_skor'    => (float) ($r['FactRangeEvaluasi.avg_skor']   ?? 0),
            'count'       => (int)   ($r['FactRangeEvaluasi.count']      ?? 0),
        ]);
    }

    /**
     * Drill-down: data individual alumni per metode yang diklik.
     * TIDAK pakai pre-agg — query ke FactRangeEvaluasi raw.
     * Berbeda dari Kompetensi Gap, di sini 1 baris per alumni per metode
     * (tidak perlu join A+B) — langsung dapat skor per alumni.
     *
     * @return array{data: array, page: int, per_page: int, total_on_page: int}
     */
    public function getDetailAlumni(
        string  $kodeField,
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
        $filters = array_merge(
            $this->buildGlobalFilters(
                jenjang:        $jenjang,
                jurusan:        $jurusan,
                idProdiIn:      $idProdiIn,
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
                'DimIndikatorEvaluasi.label_pertanyaan',
                'DimIndikatorEvaluasi.kode_field',
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
            'metode'      => $r['DimIndikatorEvaluasi.label_pertanyaan'] ?? '',
            'skor'        => round((float) ($r['FactRangeEvaluasi.avg_skor'] ?? 0), 2),
        ])->toArray();

        return [
            'data'          => $data,
            'page'          => $page,
            'per_page'      => $perPage,
            'total_on_page' => count($data),
        ];
    }
}