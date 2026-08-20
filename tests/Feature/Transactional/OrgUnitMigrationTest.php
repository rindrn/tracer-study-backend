<?php

namespace Tests\Feature\Transactional;

use Database\Seeders\OrgUnitBackfillSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Migration up/down bersih untuk org_unit_types & org_units (Fase 1,
 * testing wajib menurut cetak-biru-struktur-dinamis.md).
 *
 * Berjalan terhadap koneksi 'oltp' sungguhan (bukan sqlite default
 * phpunit.xml) -- app ini selalu memakai koneksi 'oltp'/'olap' eksplisit,
 * jadi mengikuti pola yang sama dipakai seluruh test lain di sekitar
 * modul ini. Rollback+migrate dibungkus try/finally supaya kegagalan
 * assertion di tengah jalan tidak meninggalkan skema dalam keadaan
 * setengah jadi untuk test berikutnya.
 */
class OrgUnitMigrationTest extends TestCase
{
    private const CONN = 'oltp';

    private const PATHS = [
        'database/migrations/transactional/tables/2026_08_20_000007_create_org_unit_types_table.php',
        'database/migrations/transactional/tables/2026_08_20_000008_create_org_units_table.php',
        // Fase 2 (DFR-12/13/14) menambahkan FK users.org_unit_id/programs.org_unit_id
        // -> org_units. Migration itu WAJIB ikut di-rollback di sini juga --
        // tanpanya, DROP TABLE org_units gagal karena masih direferensikan
        // FK dari users/programs, dan rollback test ini gagal senyap.
        'database/migrations/transactional/tables/2026_08_20_000009_add_org_unit_id_to_users_and_programs.php',
    ];

    public function test_rollback_then_remigrate_is_clean(): void
    {
        $this->assertTrue(Schema::connection(self::CONN)->hasTable('org_unit_types'));
        $this->assertTrue(Schema::connection(self::CONN)->hasTable('org_units'));

        try {
            // migrate:rollback tanpa --step hanya membongkar batch TERAKHIR,
            // difilter oleh --path. Migration Fase 2 (2026_08_20_000009,
            // FK users/programs.org_unit_id -> org_units) sering berada di
            // batch terpisah dari 007/008 (Fase 1) kalau dijalankan di waktu
            // berbeda -- satu panggilan rollback saja bisa cuma membongkar
            // satu batch dan meninggalkan org_units masih ber-FK, gagal DROP
            // TABLE secara senyap. Panggil berulang (dibatasi jumlah migration
            // di PATHS) sampai org_units benar-benar hilang, supaya tes ini
            // tidak bergantung pada asumsi batch tertentu.
            for ($i = 0; $i < count(self::PATHS) && Schema::connection(self::CONN)->hasTable('org_units'); $i++) {
                Artisan::call('migrate:rollback', ['--path' => self::PATHS, '--database' => self::CONN, '--force' => true]);
            }

            $this->assertFalse(Schema::connection(self::CONN)->hasTable('org_units'), 'org_units harus hilang setelah rollback');
            $this->assertFalse(Schema::connection(self::CONN)->hasTable('org_unit_types'), 'org_unit_types harus hilang setelah rollback');
            $this->assertFalse(
                DB::connection(self::CONN)->table('app_settings')->where('key', 'institution_structure_template')->exists(),
                'flag rollout DFR-25 harus ikut terhapus saat down()',
            );
        } finally {
            Artisan::call('migrate', ['--path' => self::PATHS, '--database' => self::CONN, '--force' => true]);
        }

        $this->assertTrue(Schema::connection(self::CONN)->hasTable('org_unit_types'));
        $this->assertTrue(Schema::connection(self::CONN)->hasTable('org_units'));

        // Preset DFR-02 harus utuh lagi: 5 baris (1 politeknik + 2 universitas + 2 institut).
        $this->assertSame(5, DB::connection(self::CONN)->table('org_unit_types')->count());

        $politeknik = DB::connection(self::CONN)->table('org_unit_types')
            ->where('institution_type', 'politeknik')->get();
        $this->assertCount(1, $politeknik);
        $this->assertSame('Jurusan', $politeknik->first()->label);

        $universitas = DB::connection(self::CONN)->table('org_unit_types')
            ->where('institution_type', 'universitas')->orderBy('level_index')->get();
        $this->assertCount(2, $universitas);
        $this->assertSame(['Fakultas', 'Departemen'], $universitas->pluck('label')->all());

        $institut = DB::connection(self::CONN)->table('org_unit_types')
            ->where('institution_type', 'institut')->orderBy('level_index')->get();
        $this->assertCount(2, $institut);
        $this->assertFalse((bool) $institut->firstWhere('label', 'Departemen')->is_required, 'Departemen di Institut opsional (is_required=false)');

        $this->assertSame(
            config('institution.structure_template', 'politeknik'),
            DB::connection(self::CONN)->table('app_settings')->where('key', 'institution_structure_template')->value('value'),
        );
    }

    protected function tearDown(): void
    {
        // Jaring pengaman terakhir: pastikan skema tersedia lagi untuk test
        // lain di file yang berbeda, apa pun hasil assertion di atas.
        if (! Schema::connection(self::CONN)->hasTable('org_unit_types') || ! Schema::connection(self::CONN)->hasTable('org_units')) {
            Artisan::call('migrate', ['--path' => self::PATHS, '--database' => self::CONN, '--force' => true]);
        }

        // rollback+remigrate mengosongkan org_units (preset org_unit_types
        // tidak membawa data backfill). Test lain di suite ini (guard DFR-05,
        // backfill DFR-24) mengasumsikan data POLBAN sudah ter-backfill --
        // kembalikan invariant itu supaya urutan eksekusi test tidak
        // memengaruhi hasil test lain, konsisten dengan alur deploy
        // sungguhan (migrate lalu jalankan backfill seeder).
        if (Schema::connection(self::CONN)->hasTable('org_units') && DB::connection(self::CONN)->table('org_units')->count() === 0) {
            (new OrgUnitBackfillSeeder())->execute();
        }

        parent::tearDown();
    }
}
