<?php

namespace Tests\Unit\Services;

use App\Exceptions\BusinessException;
use App\Repositories\Analytical\ExplorerRepository;
use App\Services\Analytical\ExplorerService;
use App\Services\CubeJsClient;
use Illuminate\Support\Collection;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * ExplorerService memakai repository ASLI di atas CubeJsClient tiruan, supaya
 * yang diuji adalah query Cube.js yang benar-benar terkirim — terutama dua
 * penyaring yang tidak boleh pernah hilang: scope role dan snapshot terbaru.
 */
class ExplorerServiceTest extends TestCase
{
    /** @var array<int, array> Query Cube.js yang terkirim, urut. */
    private array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();

        // WithCache selalu memakai store 'redis'; di tes diarahkan ke array
        // (tetap mendukung tags) supaya tidak butuh Redis menyala.
        config(['cache.stores.redis' => ['driver' => 'array']]);
        $this->sent = [];
    }

    /**
     * @param Collection|\Closure $queryRows  baris untuk setiap query, atau
     *                                        fn (array $query): Collection
     * @param array|null           $meta       isi /meta; null = Cube gagal
     */
    private function service(Collection|\Closure $queryRows = new Collection(), ?array $meta = []): ExplorerService
    {
        $cube = Mockery::mock(CubeJsClient::class);
        $cube->shouldReceive('load')->andReturnUsing(function (array $q) use ($queryRows) {
            $this->sent[] = $q;

            // Pencarian snapshot terbaru
            if (($q['dimensions'] ?? []) === ['DimWaktu.id_waktu']) {
                return collect([['DimWaktu.id_waktu' => 42, 'FactTracerStudy.count_alumni' => '10']]);
            }

            return $queryRows instanceof \Closure ? $queryRows($q) : $queryRows;
        });

        $meta === null
            ? $cube->shouldReceive('meta')->andThrow(new \RuntimeException('Cube mati'))
            : $cube->shouldReceive('meta')->andReturn($meta === [] ? self::meta() : $meta);

        return new ExplorerService(new ExplorerRepository($cube));
    }

    /** Potongan /meta: ukuran & rentang otomatis, plus member yang harus diabaikan. */
    private static function meta(): array
    {
        $agg = fn (string $name, string $fn, string $fnLabel, string $col, string $colLabel, string $format) => [
            'name' => $name,
            'meta' => ['kind' => 'agg', 'fn' => $fn, 'fn_label' => $fnLabel, 'column' => $col, 'column_label' => $colLabel, 'format' => $format],
        ];

        return [[
            'name'     => 'FactTracerStudy',
            'measures' => [
                ['name' => 'FactTracerStudy.count_alumni'],
                $agg('FactTracerStudy.agg_avg_take_home_pay', 'avg', 'Rata-rata', 'take_home_pay', 'Gaji', 'currency'),
                $agg('FactTracerStudy.agg_max_take_home_pay', 'max', 'Terbesar', 'take_home_pay', 'Gaji', 'currency'),
                $agg('FactTracerStudy.agg_avg_nilai_ump', 'avg', 'Rata-rata', 'nilai_ump', 'UMP provinsi tempat kerja', 'currency'),
                // Menyamar sebagai ukuran otomatis tapi bukan milik cube ini → diabaikan.
                $agg('DimAlumni.nim', 'avg', 'Rata-rata', 'nim', 'NIM', 'decimal'),
            ],
            'dimensions' => [
                ['name' => 'DimAlumni.nama'],
                [
                    'name' => 'FactTracerStudy.rentang_masa_tunggu_bekerja_3',
                    'meta' => ['kind' => 'bin', 'column' => 'masa_tunggu_bekerja', 'column_label' => 'Masa tunggu kerja (bulan)', 'width' => 3, 'width_label' => 'per 3 bulan'],
                ],
            ],
        ]];
    }

    private function body(array $override = []): array
    {
        return array_merge([
            'cube'       => 'FactTracerStudy',
            'measures'   => ['FactTracerStudy.count_alumni'],
            'dimensions' => ['DimProdi.jurusan'],
            'filters'    => [],
        ], $override);
    }

    private function lastQuery(): array
    {
        return end($this->sent);
    }

    private function filterOn(array $query, string $member): ?array
    {
        foreach ($query['filters'] ?? [] as $f) {
            if ($f['member'] === $member) {
                return $f;
            }
        }
        return null;
    }

    // ── Katalog ─────────────────────────────────────────────────────────

    public function test_katalog_tidak_memuat_kolom_identitas_alumni(): void
    {
        $json = json_encode($this->service()->catalog());

        $this->assertStringNotContainsString('DimAlumni.nama', $json);
        $this->assertStringNotContainsString('DimAlumni.nim', $json);
        $this->assertStringNotContainsString('company_name', $json);
    }

    public function test_katalog_berbentuk_seperti_yang_dibaca_frontend(): void
    {
        $catalog = $this->service()->catalog();

        $this->assertSame(['max_dimensions', 'max_measures', 'max_formulas', 'max_rows'], array_keys($catalog['limits']));
        $cube = $catalog['cubes'][0];
        $this->assertSame('FactTracerStudy', $cube['key']);
        $this->assertArrayHasKey('format', $cube['measures'][0]);
        $this->assertArrayHasKey('members', $cube['dimension_groups'][0]);
    }

    // ── Penyaring wajib ─────────────────────────────────────────────────

    public function test_query_selalu_dipatok_ke_snapshot_terbaru(): void
    {
        $this->service()->query($this->body(), []);

        $this->assertSame(['42'], $this->filterOn($this->lastQuery(), 'DimWaktu.id_waktu')['values']);
    }

    public function test_scope_kaprodi_ikut_terkirim_ke_cube(): void
    {
        $this->service()->query($this->body(), [
            'nama_prodi' => 'Teknik Informatika',
            'jenjang'    => 'D3',
            // Param lain dari query string tidak boleh ikut jadi penyaring.
            'tahun_lulus' => '2020',
        ]);

        $q = $this->lastQuery();
        $this->assertSame(['Teknik Informatika'], $this->filterOn($q, 'DimProdi.nama_prodi')['values']);
        $this->assertSame(['D3'], $this->filterOn($q, 'DimProdi.jenjang')['values']);
        $this->assertNull($this->filterOn($q, 'DimAlumni.tahun_lulus'));
    }

    public function test_scope_kajur_dikirim_sebagai_daftar_id_prodi(): void
    {
        $this->service()->query($this->body(), ['id_prodi_in' => [3, 7]]);

        $this->assertSame(['3', '7'], $this->filterOn($this->lastQuery(), 'DimProdi.id_prodi')['values']);
    }

    public function test_hasil_berbeda_scope_tidak_berbagi_cache(): void
    {
        $service = $this->service();

        $service->query($this->body(), ['nama_prodi' => 'Akuntansi', 'jenjang' => 'D3']);
        $service->query($this->body(), ['nama_prodi' => 'Akuntansi', 'jenjang' => 'D4']);

        $this->assertSame(['D4'], $this->filterOn($this->lastQuery(), 'DimProdi.jenjang')['values']);
    }

    public function test_filter_pengguna_dikirim_sebagai_equals(): void
    {
        $this->service()->query($this->body([
            'filters' => [
                ['member' => 'DimAlumni.tahun_lulus', 'values' => ['2022', '2023']],
                ['member' => 'DimProdi.jenjang', 'values' => []], // kosong = diabaikan
            ],
        ]), []);

        $q = $this->lastQuery();
        $this->assertSame(
            ['member' => 'DimAlumni.tahun_lulus', 'operator' => 'equals', 'values' => ['2022', '2023']],
            $this->filterOn($q, 'DimAlumni.tahun_lulus'),
        );
        $this->assertNull($this->filterOn($q, 'DimProdi.jenjang'));
    }

    // ── Validasi terhadap katalog ───────────────────────────────────────

    #[DataProvider('permintaanTerlarang')]
    public function test_permintaan_di_luar_katalog_ditolak_422(array $override): void
    {
        try {
            $this->service()->query($this->body($override), []);
            $this->fail('Seharusnya ditolak');
        } catch (BusinessException $e) {
            $this->assertSame(422, $e->getCode());
        }

        // Tidak satu pun query sampai ke Cube.js.
        $this->assertSame([], $this->sent);
    }

    public static function permintaanTerlarang(): array
    {
        return [
            'cube asing'         => [['cube' => 'orders']],
            'measure asing'      => [['measures' => ['FactTracerStudy.id_fact']]],
            'dimensi PII'        => [['dimensions' => ['DimAlumni.nim']]],
            'filter PII'         => [['filters' => [['member' => 'DimAlumni.nama', 'values' => ['x']]]]],
            'dimensi dobel'      => [['dimensions' => ['DimProdi.jurusan', 'DimProdi.jurusan']]],
            'terlalu banyak dim' => [['dimensions' => [
                'DimProdi.jurusan', 'DimProdi.jenjang', 'DimAlumni.tahun_lulus', 'DimStatusAlumni.label',
            ]]],
            'tanpa measure'      => [['measures' => []]],
        ];
    }

    // ── Bentuk hasil ────────────────────────────────────────────────────

    public function test_hasil_mengikuti_urutan_permintaan_dan_menandai_terpotong(): void
    {
        config(['olap_catalog.limits.max_rows' => 2]);

        $rows = collect([
            ['DimProdi.jurusan' => 'A', 'FactTracerStudy.count_alumni' => '1', 'FactTracerStudy.avg_take_home_pay' => '5'],
            ['DimProdi.jurusan' => 'B', 'FactTracerStudy.count_alumni' => '2', 'FactTracerStudy.avg_take_home_pay' => '6'],
            ['DimProdi.jurusan' => 'C', 'FactTracerStudy.count_alumni' => '3', 'FactTracerStudy.avg_take_home_pay' => '7'],
        ]);

        $result = $this->service($rows)->query($this->body([
            'measures' => ['FactTracerStudy.avg_take_home_pay', 'FactTracerStudy.count_alumni'],
        ]), []);

        $this->assertSame(
            ['FactTracerStudy.avg_take_home_pay', 'FactTracerStudy.count_alumni'],
            array_column($result['measures'], 'key'),
        );
        $this->assertSame('currency', $result['measures'][0]['format']);
        $this->assertSame([['key' => 'DimProdi.jurusan', 'label' => 'Jurusan']], $result['dimensions']);
        $this->assertSame(2, $result['row_count']);
        $this->assertTrue($result['truncated']);

        // Minta max_rows + 1 ke Cube.js untuk mendeteksi pemotongan.
        $this->assertSame(3, $this->lastQuery()['limit']);
    }

    // ── Katalog otomatis dari /meta ─────────────────────────────────────

    private function tracerCube(array $catalog): array
    {
        return collect($catalog['cubes'])->firstWhere('key', 'FactTracerStudy');
    }

    public function test_kolom_angka_dan_rentang_dibaca_dari_meta_cube(): void
    {
        $cube = $this->tracerCube($this->service()->catalog());

        $gaji = collect($cube['numeric_columns'])->firstWhere('column', 'take_home_pay');
        $this->assertSame('Gaji', $gaji['label']);
        $this->assertSame(['avg', 'max'], array_column($gaji['functions'], 'fn'));

        $rentang = collect($cube['dimension_groups'])->firstWhere('group', 'Rentang angka');
        $this->assertSame(
            [['key' => 'FactTracerStudy.rentang_masa_tunggu_bekerja_3', 'label' => 'Masa tunggu kerja per 3 bulan']],
            $rentang['members'],
        );

        // Ukuran otomatis tidak dicampur ke daftar "siap pakai".
        $this->assertNotContains('FactTracerStudy.agg_avg_take_home_pay', array_column($cube['measures'], 'key'));
    }

    public function test_member_meta_milik_cube_lain_diabaikan(): void
    {
        $json = json_encode($this->service()->catalog());
        $this->assertStringNotContainsString('DimAlumni.nim', $json);

        $this->expectException(BusinessException::class);
        $this->service()->query($this->body(['measures' => ['DimAlumni.nim']]), []);
    }

    public function test_katalog_tetap_jalan_saat_cube_tidak_bisa_dihubungi(): void
    {
        $cube = $this->tracerCube($this->service(meta: null)->catalog());

        $this->assertSame([], $cube['numeric_columns']);
        $this->assertContains('FactTracerStudy.count_alumni', array_column($cube['measures'], 'key'));
    }

    public function test_ukuran_otomatis_dan_rentang_bisa_diminta_dengan_label_yang_ramah(): void
    {
        $result = $this->service()->query($this->body([
            'measures'   => ['FactTracerStudy.agg_avg_nilai_ump', 'FactTracerStudy.agg_avg_take_home_pay'],
            'dimensions' => ['FactTracerStudy.rentang_masa_tunggu_bekerja_3'],
        ]), []);

        $this->assertSame(['Rata-rata UMP provinsi tempat kerja', 'Rata-rata gaji'], array_column($result['measures'], 'label'));
        $this->assertSame('currency', $result['measures'][0]['format']);
        $this->assertSame('Masa tunggu kerja per 3 bulan', $result['dimensions'][0]['label']);
    }

    // ── Rumus ───────────────────────────────────────────────────────────

    private function multiplier(array $override = []): array
    {
        return array_merge([
            'key' => 'rumus_1', 'label' => 'Kelipatan gaji terhadap UMP',
            'left' => 'FactTracerStudy.agg_avg_take_home_pay', 'op' => 'div',
            'right' => 'FactTracerStudy.agg_avg_nilai_ump', 'format' => 'ratio',
        ], $override);
    }

    public function test_rumus_dihitung_dari_hasil_agregasi_dan_ukuran_bantu_disembunyikan(): void
    {
        $rows = collect([
            ['DimProdi.jurusan' => 'A', 'FactTracerStudy.agg_avg_take_home_pay' => '9000000', 'FactTracerStudy.agg_avg_nilai_ump' => '3000000'],
            ['DimProdi.jurusan' => 'B', 'FactTracerStudy.agg_avg_take_home_pay' => '5000000', 'FactTracerStudy.agg_avg_nilai_ump' => '0'],
            ['DimProdi.jurusan' => 'C', 'FactTracerStudy.agg_avg_take_home_pay' => null, 'FactTracerStudy.agg_avg_nilai_ump' => '2000000'],
        ]);

        $result = $this->service($rows)->query($this->body([
            'measures' => [],
            'formulas' => [$this->multiplier()],
        ]), []);

        // Pembilang & penyebut ikut diminta ke Cube...
        $this->assertEqualsCanonicalizing(
            ['FactTracerStudy.agg_avg_take_home_pay', 'FactTracerStudy.agg_avg_nilai_ump'],
            $this->lastQuery()['measures'],
        );
        // ...tapi yang ditampilkan hanya rumusnya.
        $this->assertSame(['rumus_1'], array_column($result['measures'], 'key'));
        $this->assertSame('ratio', $result['measures'][0]['format']);
        $this->assertSame('Rata-rata gaji ÷ rata-rata UMP provinsi tempat kerja', $result['measures'][0]['description']);
        $this->assertSame('currency', $result['measures'][0]['formula']['left_format']);

        // Bagi nol dan nilai kosong → null, bukan 0.
        $this->assertSame([3.0, null, null], array_column($result['rows'], 'rumus_1'));
    }

    public function test_rumus_persen_dikali_seratus(): void
    {
        $rows = collect([['FactTracerStudy.count_terserap' => '75', 'FactTracerStudy.count_alumni' => '100']]);

        $result = $this->service($rows)->query($this->body([
            'measures'   => ['FactTracerStudy.count_alumni'],
            'dimensions' => [],
            'formulas'   => [$this->multiplier([
                'label' => 'Tingkat keterserapan', 'left' => 'FactTracerStudy.count_terserap',
                'right' => 'FactTracerStudy.count_alumni', 'format' => 'percent',
            ])],
        ]), []);

        $this->assertSame(75.0, $result['rows'][0]['rumus_1']);
        $this->assertSame(['FactTracerStudy.count_alumni', 'rumus_1'], array_column($result['measures'], 'key'));
    }

    #[DataProvider('rumusTerlarang')]
    public function test_rumus_tidak_sah_ditolak(array $override): void
    {
        try {
            $this->service()->query($this->body(['formulas' => [$this->multiplier($override)]]), []);
            $this->fail('Seharusnya ditolak');
        } catch (BusinessException $e) {
            $this->assertSame(422, $e->getCode());
        }

        $this->assertSame([], array_filter($this->sent, fn ($q) => ($q['dimensions'] ?? []) !== ['DimWaktu.id_waktu']));
    }

    public static function rumusTerlarang(): array
    {
        return [
            'operator asing'           => [['op' => 'pow']],
            'ukuran PII'               => [['left' => 'DimAlumni.nim']],
            'persen pada pengurangan'  => [['op' => 'sub', 'format' => 'percent']],
            'tanpa nama'               => [['label' => '  ']],
            'kunci bebas'              => [['key' => 'DimAlumni.nama']],
        ];
    }

    // ── Olah hasil ──────────────────────────────────────────────────────

    public function test_kelompok_dengan_responden_kurang_dari_n_disembunyikan(): void
    {
        $rows = collect([
            ['DimProdi.jurusan' => 'A', 'FactTracerStudy.agg_avg_take_home_pay' => '9000000', 'FactTracerStudy.count_alumni' => '120'],
            ['DimProdi.jurusan' => 'B', 'FactTracerStudy.agg_avg_take_home_pay' => '12000000', 'FactTracerStudy.count_alumni' => '8'],
        ]);

        $result = $this->service($rows)->query($this->body([
            'measures' => ['FactTracerStudy.agg_avg_take_home_pay'],
            'min_n'    => 30,
        ]), []);

        $this->assertContains('FactTracerStudy.count_alumni', $this->lastQuery()['measures']);
        $this->assertSame(['A'], array_column($result['rows'], 'DimProdi.jurusan'));
        // Cacah bantu tidak ikut ditampilkan.
        $this->assertSame(['FactTracerStudy.agg_avg_take_home_pay'], array_column($result['measures'], 'key'));
    }

    public function test_urutkan_dan_ambil_teratas_tanpa_dimensi_kolom(): void
    {
        $rows = collect([
            ['DimProdi.jurusan' => 'A', 'FactTracerStudy.count_alumni' => '10'],
            ['DimProdi.jurusan' => 'B', 'FactTracerStudy.count_alumni' => null],
            ['DimProdi.jurusan' => 'C', 'FactTracerStudy.count_alumni' => '30'],
            ['DimProdi.jurusan' => 'D', 'FactTracerStudy.count_alumni' => '20'],
        ]);

        $sorted = fn (array $sort) => array_column($this->service($rows)->query($this->body(['sort' => $sort]), [])['rows'], 'DimProdi.jurusan');

        $this->assertSame(['C', 'D', 'A', 'B'], $sorted(['by' => 'FactTracerStudy.count_alumni', 'direction' => 'desc']));
        $this->assertSame(['A', 'D', 'C', 'B'], $sorted(['by' => 'FactTracerStudy.count_alumni', 'direction' => 'asc']));
        $this->assertSame(['C', 'D'], $sorted(['by' => 'FactTracerStudy.count_alumni', 'direction' => 'desc', 'limit' => 2]));
    }

    public function test_urutan_dengan_dimensi_kolom_memakai_peringkat_per_kelompok_baris(): void
    {
        $router = function (array $q) {
            // Query peringkat: dikelompokkan menurut dimensi baris saja.
            if ($q['dimensions'] === ['DimProdi.jurusan']) {
                return collect([
                    ['DimProdi.jurusan' => 'A', 'FactTracerStudy.agg_avg_take_home_pay' => '7000000'],
                    ['DimProdi.jurusan' => 'B', 'FactTracerStudy.agg_avg_take_home_pay' => '9000000'],
                    ['DimProdi.jurusan' => 'C', 'FactTracerStudy.agg_avg_take_home_pay' => '8000000'],
                ]);
            }

            return collect([
                ['DimProdi.jurusan' => 'A', 'DimAlumni.tahun_lulus' => '2020', 'FactTracerStudy.agg_avg_take_home_pay' => '9900000'],
                ['DimProdi.jurusan' => 'A', 'DimAlumni.tahun_lulus' => '2021', 'FactTracerStudy.agg_avg_take_home_pay' => '1000000'],
                ['DimProdi.jurusan' => 'B', 'DimAlumni.tahun_lulus' => '2020', 'FactTracerStudy.agg_avg_take_home_pay' => '9000000'],
                ['DimProdi.jurusan' => 'C', 'DimAlumni.tahun_lulus' => '2021', 'FactTracerStudy.agg_avg_take_home_pay' => '8000000'],
            ]);
        };

        $result = $this->service($router)->query($this->body([
            'measures'         => ['FactTracerStudy.agg_avg_take_home_pay'],
            'dimensions'       => ['DimProdi.jurusan', 'DimAlumni.tahun_lulus'],
            'column_dimension' => 'DimAlumni.tahun_lulus',
            'sort'             => ['by' => 'FactTracerStudy.agg_avg_take_home_pay', 'direction' => 'desc', 'limit' => 2],
        ]), []);

        // B (9 jt) lalu C (8 jt) — A tersingkir walau salah satu selnya 9,9 jt.
        $this->assertSame(['B', 'C'], array_column($result['rows'], 'DimProdi.jurusan'));
    }

    public function test_urutan_hanya_boleh_memakai_ukuran_yang_ditampilkan(): void
    {
        $this->expectException(BusinessException::class);

        $this->service()->query($this->body(['sort' => ['by' => 'FactTracerStudy.count_terserap', 'direction' => 'desc']]), []);
    }

    public function test_saringan_ada_nilainya_dan_bukan(): void
    {
        $this->service()->query($this->body(['filters' => [
            ['member' => 'FactTracerStudy.rentang_masa_tunggu_bekerja_3', 'operator' => 'set', 'values' => []],
            ['member' => 'DimProdi.jenjang', 'operator' => 'notEquals', 'values' => ['D3']],
            ['member' => 'DimAlumni.tahun_lulus', 'operator' => 'equals', 'values' => []],
        ]]), []);

        $q = $this->lastQuery();
        $this->assertSame(['member' => 'FactTracerStudy.rentang_masa_tunggu_bekerja_3', 'operator' => 'set'], $this->filterOn($q, 'FactTracerStudy.rentang_masa_tunggu_bekerja_3'));
        $this->assertSame(['member' => 'DimProdi.jenjang', 'operator' => 'notEquals', 'values' => ['D3']], $this->filterOn($q, 'DimProdi.jenjang'));
        $this->assertNull($this->filterOn($q, 'DimAlumni.tahun_lulus'));
    }

    // ── Drill-down ──────────────────────────────────────────────────────

    private function drill(array $override = [], array $scope = [], ?Collection $rows = null): array
    {
        return $this->service($rows ?? new Collection())->drillDown(array_merge([
            'cube'    => 'FactTracerStudy',
            'measure' => 'FactTracerStudy.count_terserap',
            'filters' => [
                ['member' => 'DimProdi.jurusan', 'values' => ['Akuntansi']],
                ['member' => 'DimAlumni.tahun_lulus', 'values' => ['2022']],
            ],
        ], $override), $scope);
    }

    public function test_drill_down_menyaring_titik_scope_snapshot_dan_measure_cacah(): void
    {
        $this->drill([], ['nama_prodi' => 'Akuntansi', 'jenjang' => 'D3']);

        $q = $this->lastQuery();
        $this->assertSame(['Akuntansi'], $this->filterOn($q, 'DimProdi.jurusan')['values']);
        $this->assertSame(['2022'], $this->filterOn($q, 'DimAlumni.tahun_lulus')['values']);
        $this->assertSame(['D3'], $this->filterOn($q, 'DimProdi.jenjang')['values']);
        $this->assertSame(['42'], $this->filterOn($q, 'DimWaktu.id_waktu')['values']);

        // Hanya alumni yang ikut terhitung measure yang diklik.
        $this->assertSame(
            ['member' => 'FactTracerStudy.count_terserap', 'operator' => 'gt', 'values' => ['0']],
            $this->filterOn($q, 'FactTracerStudy.count_terserap'),
        );
        $this->assertContains('DimAlumni.nama', $q['dimensions']);
    }

    public function test_drill_down_measure_rata_rata_hanya_alumni_yang_punya_nilai(): void
    {
        $this->drill(['measure' => 'FactTracerStudy.avg_take_home_pay']);

        $this->assertSame(
            ['member' => 'FactTracerStudy.avg_take_home_pay', 'operator' => 'set'],
            $this->filterOn($this->lastQuery(), 'FactTracerStudy.avg_take_home_pay'),
        );
    }

    public function test_drill_down_berhalaman_dan_mencari_nama_atau_nim(): void
    {
        $this->drill(['page' => 3, 'per_page' => 20, 'search' => ' budi ']);

        $q = $this->lastQuery();
        $this->assertSame(20, $q['limit']);
        $this->assertSame(40, $q['offset']);

        $or = array_values(array_filter($q['filters'], fn ($f) => isset($f['or'])))[0]['or'];
        $this->assertSame(['DimAlumni.nama', 'DimAlumni.nim'], array_column($or, 'member'));
        $this->assertSame(['budi'], $or[0]['values']);
    }

    public function test_drill_down_membentuk_baris_untuk_modal(): void
    {
        $result = $this->drill(rows: collect([[
            'DimAlumni.id_alumni' => 7, 'DimAlumni.nama' => 'Budi', 'DimAlumni.nim' => '201',
            'DimProdi.nama_prodi' => 'Akuntansi', 'DimProdi.jenjang' => 'D3',
            'DimAlumni.tahun_lulus' => '2022', 'DimStatusAlumni.label' => 'Bekerja',
            'FactTracerStudy.count_terserap' => '1',
        ]]));

        $this->assertSame([[
            'nama' => 'Budi', 'nim' => '201', 'nama_prodi' => 'Akuntansi', 'jenjang' => 'D3',
            'tahun_lulus' => '2022', 'status' => 'Bekerja', 'nilai' => '1',
        ]], $result['data']);
        $this->assertSame(1, $result['pagination']['total_on_page']);
        $this->assertSame('integer', $result['measure']['format']);
    }

    public function test_drill_down_kompetensi_tanpa_kolom_status(): void
    {
        $this->drill([
            'cube'    => 'FactRangeEvaluasi',
            'measure' => 'FactRangeEvaluasi.count',
            'filters' => [['member' => 'DimIndikatorEvaluasi.grup_gap', 'values' => ['Etika']]],
        ]);

        $dims = $this->lastQuery()['dimensions'];
        $this->assertContains('DimAlumni.nama', $dims);
        $this->assertNotContains('DimStatusAlumni.label', $dims);
    }

    public function test_drill_down_tetap_menolak_saringan_pii(): void
    {
        try {
            $this->drill(['filters' => [['member' => 'DimAlumni.nim', 'values' => ['201']]]]);
            $this->fail('Seharusnya ditolak');
        } catch (BusinessException $e) {
            $this->assertSame(422, $e->getCode());
        }

        $this->assertSame([], $this->sent);
    }

    // ── Nilai dimensi ───────────────────────────────────────────────────

    public function test_nilai_dimensi_tersaring_scope_dan_tanpa_nilai_kosong(): void
    {
        $rows = collect([
            ['DimProdi.nama_prodi' => 'Akuntansi'],
            ['DimProdi.nama_prodi' => null],
            ['DimProdi.nama_prodi' => 'Teknik Sipil'],
        ]);

        $result = $this->service($rows)->dimensionValues('DimProdi.nama_prodi', ['id_prodi_in' => [1]]);

        $this->assertSame(['Akuntansi', 'Teknik Sipil'], $result['values']);
        $this->assertNotNull($this->filterOn($this->lastQuery(), 'DimProdi.id_prodi'));
        $this->assertNotNull($this->filterOn($this->lastQuery(), 'DimWaktu.id_waktu'));
    }

    public function test_nilai_dimensi_di_luar_katalog_ditolak(): void
    {
        $this->expectException(BusinessException::class);

        $this->service()->dimensionValues('DimAlumni.nim', []);
    }
}
