<?php

namespace Tests\Feature\ETL;

use App\Repositories\Transactional\OrgUnitRepository;
use App\Repositories\Transactional\OrgUnitTypeRepository;
use App\Services\ETL\OrgUnitHierarchyResolverService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * DFR-17: OrgUnitHierarchyResolverService menelusuri pohon org_units dari
 * daun ke akar dan mengisi level_1_name..level_5_name sesuai level_index
 * org_unit_types. Dites terpisah dari ProdiDimService supaya penelusuran
 * pohonnya sendiri tervalidasi lepas dari logic SCD2 di atasnya -- pola
 * yang sama dengan OrgUnitRepositoryDescendantTest (DFR-14).
 */
class OrgUnitHierarchyResolverServiceTest extends TestCase
{
    private OrgUnitHierarchyResolverService $resolver;

    /** @var int[] */
    private array $createdIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new OrgUnitHierarchyResolverService(
            new OrgUnitRepository(),
            new OrgUnitTypeRepository(),
        );
    }

    protected function tearDown(): void
    {
        foreach (array_reverse($this->createdIds) as $id) {
            DB::connection('oltp')->table('org_units')->where('id', $id)->delete();
        }

        parent::tearDown();
    }

    private function levelId(string $institutionType, int $levelIndex): int
    {
        return (int) DB::connection('oltp')->table('org_unit_types')
            ->where('institution_type', $institutionType)
            ->where('level_index', $levelIndex)
            ->value('id');
    }

    private function makeUnit(int $typeId, string $name, ?int $parentId = null): int
    {
        $id = DB::connection('oltp')->table('org_units')->insertGetId([
            'org_unit_type_id' => $typeId,
            'parent_id'        => $parentId,
            'name'             => $name,
            'is_active'        => true,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);
        $this->createdIds[] = $id;

        return $id;
    }

    public function test_resolve_by_org_unit_id_walks_two_level_tree_leaf_to_root(): void
    {
        $level1 = $this->levelId('institut', 1); // Fakultas/Sekolah
        $level2 = $this->levelId('institut', 2); // Departemen

        $faculty = $this->makeUnit($level1, 'Fakultas Teknik ' . uniqid());
        $dept = $this->makeUnit($level2, 'Departemen Sipil ' . uniqid(), $faculty);

        $result = $this->resolver->resolve($dept, null);

        $this->assertSame(DB::connection('oltp')->table('org_units')->find($faculty)->name, $result['level_1_name']);
        $this->assertSame(DB::connection('oltp')->table('org_units')->find($dept)->name, $result['level_2_name']);
        $this->assertNull($result['level_3_name']);
        $this->assertNull($result['level_4_name']);
        $this->assertNull($result['level_5_name']);
    }

    public function test_resolve_by_org_unit_id_single_level_only_fills_level_1(): void
    {
        $level1 = $this->levelId('politeknik', 1);
        $unit = $this->makeUnit($level1, 'Jurusan Uji ' . uniqid());

        $result = $this->resolver->resolve($unit, null);

        $name = DB::connection('oltp')->table('org_units')->find($unit)->name;
        $this->assertSame($name, $result['level_1_name']);
        $this->assertNull($result['level_2_name']);
    }

    public function test_resolve_falls_back_to_name_match_when_org_unit_id_null(): void
    {
        config(['institution.structure_template' => 'politeknik']);

        // Nama org_unit politeknik sungguhan sudah ada dari backfill DFR-24
        // (11 jurusan POLBAN) -- pakai salah satu tanpa membuat data baru.
        $existingName = DB::connection('oltp')->table('org_units')
            ->where('org_unit_type_id', $this->levelId('politeknik', 1))
            ->value('name');

        $this->assertNotNull($existingName, 'Prasyarat: org_units politeknik harus berisi data dari backfill DFR-24.');

        $result = $this->resolver->resolve(null, $existingName);

        $this->assertSame($existingName, $result['level_1_name']);
    }

    public function test_resolve_returns_all_null_when_nothing_matches(): void
    {
        $result = $this->resolver->resolve(null, 'Nama Yang Sama Sekali Tidak Ada ' . uniqid());

        $this->assertNull($result['level_1_name']);
        $this->assertNull($result['level_2_name']);
        $this->assertNull($result['level_3_name']);
        $this->assertNull($result['level_4_name']);
        $this->assertNull($result['level_5_name']);
    }

    public function test_resolve_returns_all_null_when_org_unit_id_and_fallback_both_absent(): void
    {
        $result = $this->resolver->resolve(null, null);

        $this->assertSame([
            'level_1_name' => null,
            'level_2_name' => null,
            'level_3_name' => null,
            'level_4_name' => null,
            'level_5_name' => null,
        ], $result);
    }

    public function test_resolve_falls_back_to_name_when_org_unit_id_points_to_deleted_unit(): void
    {
        config(['institution.structure_template' => 'politeknik']);

        $existingName = DB::connection('oltp')->table('org_units')
            ->where('org_unit_type_id', $this->levelId('politeknik', 1))
            ->value('name');

        $nonExistentId = (int) DB::connection('oltp')->table('org_units')->max('id') + 999_999;

        $result = $this->resolver->resolve($nonExistentId, $existingName);

        $this->assertSame($existingName, $result['level_1_name']);
    }
}
