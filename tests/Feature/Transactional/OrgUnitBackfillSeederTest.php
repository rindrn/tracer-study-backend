<?php

namespace Tests\Feature\Transactional;

use Database\Seeders\OrgUnitBackfillSeeder;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * DFR-24: backfill POLBAN dibandingkan baris-per-baris dengan
 * programs.jurusan / jurusans asli -- nol org_unit duplikat, nol prodi
 * yatim. Berjalan terhadap data POLBAN sungguhan yang ada di database
 * lokal (36 program, 11 jurusan per pemeriksaan awal), bukan fixture.
 */
class OrgUnitBackfillSeederTest extends TestCase
{
    public function test_backfill_matches_jurusans_master_list_exactly(): void
    {
        $jurusanNames = DB::connection('oltp')->table('jurusans')->orderBy('name')->pluck('name')->all();
        $this->assertNotEmpty($jurusanNames, 'Prasyarat: tabel jurusans harus berisi data (JurusanSeeder/dump).');

        $report = (new OrgUnitBackfillSeeder())->execute();

        $this->assertSame([], $report['mismatches'], 'Tidak boleh ada mismatch programs.jurusan vs jurusans.name pada data ini.');
        $this->assertSame([], $report['orphaned_programs'], 'Nol prodi yatim: setiap programs.jurusan harus match ke jurusans.name.');

        $politeknikType = DB::connection('oltp')->table('org_unit_types')
            ->where('institution_type', 'politeknik')->where('level_index', 1)->value('id');

        $orgUnitNames = DB::connection('oltp')->table('org_units')
            ->where('org_unit_type_id', $politeknikType)
            ->orderBy('name')
            ->pluck('name')
            ->all();

        // Baris-per-baris: nama org_units level Jurusan == daftar jurusans.name, tanpa duplikat.
        $this->assertSame($jurusanNames, $orgUnitNames, 'org_units hasil backfill harus identik dengan daftar jurusans.name (nol duplikat).');
        $this->assertSame(count($jurusanNames), count(array_unique($orgUnitNames)), 'Nol org_unit duplikat.');

        // Setiap nilai distinct programs.jurusan harus punya org_unit dengan nama yang sama persis (nol prodi yatim).
        $programJurusans = DB::connection('oltp')->table('programs')
            ->whereNotNull('jurusan')->where('jurusan', '<>', '')
            ->distinct()->pluck('jurusan')->all();

        foreach ($programJurusans as $jurusan) {
            $this->assertContains($jurusan, $orgUnitNames, "programs.jurusan \"{$jurusan}\" harus punya org_unit yang cocok (tidak yatim).");
        }
    }

    public function test_backfill_is_idempotent_on_rerun(): void
    {
        $countBefore = DB::connection('oltp')->table('org_units')->count();

        $report = (new OrgUnitBackfillSeeder())->execute();

        $this->assertSame(0, $report['created'], 'Menjalankan ulang backfill tidak boleh membuat baris baru.');
        $this->assertSame(
            $countBefore,
            DB::connection('oltp')->table('org_units')->count(),
            'Jumlah org_units tidak boleh berubah pada pemanggilan ulang.',
        );
    }
}
