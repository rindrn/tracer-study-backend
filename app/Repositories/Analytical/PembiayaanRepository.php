<?php

namespace App\Repositories\Analytical;

use Illuminate\Support\Collection;

class PembiayaanRepository extends BaseAnalyticalRepository
{

    /**
     *
     * @return Collection<array{sumber_biaya, count}>
     */
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
            'dimensions' => ['DimAlumni.label_sumber_biaya_dipolban'],
            'filters'    => $filters,
            'order'      => [['FactTracerStudy.count_alumni', 'desc']],
        ])->map(fn($r) => [
            'sumber_biaya' => $r['DimAlumni.label_sumber_biaya_dipolban'] ?? '',
            'count'        => (int) ($r['FactTracerStudy.count_alumni']   ?? 0),
        ]);
    }

    /**
     *
     * @return Collection<array{tahun_lulus, sumber_biaya, count}>
     */
    public function getPerTahunData(
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
                'DimAlumni.label_sumber_biaya_dipolban',
                'DimAlumni.tahun_lulus',
            ],
            'filters' => $filters,
            'order'   => [
                ['DimAlumni.tahun_lulus',          'asc'],
                ['FactTracerStudy.count_alumni',   'desc'],
            ],
        ])->map(fn($r) => [
            'tahun_lulus'  => $r['DimAlumni.tahun_lulus']                    ?? '',
            'sumber_biaya' => $r['DimAlumni.label_sumber_biaya_dipolban']    ?? '',
            'count'        => (int) ($r['FactTracerStudy.count_alumni']       ?? 0),
        ]);
    }

    /**
     *
     * @return Collection<array{nama_prodi, jenjang, jurusan, sumber_biaya, count}>
     */
    public function getPerProdiData(
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
                'DimAlumni.label_sumber_biaya_dipolban',
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
            'nama_prodi'   => $r['DimProdi.nama_prodi']                      ?? '',
            'jenjang'      => $r['DimProdi.jenjang']                         ?? '',
            'jurusan'      => $r['DimProdi.jurusan']                         ?? '',
            'sumber_biaya' => $r['DimAlumni.label_sumber_biaya_dipolban']    ?? '',
            'count'        => (int) ($r['FactTracerStudy.count_alumni']       ?? 0),
        ]);
    }

    /**
     *
     * @param  array<string>  $prodiFilter  Kosong = semua prodi.
     * @return Collection<array{nama_prodi, jenjang, jurusan, sumber_biaya, count}>
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
                'DimAlumni.label_sumber_biaya_dipolban',
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
            'nama_prodi'   => $r['DimProdi.nama_prodi']                      ?? '',
            'jenjang'      => $r['DimProdi.jenjang']                         ?? '',
            'jurusan'      => $r['DimProdi.jurusan']                         ?? '',
            'sumber_biaya' => $r['DimAlumni.label_sumber_biaya_dipolban']    ?? '',
            'count'        => (int) ($r['FactTracerStudy.count_alumni']       ?? 0),
        ]);
    }

    /**
     * @param string|array<string>|null $sumberBiaya satu label, atau daftar label
     *        mentah untuk bucket gabungan seperti "Lainnya" di pie (label bucket
     *        itu sendiri tidak pernah ada di gudang data).
     */
    public function getDetailAlumni(
        string|array|null $sumberBiaya = null,
        ?string $jenjang        = null,
        ?string $jurusan        = null,
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
            namaProdi:      $namaProdi,
            tahunLulus:     $tahunLulus,
            mingguSnapshot: $mingguSnapshot,
        );

        // Cube.js: 'equals' dengan banyak nilai berperilaku seperti IN.
        $sumberValues = array_values(array_filter(
            array_map('strval', (array) ($sumberBiaya ?? [])),
            fn ($v) => $v !== '',
        ));
        if (! empty($sumberValues)) {
            $filters[] = [
                'member'   => 'DimAlumni.label_sumber_biaya_dipolban',
                'operator' => 'equals',
                'values'   => $sumberValues,
            ];
        }

        if ($search) {
            $filters[] = ['member' => 'DimAlumni.nama', 'operator' => 'contains', 'values' => [$search]];
        }

        $result = $this->cube->load([
            'measures'   => ['FactTracerStudy.count_alumni'],
            'dimensions' => [
                'DimAlumni.nama',
                'DimAlumni.nim',
                'DimProdi.nama_prodi',
                'DimProdi.jenjang',
                'DimAlumni.tahun_lulus',
                'DimAlumni.label_sumber_biaya_dipolban',
            ],
            'filters' => $filters,
            'order'   => [['DimAlumni.nama', 'asc']],
            'limit'   => $perPage,
            'offset'  => ($page - 1) * $perPage,
        ]);

        $data = $result->map(fn($r) => [
            'nama'         => $r['DimAlumni.nama']                            ?? '',
            'nim'          => $r['DimAlumni.nim']                             ?? '',
            'nama_prodi'   => $r['DimProdi.nama_prodi']                       ?? '',
            'jenjang'      => $r['DimProdi.jenjang']                          ?? '',
            'tahun_lulus'  => $r['DimAlumni.tahun_lulus']                     ?? '',
            'sumber_biaya' => $r['DimAlumni.label_sumber_biaya_dipolban']     ?? '',
        ])->toArray();

        return [
            'data'          => $data,
            'page'          => $page,
            'per_page'      => $perPage,
            'total_on_page' => count($data),
        ];
    }
}