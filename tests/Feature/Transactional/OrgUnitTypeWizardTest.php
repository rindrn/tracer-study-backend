<?php

namespace Tests\Feature\Transactional;

use App\Exceptions\BusinessException;
use App\Repositories\Transactional\OrgUnitRepository;
use App\Repositories\Transactional\OrgUnitTypeRepository;
use App\Services\Transactional\OrgUnitService;
use App\Services\Transactional\OrgUnitTypeService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * DFR-06: wizard migrasi struktur -- sisip/hapus level di tengah pohon
 * berisi data TANPA kehilangan unit existing. Dibangun di atas template
 * "custom" (didefinisikan sendiri oleh test, dibersihkan di tearDown)
 * supaya tidak mengganggu preset politeknik/universitas/institut yang
 * dipakai test Fase 1/4 lain secara paralel.
 */
class OrgUnitTypeWizardTest extends TestCase
{
    private const CUSTOM_TYPE = 'custom_wizard_test';

    private OrgUnitTypeService $typeService;
    private OrgUnitService $unitService;

    /** @var int[] */
    private array $createdUnitIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->typeService = new OrgUnitTypeService(new OrgUnitTypeRepository());
        $this->unitService = new OrgUnitService(new OrgUnitRepository(), new OrgUnitTypeRepository());
    }

    protected function tearDown(): void
    {
        foreach (array_reverse($this->createdUnitIds) as $id) {
            DB::connection('oltp')->table('org_units')->where('id', $id)->delete();
        }
        DB::connection('oltp')->table('org_unit_types')->where('institution_type', self::CUSTOM_TYPE)->delete();

        parent::tearDown();
    }

    /** @return array{0:int,1:int} [$level1Id, $level2Id] */
    private function seedTwoLevelTemplate(): array
    {
        $now = now();
        $level1Id = DB::connection('oltp')->table('org_unit_types')->insertGetId([
            'institution_type' => self::CUSTOM_TYPE, 'level_index' => 1, 'label' => 'Fakultas Wizard',
            'is_required' => true, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $level2Id = DB::connection('oltp')->table('org_unit_types')->insertGetId([
            'institution_type' => self::CUSTOM_TYPE, 'level_index' => 2, 'label' => 'Prodi Wizard',
            'is_required' => true, 'created_at' => $now, 'updated_at' => $now,
        ]);

        return [$level1Id, $level2Id];
    }

    public function test_insert_level_in_middle_shifts_deeper_levels_without_losing_units(): void
    {
        [$level1Id, $level2Id] = $this->seedTwoLevelTemplate();

        $fakultas = $this->unitService->create($level1Id, 'Fakultas Wizard A ' . uniqid());
        $this->createdUnitIds[] = $fakultas['id'];
        $prodi = $this->unitService->create($level2Id, 'Prodi Wizard A ' . uniqid(), parentId: $fakultas['id']);
        $this->createdUnitIds[] = $prodi['id'];

        // Sisip "Departemen" di antara Fakultas (level 1) dan Prodi (level 2 -> jadi 3).
        $this->typeService->insertLevel(self::CUSTOM_TYPE, 2, 'Departemen Wizard');

        $levels = $this->typeService->listByInstitutionType(self::CUSTOM_TYPE);
        $this->assertCount(3, $levels);
        $this->assertSame('Fakultas Wizard', $levels[0]['label']);
        $this->assertSame(1, $levels[0]['level_index']);
        $this->assertSame('Departemen Wizard', $levels[1]['label']);
        $this->assertSame(2, $levels[1]['level_index']);
        $this->assertSame('Prodi Wizard', $levels[2]['label']);
        $this->assertSame(3, $levels[2]['level_index']);

        // Data existing (Fakultas -> Prodi langsung, tanpa Departemen) tetap utuh.
        $fakultasRow = DB::connection('oltp')->table('org_units')->where('id', $fakultas['id'])->first();
        $prodiRow    = DB::connection('oltp')->table('org_units')->where('id', $prodi['id'])->first();
        $this->assertNotNull($fakultasRow);
        $this->assertNotNull($prodiRow);
        $this->assertSame($fakultas['id'], (int) $prodiRow->parent_id);
    }

    public function test_remove_level_in_middle_preserves_grandchildren_by_reparenting_up(): void
    {
        [$level1Id, $level2Id] = $this->seedTwoLevelTemplate();
        $level3Id = $this->typeService->insertLevel(self::CUSTOM_TYPE, 3, 'Program Wizard')[2]['id'];

        $fakultas = $this->unitService->create($level1Id, 'Fakultas Wizard B ' . uniqid());
        $this->createdUnitIds[] = $fakultas['id'];
        $prodi = $this->unitService->create($level2Id, 'Prodi Wizard B ' . uniqid(), parentId: $fakultas['id']);
        $this->createdUnitIds[] = $prodi['id'];
        $program = $this->unitService->create($level3Id, 'Program Wizard B ' . uniqid(), parentId: $prodi['id']);
        $this->createdUnitIds[] = $program['id'];

        // Hapus level tengah ("Prodi Wizard") -- unit "Prodi Wizard B" ikut
        // terhapus (levelnya lenyap), tapi anaknya ("Program Wizard B")
        // TIDAK hilang, cuma naik jadi anak Fakultas.
        $result = $this->typeService->removeLevel($level2Id);

        $this->assertSame('Prodi Wizard', $result['removed_level']);
        $this->assertSame(1, $result['units_removed']);

        $this->assertNull(DB::connection('oltp')->table('org_units')->where('id', $prodi['id'])->first());

        $programRow = DB::connection('oltp')->table('org_units')->where('id', $program['id'])->first();
        $this->assertNotNull($programRow, 'Unit anak (Program Wizard B) tidak boleh ikut hilang.');
        $this->assertSame($fakultas['id'], (int) $programRow->parent_id, 'Unit anak harus naik jadi anak dari induk level yang dihapus.');

        $levels = $this->typeService->listByInstitutionType(self::CUSTOM_TYPE);
        $this->assertCount(2, $levels);
        $this->assertSame('Fakultas Wizard', $levels[0]['label']);
        $this->assertSame('Program Wizard', $levels[1]['label']);
        $this->assertSame(2, $levels[1]['level_index'], 'Level_index level yang tersisa harus dirapatkan.');
    }

    public function test_cannot_remove_the_only_remaining_level(): void
    {
        $now = now();
        $onlyLevel = DB::connection('oltp')->table('org_unit_types')->insertGetId([
            'institution_type' => self::CUSTOM_TYPE, 'level_index' => 1, 'label' => 'Satu-satunya Level',
            'is_required' => true, 'created_at' => $now, 'updated_at' => $now,
        ]);

        $this->expectException(BusinessException::class);
        $this->typeService->removeLevel($onlyLevel);
    }

    public function test_insert_level_rejects_position_beyond_current_depth_plus_one(): void
    {
        $this->seedTwoLevelTemplate();

        $this->expectException(BusinessException::class);
        $this->typeService->insertLevel(self::CUSTOM_TYPE, 10, 'Level Tidak Valid');
    }
}
