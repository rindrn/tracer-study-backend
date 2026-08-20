<?php

namespace Tests\Feature\Transactional;

use App\Exceptions\BusinessException;
use App\Repositories\Transactional\OrgUnitTypeRepository;
use App\Services\Transactional\OrgUnitTypeService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * DFR-05: guard integritas template. Level "Jurusan" (politeknik) sudah
 * dipakai oleh org_units hasil backfill DFR-24, jadi ini pengujian atas
 * data nyata yang sedang dipakai -- bukan fixture buatan -- persis
 * skenario yang guard ini dibuat untuk mencegah.
 */
class OrgUnitTypeGuardTest extends TestCase
{
    private OrgUnitTypeService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new OrgUnitTypeService(new OrgUnitTypeRepository());
    }

    private function politeknikJurusanId(): int
    {
        $row = DB::connection('oltp')->table('org_unit_types')
            ->where('institution_type', 'politeknik')
            ->where('level_index', 1)
            ->first();

        $this->assertNotNull($row, 'Prasyarat: migration org_unit_types harus sudah jalan.');
        $this->assertGreaterThan(
            0,
            DB::connection('oltp')->table('org_units')->where('org_unit_type_id', $row->id)->count(),
            'Prasyarat: level ini harus sudah dipakai org_units (lewat backfill) supaya guard teruji terhadap kasus sungguhan.',
        );

        return (int) $row->id;
    }

    public function test_changing_level_index_is_rejected_when_org_units_exist(): void
    {
        $id = $this->politeknikJurusanId();

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('tidak bisa diubah');

        $this->service->changeStructure($id, levelIndex: 2);
    }

    public function test_changing_is_required_is_rejected_when_org_units_exist(): void
    {
        $id = $this->politeknikJurusanId();

        $this->expectException(BusinessException::class);

        $this->service->changeStructure($id, isRequired: false);
    }

    public function test_deleting_type_in_use_is_rejected(): void
    {
        $id = $this->politeknikJurusanId();

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('masih dipakai');

        $this->service->delete($id);
    }

    public function test_renaming_label_is_still_allowed_because_it_does_not_change_structure(): void
    {
        $id = $this->politeknikJurusanId();
        $original = DB::connection('oltp')->table('org_unit_types')->where('id', $id)->value('label');

        $this->service->renameLabel($id, 'Jurusan (uji)');
        $this->assertSame('Jurusan (uji)', DB::connection('oltp')->table('org_unit_types')->where('id', $id)->value('label'));

        // Kembalikan ke nilai semula supaya tidak meninggalkan efek samping bagi test lain.
        $this->service->renameLabel($id, $original);
        $this->assertSame($original, DB::connection('oltp')->table('org_unit_types')->where('id', $id)->value('label'));
    }
}
