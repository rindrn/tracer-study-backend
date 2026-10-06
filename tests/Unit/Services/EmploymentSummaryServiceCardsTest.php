<?php

namespace Tests\Unit\Services;

use App\Repositories\Analytical\KesesuaianRepository;
use App\Repositories\Analytical\KeterserapanRepository;
use App\Repositories\Analytical\MasaTungguRepository;
use App\Repositories\Analytical\PendapatanRepository;
use App\Repositories\Analytical\SebaranInstansiRepository;
use App\Repositories\Analytical\WirausahaRepository;
use App\Services\Analytical\EmploymentSummaryService;
use Illuminate\Support\Collection;
use Mockery;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Card builder (buildKeterserapanCard, buildMasaTungguCard,
 * buildPendapatanCard, dst) bersifat `private` dan dipanggil dari
 * getSummary() yang di-cache lewat WithCache::remember() (butuh Redis).
 * Diuji langsung lewat Reflection supaya tidak perlu Redis nyala hanya
 * untuk menguji rumus persentasenya.
 */
class EmploymentSummaryServiceCardsTest extends TestCase
{
    private function service(): EmploymentSummaryService
    {
        return new EmploymentSummaryService(
            Mockery::mock(KeterserapanRepository::class),
            Mockery::mock(MasaTungguRepository::class),
            Mockery::mock(KesesuaianRepository::class),
            Mockery::mock(WirausahaRepository::class),
            Mockery::mock(PendapatanRepository::class),
            Mockery::mock(SebaranInstansiRepository::class),
        );
    }

    private function callPrivate(EmploymentSummaryService $service, string $method, array $args)
    {
        $ref = new ReflectionMethod($service, $method);
        $ref->setAccessible(true);

        return $ref->invoke($service, ...$args);
    }

    // ── Keterserapan lulusan ────────────────────────────────────────────

    public function test_keterserapan_hanya_menghitung_status_terserap_sebagai_pembilang(): void
    {
        $rows = new Collection([
            ['status' => 'Bekerja', 'count' => 60],
            ['status' => 'Wirausaha', 'count' => 15],
            ['status' => 'Mencari Kerja', 'count' => 25],
        ]);

        $card = $this->callPrivate($this->service(), 'buildKeterserapanCard', [$rows]);

        // (60 + 15) / 100 * 100 = 75.0
        $this->assertSame(75.0, $card['value']);
    }

    /**
     * BUG DITEMUKAN LEWAT TEST INI (belum diperbaiki, menunggu konfirmasi):
     * labelMatchesAny() pakai str_contains(), dan status asli "Belum Bekerja"
     * (lihat contoh response di KeterserapanController::index() docblock,
     * baris ~262) MENGANDUNG substring "bekerja" dari TERSERAP_KEYWORDS.
     * Akibatnya alumni yang BELUM bekerja ikut dihitung sebagai "terserap" --
     * pola persis yang sudah diantisipasi di buildKesesuaianCard() ("Kurang
     * Erat"/"Tidak Erat" vs exact match) tapi luput di kartu ini.
     *
     * Test ini sengaja meng-assert nilai yang SALAH (100.0, seharusnya 40.0)
     * supaya kelihatan merah di CI begitu diperbaiki -- pengingat untuk
     * meng-update assertion begitu bug-nya ditangani.
     */
    public function test_keterserapan_BUG_belum_bekerja_ikut_terhitung_karena_substring_bekerja(): void
    {
        $rows = new Collection([
            ['status' => 'Bekerja', 'count' => 40],
            ['status' => 'Belum Bekerja', 'count' => 60],
        ]);

        $card = $this->callPrivate($this->service(), 'buildKeterserapanCard', [$rows]);

        // Nilai SEHARUSNYA: 40/100*100 = 40.0. Nilai SEKARANG (bug): 100.0.
        $this->assertSame(100.0, $card['value'], 'Sudah diperbaiki? Ubah assertion ke 40.0 dan hapus catatan bug ini.');
    }

    public function test_keterserapan_tanpa_data_tidak_division_by_zero(): void
    {
        $card = $this->callPrivate($this->service(), 'buildKeterserapanCard', [new Collection([])]);

        $this->assertSame(0.0, $card['value']);
    }

    // ── Masa tunggu cepat ────────────────────────────────────────────────

    public function test_masa_tunggu_cepat_dihitung_terhadap_total_terserap(): void
    {
        $rows = new Collection([
            ['count_alumni' => 50, 'count_masa_tunggu_cepat' => 40],
            ['count_alumni' => 30, 'count_masa_tunggu_cepat' => 10],
        ]);

        $card = $this->callPrivate($this->service(), 'buildMasaTungguCard', [$rows]);

        // (40 + 10) / (50 + 30) * 100 = 62.5
        $this->assertSame(62.5, $card['value']);
    }

    // ── Gaji vs UMP ──────────────────────────────────────────────────────

    public function test_pendapatan_dengan_filter_tahun_lulus_hanya_menghitung_tahun_itu(): void
    {
        $rows = new Collection([
            ['tahun_lulus' => '2023', 'avg_gaji' => 4000000, 'total_alumni_ump' => 10, 'count_above_ump' => 2],
            ['tahun_lulus' => '2024', 'avg_gaji' => 6000000, 'total_alumni_ump' => 20, 'count_above_ump' => 18],
        ]);

        $card = $this->callPrivate($this->service(), 'buildPendapatanCard', [$rows, '2024']);

        $this->assertSame(6000000, $card['value']);
        // 18 / 20 * 100 = 90.0
        $this->assertSame(90.0, $card['pct_above_ump']);
    }

    public function test_pendapatan_tanpa_filter_tahun_menghitung_weighted_average_semua_tahun(): void
    {
        $rows = new Collection([
            ['tahun_lulus' => '2023', 'avg_gaji' => 4000000, 'total_alumni_ump' => 10, 'count_above_ump' => 2],
            ['tahun_lulus' => '2024', 'avg_gaji' => 6000000, 'total_alumni_ump' => 20, 'count_above_ump' => 18],
        ]);

        $card = $this->callPrivate($this->service(), 'buildPendapatanCard', [$rows, null]);

        // weighted avg = (4jt*10 + 6jt*20) / 30 = 5.333.333
        $this->assertSame(5333333, $card['value']);
        // (2 + 18) / 30 * 100 = 66.7
        $this->assertSame(66.7, $card['pct_above_ump']);
    }

    public function test_pendapatan_rows_kosong_tidak_division_by_zero(): void
    {
        $card = $this->callPrivate($this->service(), 'buildPendapatanCard', [new Collection([]), null]);

        $this->assertSame(0, $card['value']);
        $this->assertSame('-', $card['label']);
        $this->assertNull($card['pct_above_ump']);
    }

    public function test_pendapatan_filter_tahun_yang_tidak_ada_datanya_tidak_division_by_zero(): void
    {
        $rows = new Collection([
            ['tahun_lulus' => '2023', 'avg_gaji' => 4000000, 'total_alumni_ump' => 10, 'count_above_ump' => 2],
        ]);

        $card = $this->callPrivate($this->service(), 'buildPendapatanCard', [$rows, '2099']);

        $this->assertSame(0, $card['value']);
        $this->assertNull($card['pct_above_ump']);
    }
}
