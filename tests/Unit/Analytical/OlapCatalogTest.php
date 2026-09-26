<?php

namespace Tests\Unit\Analytical;

use App\Exceptions\BusinessException;
use App\Services\Analytical\OlapCatalog;
use PHPUnit\Framework\TestCase;

/**
 * Katalog adalah satu-satunya penjaga antara input pengguna dan Cube.js, jadi
 * yang diuji di sini bukan "apakah pesannya bagus" tapi apakah permintaan yang
 * salah benar-benar tidak bisa lewat.
 *
 * Katalog tiruan dipakai alih-alih config nyata supaya tes ini tidak ikut merah
 * setiap kali ada measure baru ditambahkan. Kesesuaian dengan config nyata
 * diuji terpisah di OlapCatalogConfigTest.
 */
class OlapCatalogTest extends TestCase
{
    private function catalog(): OlapCatalog
    {
        return new OlapCatalog([
            'limits' => [
                'max_dimensions' => 3,
                'max_measures'   => 5,
                'max_rows'       => 5000,
            ],
            'dimensions' => [
                'DimProdi' => [
                    'group'   => 'Program Studi',
                    'members' => [
                        'DimProdi.nama_prodi' => 'Nama Program Studi',
                        'DimProdi.jenjang'    => 'Jenjang',
                    ],
                ],
                'DimAlumni' => [
                    'group'   => 'Alumni',
                    'members' => ['DimAlumni.tahun_lulus' => 'Tahun Lulus'],
                ],
                'DimPerusahaan' => [
                    'group'   => 'Tempat Bekerja',
                    'members' => ['DimPerusahaan.nama_provinsi' => 'Provinsi Tempat Kerja'],
                ],
                'DimIndikatorEvaluasi' => [
                    'group'   => 'Indikator Evaluasi',
                    'members' => ['DimIndikatorEvaluasi.grup_gap' => 'Grup Kompetensi'],
                ],
            ],
            'cubes' => [
                'FactTracerStudy' => [
                    'label'       => 'Tracer Study',
                    'description' => '',
                    'dimensions'  => ['DimProdi', 'DimAlumni', 'DimPerusahaan'],
                    'measures'    => [
                        'FactTracerStudy.count_alumni' => [
                            'label' => 'Jumlah Alumni', 'format' => 'integer',
                        ],
                        'FactTracerStudy.avg_masa_tunggu_bekerja' => [
                            'label' => 'Rata-rata Masa Tunggu Kerja (bulan)', 'format' => 'decimal',
                        ],
                    ],
                ],
                'FactRangeEvaluasi' => [
                    'label'       => 'Evaluasi Kompetensi',
                    'description' => '',
                    'dimensions'  => ['DimProdi', 'DimAlumni', 'DimIndikatorEvaluasi'],
                    'measures'    => [
                        'FactRangeEvaluasi.avg_skor' => [
                            'label' => 'Rata-rata Skor', 'format' => 'decimal',
                        ],
                    ],
                ],
            ],
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function request(array $overrides = []): array
    {
        return array_merge([
            'cube'       => 'FactTracerStudy',
            'measures'   => ['FactTracerStudy.count_alumni'],
            'dimensions' => ['DimProdi.nama_prodi'],
            'filters'    => [],
        ], $overrides);
    }

    public function test_permintaan_yang_sah_diterima(): void
    {
        $this->catalog()->assertValidRequest($this->request());

        $this->addToAssertionCount(1);
    }

    public function test_menolak_cube_yang_tidak_dikenal(): void
    {
        $this->expectException(BusinessException::class);
        $this->expectExceptionCode(422);

        $this->catalog()->assertValidRequest($this->request(['cube' => 'FactKarangan']));
    }

    public function test_menolak_measure_yang_tidak_terdaftar(): void
    {
        $this->expectException(BusinessException::class);

        $this->catalog()->assertValidRequest($this->request([
            'measures' => ['FactTracerStudy.avg_gaji_impian'],
        ]));
    }

    public function test_menolak_dimensi_yang_tidak_terdaftar(): void
    {
        $this->expectException(BusinessException::class);

        $this->catalog()->assertValidRequest($this->request([
            'dimensions' => ['DimProdi.rahasia'],
        ]));
    }

    /**
     * DimWaktu dipakai backend sebagai filter snapshot internal, tapi tidak
     * boleh bisa diminta pengguna sebagai dimensi — inti dari aturan satu
     * sumbu tahun. Formatnya benar, jadi yang menolaknya harus katalog, bukan
     * kebetulan parsing.
     */
    public function test_menolak_dim_waktu_sebagai_dimensi(): void
    {
        $this->expectException(BusinessException::class);

        $this->catalog()->assertValidRequest($this->request([
            'dimensions' => ['DimWaktu.tahun_snapshot'],
        ]));
    }

    /**
     * Inti penjagaan grain: measure FactTracerStudy dipecah oleh dimensi yang
     * hanya ter-join ke fact evaluasi. Kalau ini lolos, Cube.js menyusun join
     * lintas fact dan satu alumni terhitung sebanyak indikator yang ia jawab.
     */
    public function test_menolak_dimensi_yang_tidak_ter_join_ke_cube_terpilih(): void
    {
        $this->expectException(BusinessException::class);

        $this->catalog()->assertValidRequest($this->request([
            'cube'       => 'FactTracerStudy',
            'dimensions' => ['DimIndikatorEvaluasi.grup_gap'],
        ]));
    }

    public function test_menolak_measure_milik_cube_lain(): void
    {
        $this->expectException(BusinessException::class);

        $this->catalog()->assertValidRequest($this->request([
            'cube'     => 'FactTracerStudy',
            'measures' => ['FactRangeEvaluasi.avg_skor'],
        ]));
    }

    public function test_dimensi_bersama_tetap_boleh_di_cube_evaluasi(): void
    {
        $this->catalog()->assertValidRequest([
            'cube'       => 'FactRangeEvaluasi',
            'measures'   => ['FactRangeEvaluasi.avg_skor'],
            'dimensions' => ['DimProdi.nama_prodi', 'DimIndikatorEvaluasi.grup_gap'],
            'filters'    => [],
        ]);

        $this->addToAssertionCount(1);
    }

    public function test_menolak_lebih_dari_tiga_dimensi(): void
    {
        $this->expectException(BusinessException::class);

        $this->catalog()->assertValidRequest($this->request([
            'dimensions' => [
                'DimProdi.nama_prodi',
                'DimProdi.jenjang',
                'DimAlumni.tahun_lulus',
                'DimPerusahaan.nama_provinsi',
            ],
        ]));
    }

    public function test_menolak_tanpa_measure(): void
    {
        $this->expectException(BusinessException::class);

        $this->catalog()->assertValidRequest($this->request(['measures' => []]));
    }

    public function test_menolak_dimensi_kembar(): void
    {
        $this->expectException(BusinessException::class);

        $this->catalog()->assertValidRequest($this->request([
            'dimensions' => ['DimProdi.nama_prodi', 'DimProdi.nama_prodi'],
        ]));
    }

    /**
     * Filter tunduk pada aturan join yang sama dengan dimensi. Kalau tidak,
     * pembatasan grain bisa ditembus lewat pintu filter.
     */
    public function test_menolak_filter_yang_tidak_ter_join_ke_cube_terpilih(): void
    {
        $this->expectException(BusinessException::class);

        $this->catalog()->assertValidRequest($this->request([
            'filters' => [
                ['member' => 'DimIndikatorEvaluasi.grup_gap', 'values' => ['Kognitif']],
            ],
        ]));
    }

    public function test_katalog_frontend_hanya_membawa_dimensi_milik_cube_itu(): void
    {
        $data = $this->catalog()->forFrontend();

        $tracer = collect($data['cubes'])->firstWhere('key', 'FactTracerStudy');
        $groups = collect($tracer['dimension_groups'])->pluck('group')->all();

        $this->assertContains('Program Studi', $groups);
        $this->assertContains('Tempat Bekerja', $groups);
        $this->assertNotContains('Indikator Evaluasi', $groups);
    }
}
