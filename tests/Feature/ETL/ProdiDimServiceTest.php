<?php

namespace Tests\Feature\ETL;

use App\Repositories\ETL\OlapLoadRepository;
use App\Repositories\ETL\OltpExtractRepository;
use App\Repositories\Transactional\OrgUnitRepository;
use App\Repositories\Transactional\OrgUnitTypeRepository;
use App\Services\ETL\OrgUnitHierarchyResolverService;
use App\Services\ETL\ProdiDimService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * DFR-17/DFR-18/DFR-19 (Fase 3): ProdiDimService::sync() memanggil
 * OrgUnitHierarchyResolverService DI DALAM langkah SCD2 yang sama, dan
 * hasilnya (level_1_name..level_5_name) harus:
 *
 *   1. identik dengan `jurusan` lama untuk data POLBAN existing (mode
 *      politeknik, 1 level saja);
 *   2. TIDAK PERNAH menulis ulang versi SCD2 yang sudah tertutup
 *      (flag_prodi = false) -- riwayat tetap terjaga (DFR-19).
 *
 * Berjalan terhadap data POLBAN sungguhan di database lokal (36 program),
 * konsisten dengan pola OrgUnitBackfillSeederTest -- bukan fixture/mock.
 */
class ProdiDimServiceTest extends TestCase
{
    private ProdiDimService $service;

    /** @var int[] id_prodi sintetis yang dibuat test ini di dim_prodi */
    private array $createdProdiSks = [];

    /** @var int[] id programs sintetis */
    private array $createdProgramIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new ProdiDimService(
            new OltpExtractRepository(),
            new OlapLoadRepository(),
            new OrgUnitHierarchyResolverService(new OrgUnitRepository(), new OrgUnitTypeRepository()),
        );
    }

    protected function tearDown(): void
    {
        foreach ($this->createdProdiSks as $sk) {
            DB::connection('olap')->table('dim_prodi')->where('prodi_sk', $sk)->delete();
        }
        foreach (array_reverse($this->createdProgramIds) as $id) {
            DB::connection('oltp')->table('programs')->where('id', $id)->delete();
        }

        parent::tearDown();
    }

    public function test_sync_fills_level_1_name_identical_to_jurusan_for_existing_polban_data(): void
    {
        $this->service->sync(now());

        $rows = DB::connection('olap')->table('dim_prodi')
            ->where('flag_prodi', true)
            ->get(['id_prodi', 'jurusan', 'level_1_name', 'level_2_name']);

        $this->assertNotEmpty($rows, 'Prasyarat: dim_prodi harus berisi data POLBAN existing.');

        foreach ($rows as $row) {
            $this->assertSame(
                $row->jurusan,
                $row->level_1_name,
                "id_prodi={$row->id_prodi}: level_1_name harus identik dengan jurusan lama pada mode politeknik.",
            );
            $this->assertNull($row->level_2_name, 'Politeknik cuma 1 level -- level_2_name harus NULL.');
        }
    }

    public function test_closed_scd2_version_is_never_retroactively_filled(): void
    {
        // Baris tertutup sintetis, TIDAK terkait id program manapun yang
        // sungguhan ada di tabel programs -- sync() tidak boleh pernah
        // menyentuhnya karena hanya mengiterasi programs yang benar-benar
        // ada (getActiveProdiVersion/closeProdiVersion/insertNewProdiVersion
        // semuanya dipicu per baris `programs`).
        $syntheticIdProdi = (int) DB::connection('olap')->table('dim_prodi')->max('id_prodi') + 999_001;

        $closedSk = DB::connection('olap')->table('dim_prodi')->insertGetId([
            'id_prodi'     => $syntheticIdProdi,
            'kode_prodi'   => 'ZZTEST',
            'nama_prodi'   => 'Prodi Historis Uji',
            'jurusan'      => 'Jurusan Historis Uji',
            'jenjang'      => 'D3',
            'level_1_name' => null,
            'valid_from'   => '2020-01-01',
            'valid_to'     => '2021-01-01',
            'flag_prodi'   => false,
        ], 'prodi_sk');
        $this->createdProdiSks[] = $closedSk;

        $this->service->sync(now());

        $row = DB::connection('olap')->table('dim_prodi')->where('prodi_sk', $closedSk)->first();

        $this->assertFalse((bool) $row->flag_prodi, 'Versi tertutup harus tetap tertutup.');
        $this->assertNull($row->level_1_name, 'Versi SCD2 tertutup TIDAK BOLEH diisi retroaktif oleh sync().');
    }

    public function test_reparenting_the_org_unit_rolls_a_new_scd2_version_and_preserves_old_ones_hierarchy(): void
    {
        $politeknikType = (int) DB::connection('oltp')->table('org_unit_types')
            ->where('institution_type', 'politeknik')->where('level_index', 1)->value('id');

        $jurusanA = DB::connection('oltp')->table('org_units')
            ->where('org_unit_type_id', $politeknikType)->orderBy('id')->first();
        $jurusanB = DB::connection('oltp')->table('org_units')
            ->where('org_unit_type_id', $politeknikType)->orderBy('id')->skip(1)->first();

        $this->assertNotNull($jurusanA);
        $this->assertNotNull($jurusanB);

        $programId = DB::connection('oltp')->table('programs')->insertGetId([
            'name'        => 'Prodi Uji DFR-19 ' . uniqid(),
            'code'        => 'UJI' . substr(uniqid(), -6),
            'degree'      => 'D3',
            'is_active'   => true,
            'jurusan'     => $jurusanA->name,
            'org_unit_id' => $jurusanA->id,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
        $this->createdProgramIds[] = $programId;

        $this->service->sync(now());

        $firstVersion = DB::connection('olap')->table('dim_prodi')
            ->where('id_prodi', $programId)->where('flag_prodi', true)->first();
        $this->assertNotNull($firstVersion);
        $this->createdProdiSks[] = $firstVersion->prodi_sk;
        $this->assertSame($jurusanA->name, $firstVersion->level_1_name);

        // Reparent: pindahkan program ke jurusan lain (DFR-09 sudah
        // memvalidasi ini di level org_units -- di sini cukup ubah FK
        // programs.org_unit_id langsung, meniru hasil OrgUnitService di
        // masa depan) tanpa mengubah programs.jurusan. Resolver harus
        // memprioritaskan org_unit_id di atas fallback nama.
        DB::connection('oltp')->table('programs')->where('id', $programId)
            ->update(['org_unit_id' => $jurusanB->id]);

        $this->service->sync(now());

        $closedVersion = DB::connection('olap')->table('dim_prodi')->where('prodi_sk', $firstVersion->prodi_sk)->first();
        $newVersion = DB::connection('olap')->table('dim_prodi')
            ->where('id_prodi', $programId)->where('flag_prodi', true)->first();

        $this->assertNotNull($newVersion);
        $this->createdProdiSks[] = $newVersion->prodi_sk;

        $this->assertFalse((bool) $closedVersion->flag_prodi, 'Versi lama harus ditutup begitu unit di-reparent.');
        $this->assertSame($jurusanA->name, $closedVersion->level_1_name, 'Versi lama tetap mencerminkan struktur pada masanya (tidak diubah retroaktif).');
        $this->assertSame($jurusanB->name, $newVersion->level_1_name, 'Versi baru mencerminkan unit hasil reparent.');
    }
}
