<?php

namespace App\Repositories\Analytical;

use Illuminate\Support\Collection;

class KompetensiRepository extends BaseAnalyticalRepository
{
    // Filter kategori: hanya ambil Kompetensi_A dan Kompetensi_B
    private const FILTER_KATEGORI = [
        'member'   => 'DimIndikatorEvaluasi.kategori_pertanyaan',
        'operator' => 'in',
        'values'   => ['Kompetensi_A', 'Kompetensi_B'],
    ];

    /**
     *
     * @return Collection<array{kode_field, label, kategori, avg_skor, count}>
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

        return $this->cube->load([
            'measures'   => [
                'FactRangeEvaluasi.avg_skor',
                'FactRangeEvaluasi.count',
            ],
            'dimensions' => [
                'DimIndikatorEvaluasi.label_pertanyaan',
                'DimIndikatorEvaluasi.kode_field',
                'DimIndikatorEvaluasi.kategori_pertanyaan',
            ],
            'filters' => $filters,
            'order'   => [['DimIndikatorEvaluasi.kode_field', 'asc']],
        ])->map(fn($r) => [
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
     * @return Collection<array{kode_field, label, kategori, jenjang, jurusan, nama_prodi, tahun_lulus, avg_skor, count}>
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
                'DimIndikatorEvaluasi.kode_field',
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
                ['DimIndikatorEvaluasi.kode_field', 'asc'],
            ],
        ])->map(fn($r) => [
            'kode_field'  => $r['DimIndikatorEvaluasi.kode_field']          ?? '',
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
}