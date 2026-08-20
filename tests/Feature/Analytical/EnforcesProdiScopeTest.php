<?php

namespace Tests\Feature\Analytical;

use App\Http\Controllers\Api\Analytical\Concerns\EnforcesProdiScope;
use App\Models\Transactional\Program;
use App\Models\Transactional\Role;
use App\Models\Transactional\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * DFR-13 -- "titik paling kritis" per cetak-biru-struktur-dinamis.md: satu
 * cacat di sini membocorkan data lintas unit. Tes ini menutupi:
 *
 *   1. Regresi mode "politeknik" (scopedParamsLegacy) -- badan metode tidak
 *      diubah, tapi dites ulang lewat dispatcher scopedParams() untuk
 *      memastikan routing dual-mode (DFR-25) tidak diam-diam mengubah hasil.
 *   2. Mode generik (DFR-12/13/14/15) -- unrestricted / mid-level+descendant
 *      / leaf composite-key, dan gagal-terang-terangan (403) untuk role
 *      tak tertaut atau scope tak dikenal.
 *   3. Isolasi composite-key: dua user "Program Studi"-scope dengan nama
 *      prodi SAMA tapi jenjang BEDA tidak boleh saling bocor.
 *
 * User di tes ini SENGAJA tidak disimpan ke DB (in-memory saja) -- kolom
 * `users.role` masih dibatasi CHECK constraint 5 nilai legacy dan
 * UserController::store() masih memvalidasi Rule::in(User::ROLES_ALL).
 * DFR-12 baru membuat *scoping*-nya generik; melonggarkan constraint itu
 * supaya head_tracer bisa menyimpan user dengan nama role baru sungguhan
 * adalah pekerjaan Fase 4 (admin UI manajemen role) -- dicatat sebagai
 * follow-up di laporan, bukan cakupan Fase 2. scopedParams() sendiri hanya
 * butuh objek User yang punya atribut ->role/->org_unit_id/->program, jadi
 * batasan itu tidak menghalangi tes trait ini.
 */
class EnforcesProdiScopeTest extends TestCase
{
    private const CONN = 'oltp';

    /** @var int[] */
    private array $createdRoleIds = [];
    /** @var int[] */
    private array $createdProgramIds = [];
    /** @var int[] */
    private array $createdOrgUnitIds = [];

    protected function tearDown(): void
    {
        config(['institution.structure_template' => 'politeknik']);

        foreach ($this->createdProgramIds as $id) {
            DB::connection(self::CONN)->table('programs')->where('id', $id)->delete();
        }
        foreach ($this->createdOrgUnitIds as $id) {
            DB::connection(self::CONN)->table('org_units')->where('id', $id)->delete();
        }
        foreach ($this->createdRoleIds as $id) {
            DB::connection(self::CONN)->table('roles')->where('id', $id)->delete();
        }

        parent::tearDown();
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    private function harness(): object
    {
        return new class {
            use EnforcesProdiScope;

            public function call(Request $request): array
            {
                return $this->scopedParams($request);
            }
        };
    }

    private function requestFor(User $user, array $query = []): Request
    {
        $request = Request::create('/api/test', 'GET', $query);
        $request->setUserResolver(fn () => $user);

        return $request;
    }

    private function makeUser(array $attributes): User
    {
        $user = new User();
        $user->forceFill(array_merge([
            'id'    => $attributes['id'] ?? random_int(100000, 999999),
            'name'  => 'Test User',
            'email' => 'test-' . uniqid() . '@example.test',
        ], $attributes));

        return $user;
    }

    private function makeRole(string $name, string $scope): Role
    {
        $id = DB::connection(self::CONN)->table('roles')->insertGetId([
            'name'        => $name,
            'label'       => $name,
            'description' => 'fixture',
            'scope'       => $scope,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
        $this->createdRoleIds[] = $id;

        return Role::find($id);
    }

    private function levelId(string $institutionType, int $levelIndex): int
    {
        $id = DB::connection(self::CONN)->table('org_unit_types')
            ->where('institution_type', $institutionType)
            ->where('level_index', $levelIndex)
            ->value('id');

        $this->assertNotNull($id, "Prasyarat: org_unit_types ({$institutionType}, level {$levelIndex}) harus ada.");

        return (int) $id;
    }

    private function makeOrgUnit(int $typeId, string $name, ?int $parentId = null): int
    {
        $id = DB::connection(self::CONN)->table('org_units')->insertGetId([
            'org_unit_type_id' => $typeId,
            'parent_id'        => $parentId,
            'name'             => $name,
            'is_active'        => true,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);
        $this->createdOrgUnitIds[] = $id;

        return $id;
    }

    private function makeProgram(array $attributes): Program
    {
        $program = Program::create(array_merge([
            'name'   => 'Prodi Uji ' . uniqid(),
            'code'   => 'X' . random_int(1000, 9999),
            'degree' => 'D3',
        ], $attributes));
        $this->createdProgramIds[] = $program->id;

        return $program;
    }

    // ── 1. Regresi mode politeknik (dispatcher tetap memanggil jalur lama) ──

    public function test_politeknik_mode_kaprodi_forces_composite_key(): void
    {
        config(['institution.structure_template' => 'politeknik']);

        $program = $this->makeProgram(['name' => 'Teknik Informatika', 'degree' => 'D3', 'jurusan' => 'Teknik Informatika dan Komputer']);
        $user    = $this->makeUser(['role' => User::ROLE_KAPRODI, 'program_id' => $program->id]);
        $user->setRelation('program', $program);

        $params = $this->harness()->call($this->requestFor($user, ['nama_prodi' => 'Prodi Lain', 'jenjang' => 'D4']));

        $this->assertSame('Teknik Informatika', $params['nama_prodi']);
        $this->assertSame('D3', $params['jenjang']);
        $this->assertSame('Teknik Informatika dan Komputer', $params['jurusan']);
    }

    public function test_politeknik_mode_kaprodi_without_program_is_rejected(): void
    {
        config(['institution.structure_template' => 'politeknik']);

        $user = $this->makeUser(['role' => User::ROLE_KAPRODI, 'program_id' => null]);
        $user->setRelation('program', null);

        $this->expectException(HttpException::class);
        $this->harness()->call($this->requestFor($user));
    }

    public function test_politeknik_mode_kajur_forces_jurusan(): void
    {
        config(['institution.structure_template' => 'politeknik']);

        $user = $this->makeUser(['role' => User::ROLE_KAJUR, 'jurusan' => 'Teknik Elektro']);

        $params = $this->harness()->call($this->requestFor($user, ['jurusan' => 'Jurusan Lain']));

        $this->assertSame('Teknik Elektro', $params['jurusan']);
    }

    public function test_politeknik_mode_kajur_without_jurusan_is_rejected(): void
    {
        config(['institution.structure_template' => 'politeknik']);

        $user = $this->makeUser(['role' => User::ROLE_KAJUR, 'jurusan' => null]);

        $this->expectException(HttpException::class);
        $this->harness()->call($this->requestFor($user));
    }

    public function test_politeknik_mode_head_tracer_passthrough_unrestricted(): void
    {
        config(['institution.structure_template' => 'politeknik']);

        $user = $this->makeUser(['role' => User::ROLE_HEAD_TRACER]);

        $params = $this->harness()->call($this->requestFor($user, ['nama_prodi' => 'Apa Saja']));

        $this->assertSame('Apa Saja', $params['nama_prodi']);
    }

    // ── 2. Mode generik ──────────────────────────────────────────────────

    public function test_generic_mode_seluruh_jurusan_is_unrestricted(): void
    {
        config(['institution.structure_template' => 'universitas']);
        $role = $this->makeRole('rektor_uji_' . uniqid(), 'Seluruh Jurusan');

        $user = $this->makeUser(['role' => $role->name]);

        $params = $this->harness()->call($this->requestFor($user, ['nama_prodi' => 'Apa Saja']));

        $this->assertSame('Apa Saja', $params['nama_prodi']);
    }

    public function test_generic_mode_mid_level_scopes_to_unit_and_descendants(): void
    {
        config(['institution.structure_template' => 'universitas']);

        $fakultasType   = $this->levelId('universitas', 1);
        $departemenType = $this->levelId('universitas', 2);

        $fakultasId   = $this->makeOrgUnit($fakultasType, 'Fakultas Uji ' . uniqid());
        $departemenA  = $this->makeOrgUnit($departemenType, 'Departemen A', $fakultasId);
        $departemenB  = $this->makeOrgUnit($departemenType, 'Departemen B', $fakultasId);

        $role = $this->makeRole('dekan_uji_' . uniqid(), 'Jurusan');
        $user = $this->makeUser(['role' => $role->name, 'org_unit_id' => $fakultasId]);

        $params = $this->harness()->call($this->requestFor($user));

        $this->assertSame($fakultasId, $params['org_unit_id']);
        $this->assertEqualsCanonicalizing([$fakultasId, $departemenA, $departemenB], $params['org_unit_ids']);
    }

    public function test_generic_mode_mid_level_excludes_sibling_branch(): void
    {
        config(['institution.structure_template' => 'universitas']);

        $fakultasType = $this->levelId('universitas', 1);

        $fakultasX = $this->makeOrgUnit($fakultasType, 'Fakultas X ' . uniqid());
        $fakultasY = $this->makeOrgUnit($fakultasType, 'Fakultas Y ' . uniqid());

        $role = $this->makeRole('dekan_uji_' . uniqid(), 'Jurusan');
        $user = $this->makeUser(['role' => $role->name, 'org_unit_id' => $fakultasX]);

        $params = $this->harness()->call($this->requestFor($user));

        $this->assertNotContains($fakultasY, $params['org_unit_ids']);
    }

    public function test_generic_mode_mid_level_without_org_unit_is_rejected(): void
    {
        config(['institution.structure_template' => 'universitas']);
        $role = $this->makeRole('dekan_uji_' . uniqid(), 'Jurusan');
        $user = $this->makeUser(['role' => $role->name, 'org_unit_id' => null]);

        $this->expectException(HttpException::class);
        $this->harness()->call($this->requestFor($user));
    }

    public function test_generic_mode_leaf_level_composite_key(): void
    {
        config(['institution.structure_template' => 'universitas']);

        $fakultasType = $this->levelId('universitas', 1);
        $fakultasId   = $this->makeOrgUnit($fakultasType, 'Fakultas Uji ' . uniqid());

        $program = $this->makeProgram([
            'name'        => 'Sains Data',
            'degree'      => 'S1',
            'org_unit_id' => $fakultasId,
        ]);

        $role = $this->makeRole('kaprodi_uji_' . uniqid(), 'Program Studi');
        $user = $this->makeUser(['role' => $role->name, 'program_id' => $program->id]);
        $user->setRelation('program', $program);

        $params = $this->harness()->call($this->requestFor($user, ['nama_prodi' => 'Ganti', 'jenjang' => 'S2']));

        $this->assertSame('Sains Data', $params['nama_prodi']);
        $this->assertSame('S1', $params['jenjang']);
        $this->assertSame($fakultasId, $params['org_unit_id']);
    }

    public function test_generic_mode_leaf_level_without_program_is_rejected(): void
    {
        config(['institution.structure_template' => 'universitas']);
        $role = $this->makeRole('kaprodi_uji_' . uniqid(), 'Program Studi');
        $user = $this->makeUser(['role' => $role->name, 'program_id' => null]);
        $user->setRelation('program', null);

        $this->expectException(HttpException::class);
        $this->harness()->call($this->requestFor($user));
    }

    public function test_generic_mode_unmapped_role_is_rejected(): void
    {
        config(['institution.structure_template' => 'universitas']);

        // Role name yang tidak punya baris `roles` sama sekali.
        $user = $this->makeUser(['role' => 'role_tak_dikenal_' . uniqid()]);

        $this->expectException(HttpException::class);
        $this->harness()->call($this->requestFor($user));
    }

    public function test_generic_mode_unrecognized_scope_value_is_rejected(): void
    {
        config(['institution.structure_template' => 'universitas']);
        $role = $this->makeRole('aneh_uji_' . uniqid(), 'Scope Tidak Dikenal');
        $user = $this->makeUser(['role' => $role->name]);

        $this->expectException(HttpException::class);
        $this->harness()->call($this->requestFor($user));
    }

    // ── 3. Isolasi composite-key: nama sama, jenjang beda ───────────────

    public function test_composite_key_does_not_leak_between_same_name_different_degree(): void
    {
        config(['institution.structure_template' => 'universitas']);

        $fakultasType = $this->levelId('universitas', 1);
        $fakultasId   = $this->makeOrgUnit($fakultasType, 'Fakultas Uji ' . uniqid());

        $programD3 = $this->makeProgram(['name' => 'Akuntansi', 'degree' => 'D3', 'org_unit_id' => $fakultasId]);
        $programD4 = $this->makeProgram(['name' => 'Akuntansi', 'degree' => 'D4', 'org_unit_id' => $fakultasId]);

        $role = $this->makeRole('kaprodi_uji_' . uniqid(), 'Program Studi');

        $userD3 = $this->makeUser(['role' => $role->name, 'program_id' => $programD3->id]);
        $userD3->setRelation('program', $programD3);
        $userD4 = $this->makeUser(['role' => $role->name, 'program_id' => $programD4->id]);
        $userD4->setRelation('program', $programD4);

        $paramsD3 = $this->harness()->call($this->requestFor($userD3));
        $paramsD4 = $this->harness()->call($this->requestFor($userD4));

        $this->assertSame('Akuntansi', $paramsD3['nama_prodi']);
        $this->assertSame('D3', $paramsD3['jenjang']);
        $this->assertSame('Akuntansi', $paramsD4['nama_prodi']);
        $this->assertSame('D4', $paramsD4['jenjang']);
        $this->assertNotSame($paramsD3['jenjang'], $paramsD4['jenjang']);
    }

    // ── 4. Dual-mode sanity: dispatcher benar-benar bercabang ───────────

    public function test_dispatcher_routes_by_structure_template_config(): void
    {
        $program = $this->makeProgram(['name' => 'Sistem Informasi', 'degree' => 'D4', 'jurusan' => 'Teknik Informatika dan Komputer']);
        $user    = $this->makeUser(['role' => User::ROLE_KAPRODI, 'program_id' => $program->id]);
        $user->setRelation('program', $program);

        config(['institution.structure_template' => 'politeknik']);
        $legacyParams = $this->harness()->call($this->requestFor($user));
        $this->assertSame('Sistem Informasi', $legacyParams['nama_prodi']);
        $this->assertArrayNotHasKey('org_unit_id', $legacyParams);

        // Role 'kaprodi' tidak punya baris `roles` custom di fixture ini,
        // tapi RoleSeeder produksi memang menyeed baris 'kaprodi' dengan
        // scope 'Program Studi' -- pastikan baris itu ada supaya jalur
        // generik bisa dites lewat role existing yang sama.
        $existingRole = Role::where('name', User::ROLE_KAPRODI)->first();
        if ($existingRole === null) {
            $this->markTestSkipped('Baris roles untuk kaprodi tidak ditemukan -- jalankan RoleSeeder terlebih dulu.');
        }

        config(['institution.structure_template' => 'universitas']);
        $genericParams = $this->harness()->call($this->requestFor($user));
        $this->assertSame('Sistem Informasi', $genericParams['nama_prodi']);
        $this->assertArrayHasKey('org_unit_id', $genericParams);
    }
}
