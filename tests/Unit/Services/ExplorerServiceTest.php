<?php

namespace Tests\Unit\Services;

use App\Exceptions\BusinessException;
use App\Repositories\Analytical\ExplorerRepository;
use App\Services\Analytical\ExplorerService;
use App\Services\CubeJsClient;
use Illuminate\Support\Collection;
use Mockery;
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

    private function service(Collection $queryRows = new Collection()): ExplorerService
    {
        $cube = Mockery::mock(CubeJsClient::class);
        $cube->shouldReceive('load')->andReturnUsing(function (array $q) use ($queryRows) {
            $this->sent[] = $q;

            // Pencarian snapshot terbaru
            if (($q['dimensions'] ?? []) === ['DimWaktu.id_waktu']) {
                return collect([['DimWaktu.id_waktu' => 42, 'FactTracerStudy.count_alumni' => '10']]);
            }

            return $queryRows;
        });

        return new ExplorerService(new ExplorerRepository($cube));
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

        $this->assertSame(['max_dimensions', 'max_measures', 'max_rows'], array_keys($catalog['limits']));
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

    /** @dataProvider permintaanTerlarang */
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
