<?php

namespace Tests\Feature\Transactional;

use App\Exceptions\BusinessException;
use App\Repositories\Transactional\OrgUnitRepository;
use App\Repositories\Transactional\OrgUnitTypeRepository;
use App\Services\Transactional\OrgUnitService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * DFR-09: validasi anti-siklus di level aplikasi (bukan constraint DB --
 * lihat catatan di migration create_org_units_table). Dibangun di atas
 * template Universitas (2 level: Fakultas -> Departemen) supaya ada kasus
 * siklus multi-level yang sungguhan, bukan cuma self-parent.
 */
class OrgUnitServiceCycleTest extends TestCase
{
    private OrgUnitService $service;

    /** @var int[] id org_units yang dibuat test ini, dibersihkan di tearDown */
    private array $createdIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new OrgUnitService(new OrgUnitRepository(), new OrgUnitTypeRepository());
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
        $id = DB::connection('oltp')->table('org_unit_types')
            ->where('institution_type', $institutionType)
            ->where('level_index', $levelIndex)
            ->value('id');

        $this->assertNotNull($id, "Prasyarat: org_unit_types ({$institutionType}, level {$levelIndex}) harus ada.");

        return (int) $id;
    }

    public function test_unit_cannot_become_its_own_parent(): void
    {
        $fakultasType = $this->levelId('universitas', 1);
        $unit = $this->service->create($fakultasType, 'Fakultas Uji Siklus A ' . uniqid());
        $this->createdIds[] = $unit['id'];

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessageMatches('/induk dari dirinya sendiri/');

        $this->service->reparent($unit['id'], $unit['id']);
    }

    public function test_ancestor_cannot_be_reparented_under_its_own_descendant(): void
    {
        $fakultasType   = $this->levelId('universitas', 1);
        $departemenType = $this->levelId('universitas', 2);

        $fakultas = $this->service->create($fakultasType, 'Fakultas Uji Siklus B ' . uniqid());
        $this->createdIds[] = $fakultas['id'];

        $departemen = $this->service->create($departemenType, 'Departemen Uji Siklus B ' . uniqid(), parentId: $fakultas['id']);
        $this->createdIds[] = $departemen['id'];

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessageMatches('/siklus/');

        // Fakultas (ancestor) dipindah jadi anak Departemen (descendant-nya sendiri) -- harus ditolak.
        $this->service->reparent($fakultas['id'], $departemen['id']);
    }

    public function test_parent_level_must_be_shallower_than_child_level(): void
    {
        $fakultasType   = $this->levelId('universitas', 1);
        $departemenType = $this->levelId('universitas', 2);

        $departemen = $this->service->create($departemenType, 'Departemen Uji Level C ' . uniqid());
        $this->createdIds[] = $departemen['id'];

        $this->expectException(BusinessException::class);

        // Fakultas (level lebih dalam secara sengaja dibalik di sini) tidak boleh
        // dibuat sebagai anak dari Departemen -- urutan level tidak valid.
        $child = $this->service->create($fakultasType, 'Fakultas Uji Level C ' . uniqid(), parentId: $departemen['id']);
        $this->createdIds[] = $child['id'] ?? null;
    }

    public function test_valid_reparent_within_same_template_succeeds(): void
    {
        $fakultasType   = $this->levelId('universitas', 1);
        $departemenType = $this->levelId('universitas', 2);

        $fakultasA = $this->service->create($fakultasType, 'Fakultas Uji Valid D-A ' . uniqid());
        $this->createdIds[] = $fakultasA['id'];
        $fakultasB = $this->service->create($fakultasType, 'Fakultas Uji Valid D-B ' . uniqid());
        $this->createdIds[] = $fakultasB['id'];

        $departemen = $this->service->create($departemenType, 'Departemen Uji Valid D ' . uniqid(), parentId: $fakultasA['id']);
        $this->createdIds[] = $departemen['id'];

        $this->service->reparent($departemen['id'], $fakultasB['id']);

        $newParentId = DB::connection('oltp')->table('org_units')->where('id', $departemen['id'])->value('parent_id');
        $this->assertSame($fakultasB['id'], (int) $newParentId);
    }
}
