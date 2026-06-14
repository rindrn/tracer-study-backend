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
}