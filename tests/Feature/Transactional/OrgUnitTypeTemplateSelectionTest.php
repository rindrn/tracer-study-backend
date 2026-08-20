<?php

namespace Tests\Feature\Transactional;

use App\Exceptions\BusinessException;
use App\Repositories\Config\AppSettingRepository;
use App\Repositories\Transactional\OrgUnitRepository;
use App\Repositories\Transactional\OrgUnitTypeRepository;
use App\Services\Transactional\OrgUnitTypeService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * DFR-01 (pilih template saat setup) dan DFR-04 (template custom).
 *
 * DFR-01 di lingkungan ini SELALU ditolak karena org_units POLBAN (hasil
 * backfill Fase 1) sudah ada -- itu justru skenario yang guard ini
 * dibuat untuk mencegah, jadi diuji sebagai kasus penolakan yang benar,
 * bukan dilewati. Pemilihan template yang berhasil (instalasi baru,
 * org_units kosong) tidak bisa diuji tanpa mengosongkan tabel org_units
 * sungguhan -- di luar cakupan test yang wajib membersihkan diri sendiri.
 */
class OrgUnitTypeTemplateSelectionTest extends TestCase
{
    private OrgUnitTypeService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new OrgUnitTypeService(new OrgUnitTypeRepository(), new OrgUnitRepository(), new AppSettingRepository());
    }

    protected function tearDown(): void
    {
        DB::connection('oltp')->table('org_unit_types')->where('institution_type', 'custom_dfr04_test')->delete();

        parent::tearDown();
    }

    public function test_select_template_is_rejected_when_org_units_already_exist(): void
    {
        $this->assertGreaterThan(0, (new OrgUnitRepository())->countAll(), 'Prasyarat: org_units POLBAN harus sudah ada.');

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessageMatches('/sebelum ada unit organisasi/');

        $this->service->selectTemplate('universitas');
    }

    public function test_select_template_rejects_invalid_institution_type(): void
    {
        $this->expectException(BusinessException::class);
        $this->service->selectTemplate('kerajaan');
    }

    public function test_active_institution_type_reads_polban_default(): void
    {
        // Politeknik adalah nilai aktif POLBAN saat ini -- baik lewat
        // app_settings (diisi migration create_org_unit_types_table) maupun
        // fallback config/institution.php kalau baris itu belum ada.
        $this->assertSame('politeknik', $this->service->activeInstitutionType());
    }

    public function test_define_custom_template_creates_two_to_five_levels(): void
    {
        // Institution_type literalnya 'custom' dipakai HTTP layer; di sini
        // kita uji lewat baris nyata 'custom' juga tapi hanya kalau belum
        // dipakai org_units apa pun (guard sama seperti removeLevel), jadi
        // aman dijalankan berulang di lingkungan lokal manapun.
        $existingCustomUsage = collect((new OrgUnitTypeRepository())->byInstitutionType('custom'))
            ->sum(fn ($row) => (new OrgUnitTypeRepository())->orgUnitCount((int) $row->id));

        if ($existingCustomUsage > 0) {
            $this->markTestSkipped('Template custom sudah dipakai org_units di lingkungan ini -- lihat guard defineCustomTemplate().');
        }

        $levels = $this->service->defineCustomTemplate([
            ['label' => 'Unit Akademik Uji'],
            ['label' => 'Program Studi Uji'],
        ]);

        $this->assertCount(2, $levels);
        $this->assertSame('Unit Akademik Uji', $levels[0]['label']);
        $this->assertSame(1, $levels[0]['level_index']);
        $this->assertSame('Program Studi Uji', $levels[1]['label']);
        $this->assertSame(2, $levels[1]['level_index']);

        // Kembalikan ke keadaan semula (kosong) supaya tidak meninggalkan
        // efek samping bagi test lain yang memakai institution_type 'custom'.
        (new OrgUnitTypeRepository())->deleteByInstitutionType('custom');
    }

    public function test_define_custom_template_rejects_more_than_five_levels(): void
    {
        $this->expectException(BusinessException::class);

        $this->service->defineCustomTemplate([
            ['label' => 'L1'], ['label' => 'L2'], ['label' => 'L3'],
            ['label' => 'L4'], ['label' => 'L5'], ['label' => 'L6'],
        ]);
    }

    public function test_define_custom_template_rejects_fewer_than_two_levels(): void
    {
        $this->expectException(BusinessException::class);

        $this->service->defineCustomTemplate([['label' => 'Hanya Satu Level']]);
    }
}
