<?php

namespace Tests\Feature\Transactional;

use App\Repositories\Transactional\OrgUnitRepository;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * DFR-14: RBAC generik butuh cakupan unit + seluruh keturunannya. Dites
 * terpisah dari EnforcesProdiScopeTest supaya traversal pohonnya sendiri
 * (BFS iteratif) tervalidasi lepas dari logic scoping di atasnya.
 */
class OrgUnitRepositoryDescendantTest extends TestCase
{
    private OrgUnitRepository $repo;

    /** @var int[] */
    private array $createdIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new OrgUnitRepository();
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

    public function test_leaf_unit_descendant_is_only_itself(): void
    {
        $type = $this->levelId('institut', 1);
        $leaf = $this->makeUnit($type, 'Leaf ' . uniqid());

        $this->assertSame([$leaf], $this->repo->descendantIds($leaf));
    }

    public function test_multi_level_descendant_includes_all_generations(): void
    {
        $level1 = $this->levelId('institut', 1);
        $level2 = $this->levelId('institut', 2);

        $root  = $this->makeUnit($level1, 'Root ' . uniqid());
        $childA = $this->makeUnit($level2, 'Child A', $root);
        $childB = $this->makeUnit($level2, 'Child B', $root);

        $ids = $this->repo->descendantIds($root);

        $this->assertEqualsCanonicalizing([$root, $childA, $childB], $ids);
    }

    public function test_descendant_of_sibling_root_is_not_included(): void
    {
        $level1 = $this->levelId('institut', 1);

        $rootA = $this->makeUnit($level1, 'Root A ' . uniqid());
        $rootB = $this->makeUnit($level1, 'Root B ' . uniqid());

        $ids = $this->repo->descendantIds($rootA);

        $this->assertNotContains($rootB, $ids);
    }
}
