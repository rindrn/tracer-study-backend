<?php

namespace Tests\Feature\Transactional;

use App\Exceptions\BusinessException;
use App\Repositories\Transactional\JurusanRepository;
use App\Repositories\Transactional\OrgUnitRepository;
use App\Repositories\Transactional\OrgUnitTypeRepository;
use App\Services\Transactional\JurusanService;
use App\Services\Transactional\OrgUnitService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Fase 4 (DFR-07/08/09/10/11): end-to-end CRUD unit organisasi (tambah,
 * ubah, nonaktifkan, reparent) di ketiga template (Politeknik/
 * Universitas/Institut), plus pencarian dan rename merambat ke jurusans
 * (DFR-10). Data sintetis dengan nama ber-uniqid(), dibersihkan sendiri
 * di tearDown -- data POLBAN asli (11 baris org_units hasil backfill
 * Fase 1) TIDAK disentuh.
 */
class OrgUnitCrudFase4Test extends TestCase
{
    private OrgUnitService $service;

    /** @var int[] */
    private array $createdUnitIds = [];

    /** @var int[] id jurusans sintetis yang dibuat test rename-merambat */
    private array $createdJurusanIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new OrgUnitService(new OrgUnitRepository(), new OrgUnitTypeRepository());
    }

    protected function tearDown(): void
    {
        foreach (array_reverse($this->createdUnitIds) as $id) {
            DB::connection('oltp')->table('org_units')->where('id', $id)->delete();
        }
        foreach (array_reverse($this->createdJurusanIds) as $id) {
            DB::connection('oltp')->table('jurusans')->where('id', $id)->delete();
        }

        parent::tearDown();
    }

    private function levelId(string $institutionType, int $levelIndex): int
    {
        $id = DB::connection('oltp')->table('org_unit_types')
            ->where('institution_type', $institutionType)
            ->where('level_index', $levelIndex)
            ->value('id');

        $this->assertNotNull($id, "Prasyarat: org_unit_types ({$institutionType}, level {$levelIndex}) harus ada.");

        return (int) $id;
    }

    public function test_crud_lifecycle_on_politeknik_template(): void
    {
        $type = $this->levelId('politeknik', 1);

        $unit = $this->service->create($type, 'Jurusan Uji CRUD Politeknik ' . uniqid());
        $this->createdUnitIds[] = $unit['id'];

        $updated = $this->service->update($unit['id'], $unit['name'] . ' (diubah)');
        $this->assertSame($unit['name'] . ' (diubah)', $updated['name']);

        // Nonaktifkan (DFR-07)
        $this->service->update($unit['id'], $updated['name'], false);
        $row = DB::connection('oltp')->table('org_units')->where('id', $unit['id'])->first();
        $this->assertFalse((bool) $row->is_active);

        $this->service->delete($unit['id']);
        $this->createdUnitIds = array_diff($this->createdUnitIds, [$unit['id']]);
        $this->assertNull(DB::connection('oltp')->table('org_units')->where('id', $unit['id'])->first());
    }

    public function test_crud_and_reparent_lifecycle_on_universitas_template(): void
    {
        $fakultasType   = $this->levelId('universitas', 1);
        $departemenType = $this->levelId('universitas', 2);

        $fakultasA = $this->service->create($fakultasType, 'Fakultas Uji CRUD U-A ' . uniqid());
        $this->createdUnitIds[] = $fakultasA['id'];
        $fakultasB = $this->service->create($fakultasType, 'Fakultas Uji CRUD U-B ' . uniqid());
        $this->createdUnitIds[] = $fakultasB['id'];

        $departemen = $this->service->create($departemenType, 'Departemen Uji CRUD U ' . uniqid(), parentId: $fakultasA['id']);
        $this->createdUnitIds[] = $departemen['id'];

        $this->service->reparent($departemen['id'], $fakultasB['id']);
        $parentId = DB::connection('oltp')->table('org_units')->where('id', $departemen['id'])->value('parent_id');
        $this->assertSame($fakultasB['id'], (int) $parentId);

        $this->service->update($departemen['id'], $departemen['name'] . ' (baru)');
        $name = DB::connection('oltp')->table('org_units')->where('id', $departemen['id'])->value('name');
        $this->assertSame($departemen['name'] . ' (baru)', $name);
    }

    public function test_crud_lifecycle_on_institut_template(): void
    {
        $fakultasType = $this->levelId('institut', 1);

        $unit = $this->service->create($fakultasType, 'Fakultas/Sekolah Uji CRUD Institut ' . uniqid());
        $this->createdUnitIds[] = $unit['id'];

        $this->service->update($unit['id'], $unit['name'], false);
        $row = DB::connection('oltp')->table('org_units')->where('id', $unit['id'])->first();
        $this->assertFalse((bool) $row->is_active);

        $this->service->update($unit['id'], $unit['name'], true);
        $row = DB::connection('oltp')->table('org_units')->where('id', $unit['id'])->first();
        $this->assertTrue((bool) $row->is_active);
    }

    public function test_delete_is_rejected_when_unit_has_children(): void
    {
        $fakultasType   = $this->levelId('universitas', 1);
        $departemenType = $this->levelId('universitas', 2);

        $fakultas = $this->service->create($fakultasType, 'Fakultas Uji Delete Anak ' . uniqid());
        $this->createdUnitIds[] = $fakultas['id'];

        $departemen = $this->service->create($departemenType, 'Departemen Uji Delete Anak ' . uniqid(), parentId: $fakultas['id']);
        $this->createdUnitIds[] = $departemen['id'];

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessageMatches('/unit anak/');

        $this->service->delete($fakultas['id']);
    }

    public function test_search_filters_by_name_and_level(): void
    {
        $type = $this->levelId('politeknik', 1);
        $unique = 'ZzUjiCari' . uniqid();

        $unit = $this->service->create($type, $unique);
        $this->createdUnitIds[] = $unit['id'];

        $byName = $this->service->search($unique);
        $this->assertCount(1, $byName);
        $this->assertSame($unit['id'], $byName[0]['id']);

        $byLevel = $this->service->search($unique, $type);
        $this->assertCount(1, $byLevel);

        $wrongLevelType = $this->levelId('universitas', 1);
        $byWrongLevel = $this->service->search($unique, $wrongLevelType);
        $this->assertCount(0, $byWrongLevel);
    }

    /**
     * DFR-10: rename unit level "Jurusan" (politeknik) yang namanya persis
     * cocok dengan baris `jurusans` harus merambat ke `jurusans.name`
     * (dan lewat JurusanService, ke `programs.jurusan`/`users.jurusan`) --
     * dual-mode DFR-25 tidak boleh pecah kembar.
     */
    public function test_rename_propagates_to_matching_jurusan_row(): void
    {
        $jurusanRepo = new JurusanRepository();
        $jurusanService = new JurusanService($jurusanRepo);

        $oldName = 'Jurusan Uji Rambat ' . uniqid();
        $jurusanId = $jurusanRepo->insert($oldName);
        $this->createdJurusanIds[] = $jurusanId;

        $type = $this->levelId('politeknik', 1);
        $unit = $this->service->create($type, $oldName);
        $this->createdUnitIds[] = $unit['id'];

        $newName = $oldName . ' (rename)';
        $result = $this->service->update($unit['id'], $newName);

        $this->assertSame($newName, DB::connection('oltp')->table('jurusans')->where('id', $jurusanId)->value('name'));
        $this->assertArrayHasKey('jurusan_affected', $result);
    }

    public function test_rename_of_unit_without_matching_jurusan_does_not_touch_jurusans_table(): void
    {
        $type = $this->levelId('universitas', 1);
        $unit = $this->service->create($type, 'Fakultas Tanpa Jurusan ' . uniqid());
        $this->createdUnitIds[] = $unit['id'];

        $countBefore = DB::connection('oltp')->table('jurusans')->count();
        $this->service->update($unit['id'], $unit['name'] . ' (ubah)');
        $countAfter = DB::connection('oltp')->table('jurusans')->count();

        $this->assertSame($countBefore, $countAfter);
    }
}
