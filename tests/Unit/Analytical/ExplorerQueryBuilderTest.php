<?php

namespace Tests\Unit\Analytical;

use App\Services\Analytical\ExplorerQueryBuilder;
use PHPUnit\Framework\TestCase;

class ExplorerQueryBuilderTest extends TestCase
{
    private ExplorerQueryBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new ExplorerQueryBuilder();
    }

    /** Filter scope kaprodi seperti yang dihasilkan buildGlobalFilters(). */
    private function scopeKaprodi(): array
    {
        return [
            ['member' => 'DimProdi.jenjang',    'operator' => 'equals', 'values' => ['D3']],
            ['member' => 'DimProdi.nama_prodi', 'operator' => 'equals', 'values' => ['Teknik Informatika']],
        ];
    }

    private function request(array $overrides = []): array
    {
        return array_merge([
            'cube'       => 'FactTracerStudy',
            'measures'   => ['FactTracerStudy.count_alumni'],
            'dimensions' => ['DimProdi.nama_prodi'],
            'filters'    => [],
        ], $overrides);
    }

    private function membersOf(array $query): array
    {
        return array_column($query['filters'] ?? [], 'member');
    }

    public function test_menyusun_measure_dimensi_dan_urutan(): void
    {
        $query = $this->builder->build(
            $this->request([
                'dimensions' => ['DimProdi.nama_prodi', 'DimAlumni.tahun_lulus'],
            ]),
            [],
            5000,
        );

        $this->assertSame(['FactTracerStudy.count_alumni'], $query['measures']);
        $this->assertSame(
            ['DimProdi.nama_prodi', 'DimAlumni.tahun_lulus'],
            $query['dimensions'],
        );
        $this->assertSame(
            [['DimProdi.nama_prodi', 'asc'], ['DimAlumni.tahun_lulus', 'asc']],
            $query['order'],
        );
    }

    /**
     * Inti penjagaan scope. Kaprodi mengirim filter ke prodi lain lewat body;
     * filter scope miliknya harus TETAP terpasang. Karena Cube.js meng-AND
     * seluruh filter, hasilnya jadi irisan kosong — bukan data prodi lain.
     */
    public function test_filter_scope_tetap_terpasang_meski_body_mengirim_prodi_lain(): void
    {
        $query = $this->builder->build(
            $this->request([
                'filters' => [
                    ['member' => 'DimProdi.nama_prodi', 'values' => ['Teknik Mesin']],
                ],
            ]),
            $this->scopeKaprodi(),
            5000,
        );

        $scopeFilter = collect($query['filters'])
            ->firstWhere(fn ($f) => $f['member'] === 'DimProdi.nama_prodi'
                && $f['values'] === ['Teknik Informatika']);

        $this->assertNotNull(
            $scopeFilter,
            'Filter scope kaprodi hilang setelah body mengirim filter prodi lain.',
        );
        $this->assertContains('DimProdi.jenjang', $this->membersOf($query));
    }

    public function test_filter_scope_tidak_bisa_dihapus_dengan_body_kosong(): void
    {
        $query = $this->builder->build(
            $this->request(['filters' => []]),
            $this->scopeKaprodi(),
            5000,
        );

        $this->assertContains('DimProdi.nama_prodi', $this->membersOf($query));
        $this->assertContains('DimProdi.jenjang', $this->membersOf($query));
    }

    /**
     * Explorer TIDAK boleh memasang penyaring snapshot sendiri. Duplikasi
     * antar snapshot ditangani cube *Terkini di Cube.js; menyaring id_waktu di
     * atasnya justru membuang alumni yang tidak ikut run terakhir, karena ETL
     * berjalan inkremental.
     */
    public function test_tidak_pernah_memasang_filter_snapshot(): void
    {
        $query = $this->builder->build($this->request(), $this->scopeKaprodi(), 5000);

        $this->assertNotContains('DimWaktu.id_waktu', $this->membersOf($query));
    }

    /**
     * Filter tanpa nilai berarti "tanpa penyaring". Kalau diteruskan apa
     * adanya, `equals` dengan array kosong menghabiskan seluruh hasil dan
     * pengguna melihat tabel kosong tanpa penjelasan.
     */
    public function test_membuang_filter_tanpa_nilai(): void
    {
        $query = $this->builder->build(
            $this->request([
                'filters' => [
                    ['member' => 'DimProdi.jenjang', 'values' => []],
                    ['member' => 'DimAlumni.tahun_lulus', 'values' => ['2024']],
                ],
            ]),
            [],
            5000,
        );

        $this->assertNotContains('DimProdi.jenjang', $this->membersOf($query));
        $this->assertContains('DimAlumni.tahun_lulus', $this->membersOf($query));
    }

    public function test_limit_dibatasi_max_rows(): void
    {
        $query = $this->builder->build($this->request(['limit' => 999999]), [], 5000);

        $this->assertSame(5000, $query['limit']);
    }

    public function test_limit_kosong_memakai_max_rows(): void
    {
        $query = $this->builder->build($this->request(), [], 5000);

        $this->assertSame(5000, $query['limit']);
    }

    public function test_nilai_filter_dikirim_sebagai_teks(): void
    {
        $query = $this->builder->build(
            $this->request([
                'filters' => [['member' => 'DimAlumni.tahun_lulus', 'values' => [2024, '2023']]],
            ]),
            [],
            5000,
        );

        $filter = collect($query['filters'])->firstWhere('member', 'DimAlumni.tahun_lulus');

        $this->assertSame(['2024', '2023'], $filter['values']);
    }
}
