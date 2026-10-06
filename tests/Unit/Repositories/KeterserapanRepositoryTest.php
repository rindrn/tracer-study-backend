<?php

namespace Tests\Unit\Repositories;

use App\Repositories\Analytical\KeterserapanRepository;
use App\Services\CubeJsClient;
use Illuminate\Support\Collection;
use Mockery;
use ReflectionMethod;
use Tests\TestCase;

/**
 * reshapePerProdi() bersifat `private`, murni transformasi data (tidak
 * memanggil Cube.js), diuji lewat Reflection.
 */
class KeterserapanRepositoryTest extends TestCase
{
    private function repo(): KeterserapanRepository
    {
        return new KeterserapanRepository(Mockery::mock(CubeJsClient::class));
    }

    /** @return array Isi key 'chart' saja -- reshapePerProdi() sebenarnya mengembalikan ['chart', 'table', 'prodi_list']. */
    private function callReshape(array $raw, array $prodiFilter = []): array
    {
        $method = new ReflectionMethod(KeterserapanRepository::class, 'reshapePerProdi');
        $method->setAccessible(true);

        return $method->invoke($this->repo(), new Collection($raw), $prodiFilter)['chart'];
    }

    public function test_persentase_status_dihitung_terhadap_total_prodi(): void
    {
        $raw = [
            ['jenjang' => 'D4', 'jurusan' => 'Teknik Elektro', 'nama_prodi' => 'Teknik Informatika', 'status' => 'Bekerja', 'count' => 30, 'tahun_lulus' => '2024'],
            ['jenjang' => 'D4', 'jurusan' => 'Teknik Elektro', 'nama_prodi' => 'Teknik Informatika', 'status' => 'Belum Bekerja', 'count' => 10, 'tahun_lulus' => '2024'],
        ];

        $chart = $this->callReshape($raw);

        $this->assertSame(40, $chart[0]['total']);
        $bekerja = collect($chart[0]['statuses'])->firstWhere('label', 'Bekerja');
        $this->assertSame(75.0, $bekerja['pct']);
    }

    public function test_filter_prodi_cocok_lewat_substring_nama_display(): void
    {
        $raw = [
            ['jenjang' => 'D4', 'jurusan' => 'Teknik Elektro', 'nama_prodi' => 'Teknik Informatika', 'status' => 'Bekerja', 'count' => 5, 'tahun_lulus' => '2024'],
            ['jenjang' => 'D3', 'jurusan' => 'Ekonomi', 'nama_prodi' => 'Akuntansi', 'status' => 'Bekerja', 'count' => 5, 'tahun_lulus' => '2024'],
        ];

        $chart = $this->callReshape($raw, ['informatika']);

        $this->assertCount(1, $chart);
        $this->assertSame('Teknik Informatika', $chart[0]['nama_prodi']);
    }

    public function test_filter_prodi_tidak_cocok_menghasilkan_chart_kosong(): void
    {
        $raw = [
            ['jenjang' => 'D4', 'jurusan' => 'Teknik Elektro', 'nama_prodi' => 'Teknik Informatika', 'status' => 'Bekerja', 'count' => 5, 'tahun_lulus' => '2024'],
        ];

        $chart = $this->callReshape($raw, ['akuntansi']);

        $this->assertSame([], $chart);
    }

    public function test_nama_prodi_kosong_fallback_ke_jenjang_plus_jurusan(): void
    {
        $raw = [
            ['jenjang' => 'D3', 'jurusan' => 'Pariwisata', 'nama_prodi' => '', 'status' => 'Bekerja', 'count' => 5, 'tahun_lulus' => '2024'],
        ];

        $chart = $this->callReshape($raw);

        $this->assertSame('D3 Pariwisata', $chart[0]['nama_prodi']);
    }

    public function test_per_tahun_dikelompokkan_dan_persentase_dihitung_per_tahun_masing_masing(): void
    {
        $raw = [
            ['jenjang' => 'D4', 'jurusan' => 'TE', 'nama_prodi' => 'TI', 'status' => 'Bekerja', 'count' => 8, 'tahun_lulus' => '2023'],
            ['jenjang' => 'D4', 'jurusan' => 'TE', 'nama_prodi' => 'TI', 'status' => 'Belum Bekerja', 'count' => 2, 'tahun_lulus' => '2023'],
            ['jenjang' => 'D4', 'jurusan' => 'TE', 'nama_prodi' => 'TI', 'status' => 'Bekerja', 'count' => 20, 'tahun_lulus' => '2024'],
        ];

        $chart = $this->callReshape($raw);
        $perTahun = collect($chart[0]['per_tahun'])->keyBy('tahun_lulus');

        $this->assertSame(80.0, collect($perTahun['2023']['statuses'])->firstWhere('label', 'Bekerja')['pct']);
        $this->assertSame(100.0, collect($perTahun['2024']['statuses'])->firstWhere('label', 'Bekerja')['pct']);
    }
}
