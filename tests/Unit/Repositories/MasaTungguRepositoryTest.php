<?php

namespace Tests\Unit\Repositories;

use App\Repositories\Analytical\MasaTungguRepository;
use App\Services\CubeJsClient;
use Mockery;
use ReflectionMethod;
use Tests\TestCase;

/**
 * buildRentangFilters() dan cepatCountsByGroup() bersifat `private`, jadi
 * diuji lewat Reflection -- pola ini dipilih daripada mengubah visibility
 * production code hanya demi testability (di luar cakupan tugas ini).
 */
class MasaTungguRepositoryTest extends TestCase
{
    private function repo(): MasaTungguRepository
    {
        return new MasaTungguRepository(Mockery::mock(CubeJsClient::class));
    }

    private function callBuildRentangFilters(MasaTungguRepository $repo, string $rentang, float $batas = 6.0): array
    {
        $method = new ReflectionMethod($repo, 'buildRentangFilters');
        $method->setAccessible(true);

        return $method->invoke($repo, $rentang, $batas);
    }

    /**
     * Bucket 'cepat' harus pakai gte 0 (BUKAN gt 0) -- masa tunggu persis
     * 0 bulan (sudah bekerja sebelum/saat lulus) tetap "cepat". Riwayat bug:
     * dulu gt 0 membuat angka "cepat" lebih rendah dari yang seharusnya.
     */
    public function test_bucket_cepat_menyertakan_masa_tunggu_nol_bulan(): void
    {
        $filters = $this->callBuildRentangFilters($this->repo(), 'cepat', 6.0);

        $gte = collect($filters)->first(
            fn ($f) => $f['member'] === 'FactTracerStudy.masa_tunggu_bekerja' && $f['operator'] === 'gte'
        );

        $this->assertNotNull($gte, "Filter 'gte' untuk masa_tunggu_bekerja harus ada di bucket 'cepat'");
        $this->assertSame(['0'], $gte['values']);
    }

    /** Ambang atas bucket 'cepat' mengikuti $batasCepatBulan yang dinamis, bukan hardcode 6. */
    public function test_bucket_cepat_memakai_ambang_dinamis(): void
    {
        $filters = $this->callBuildRentangFilters($this->repo(), 'cepat', 9.5);

        $lte = collect($filters)->first(
            fn ($f) => $f['member'] === 'FactTracerStudy.masa_tunggu_bekerja' && $f['operator'] === 'lte'
        );

        $this->assertSame(['9.5'], $lte['values']);
    }

    /**
     * Bucket histogram '0-3' TIDAK ikut berubah oleh ambang cepat dinamis --
     * granularitasnya tetap, terpisah dari definisi 'cepat'.
     */
    public function test_bucket_histogram_0_3_tidak_terpengaruh_ambang_cepat(): void
    {
        $filters = $this->callBuildRentangFilters($this->repo(), '0-3', 9.5);

        $lt = collect($filters)->first(
            fn ($f) => $f['member'] === 'FactTracerStudy.masa_tunggu_bekerja' && $f['operator'] === 'lt'
        );

        $this->assertSame(['3'], $lt['values']);
    }

    /** rentang di luar daftar yang dikenal -> array filter kosong, bukan exception/filter liar. */
    public function test_rentang_tidak_dikenal_menghasilkan_array_kosong(): void
    {
        $filters = $this->callBuildRentangFilters($this->repo(), 'rentang-ngasal');

        $this->assertSame([], $filters);
    }
    private const BEKERJA = ['Bekerja (full time / part time)', 'Wiraswasta'];

    /** Klien Cube palsu: catat query; baris balasan dipilih per jenis query. */
    private function repoWith(\Closure $rows, array &$sent): MasaTungguRepository
    {
        $cube = Mockery::mock(CubeJsClient::class);
        $cube->shouldReceive('load')->andReturnUsing(function (array $q) use ($rows, &$sent) {
            $sent[] = $q;
            return collect($rows($q));
        });

        return new MasaTungguRepository($cube);
    }

    private static function hanyaBekerja(array $q): bool
    {
        return collect($q['filters'])->contains(
            fn ($f) => ($f['member'] ?? null) === 'DimStatusAlumni.label' && ($f['values'] ?? null) === self::BEKERJA
        );
    }

    public function test_bar_penyebut_dan_rata_rata_hanya_dari_alumni_bekerja(): void
    {
        $sent = [];
        $grup = ['DimProdi.jenjang' => 'D3', 'DimProdi.jurusan' => 'AN', 'DimProdi.nama_prodi' => 'UPW', 'DimAlumni.tahun_lulus' => '2024'];
        $repo = $this->repoWith(function (array $q) use ($grup) {
            if (!self::hanyaBekerja($q)) {
                // Semua alumni, termasuk yang kuliah/mencari kerja bernilai 0.
                return [$grup + ['FactTracerStudy.count_alumni' => '245', 'FactTracerStudy.count_terserap' => '214',
                    'FactTracerStudy.avg_masa_tunggu_bekerja' => '1.9', 'FactTracerStudy.median_masa_tunggu_bekerja' => '1.1']];
            }
            $isCepat = collect($q['filters'])->contains(fn ($f) => ($f['operator'] ?? null) === 'lte');
            return [$grup + ($isCepat
                ? ['FactTracerStudy.count_alumni' => '182']
                : ['FactTracerStudy.count_alumni' => '194', 'FactTracerStudy.avg_masa_tunggu_bekerja' => '2.3',
                   'FactTracerStudy.median_masa_tunggu_bekerja' => '1.5'])];
        }, $sent);

        $row = $repo->getBarData()->first();

        $this->assertSame(245, $row['count_alumni']);
        $this->assertSame(194, $row['count_bekerja']);
        $this->assertSame(182, $row['count_masa_tunggu_cepat']);
        $this->assertSame(2.3, $row['avg_masa_tunggu_bekerja']);
        $this->assertSame(1.5, $row['median_masa_tunggu_bekerja']);
    }

    public function test_pola_pencarian_kerja_hanya_dari_alumni_bekerja(): void
    {
        $sent = [];
        $this->repoWith(fn () => [], $sent)->getPolaPencarianKerja();

        $this->assertTrue(self::hanyaBekerja($sent[0]));
        $this->assertFalse(collect($sent[0]['filters'])->contains(fn ($f) => ($f['member'] ?? null) === 'FactTracerStudy.perusahaan_sk'));
    }

    public function test_tren_median_hanya_dari_alumni_bekerja(): void
    {
        $sent = [];
        $this->repoWith(fn () => [], $sent)->getMedianTrendPerTahun();

        $this->assertTrue(self::hanyaBekerja($sent[0]));
    }
}
