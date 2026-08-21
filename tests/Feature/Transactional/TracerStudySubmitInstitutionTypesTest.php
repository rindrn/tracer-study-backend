<?php

namespace Tests\Feature\Transactional;

use App\Models\Transactional\AlumniProfile;
use App\Models\Transactional\Program;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Memverifikasi bahwa alur pengisian kuesioner Tracer Study (POST
 * /api/tracer-study/submit) tetap AMAN pada ketiga template struktur
 * institusi (politeknik/universitas/institut) -- bukan hanya pada bentuk
 * POLBAN datar yang selama ini satu-satunya yang diuji.
 *
 * TracerStudySubmitService merangkai submisi murni lewat kode program studi
 * (kdpstmsmh -> programs.code) dan tidak pernah membaca org_units atau
 * institution.structure_template sama sekali -- lihat berkas itu. Tes ini
 * membuktikan itu secara empiris, bukan sekadar membaca kode: program yang
 * ditautkan ke unit organisasi bertingkat (Fakultas -> Departemen) pada mode
 * universitas/institut tetap resolve dan tersimpan dengan benar, dan tiga
 * penjagaan keamanan yang sudah ada (kepemilikan NIM, persetujuan privasi,
 * larangan isi-ulang RBAC-18) tetap tegak di ketiganya.
 *
 * Sebelum tes ini, jalur submit alumni SAMA SEKALI belum punya cakupan tes
 * -- baik untuk mode politeknik maupun generik.
 */
class TracerStudySubmitInstitutionTypesTest extends TestCase
{
    private const CONN = 'oltp';

    private array $createdProgramIds = [];
    private array $createdOrgUnitIds = [];
    private array $createdAlumniIds = [];
    private array $createdQuestionnaireIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        // phpunit.xml memaksa DB_CONNECTION=sqlite untuk lingkungan testing
        // (tanpa pdo_sqlite terpasang), tapi SubmitTracerStudyRequest memakai
        // aturan `exists:programs,code` / `exists:...` tanpa connection
        // eksplisit -- artinya query itu jatuh ke connection DEFAULT, bukan
        // 'oltp'. Tes lain di proyek ini tidak pernah menabrak ini karena
        // semuanya memanggil trait/service langsung, tidak lewat HTTP penuh.
        // Ini satu-satunya override; tidak mengubah phpunit.xml maupun
        // perilaku aplikasi di luar proses tes ini.
        config(['database.default' => 'oltp']);
    }

    protected function tearDown(): void
    {
        config(['institution.structure_template' => 'politeknik']);

        foreach ($this->createdQuestionnaireIds as $id) {
            DB::connection(self::CONN)->table('responses')->where('questionnaire_id', $id)->delete();
            DB::connection(self::CONN)->table('questionnaires')->where('id', $id)->delete();
        }
        foreach ($this->createdAlumniIds as $id) {
            DB::connection(self::CONN)->table('employment_records')->where('alumni_id', $id)->delete();
            DB::connection(self::CONN)->table('education_records')->where('alumni_id', $id)->delete();
            DB::connection(self::CONN)->table('stakeholder_contacts')->where('alumni_id', $id)->delete();
            DB::connection(self::CONN)->table('alumni_profiles')->where('id', $id)->delete();
        }
        foreach ($this->createdProgramIds as $id) {
            DB::connection(self::CONN)->table('programs')->where('id', $id)->delete();
        }
        foreach ($this->createdOrgUnitIds as $id) {
            DB::connection(self::CONN)->table('org_units')->where('id', $id)->delete();
        }

        parent::tearDown();
    }

    // ── Helpers ──────────────────────────────────────────────────────

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

    /** Bangun pohon unit organisasi sesuai template, kembalikan org_unit_id daun tempat prodi ditautkan. */
    private function leafOrgUnitFor(string $institutionType): int
    {
        return match ($institutionType) {
            'politeknik' => $this->makeOrgUnit($this->levelId('politeknik', 1), 'Jurusan Uji ' . uniqid()),
            'universitas' => $this->makeOrgUnit(
                $this->levelId('universitas', 2),
                'Departemen Uji ' . uniqid(),
                $this->makeOrgUnit($this->levelId('universitas', 1), 'Fakultas Uji ' . uniqid()),
            ),
            'institut' => $this->makeOrgUnit(
                $this->levelId('institut', 2),
                'Departemen Uji ' . uniqid(),
                $this->makeOrgUnit($this->levelId('institut', 1), 'Fakultas/Sekolah Uji ' . uniqid()),
            ),
            default => throw new \InvalidArgumentException($institutionType),
        };
    }

    private function makeProgram(int $orgUnitId): Program
    {
        $program = Program::create([
            'name'        => 'Prodi Uji ' . uniqid(),
            'code'        => 'X' . random_int(100000, 999999),
            'degree'      => 'D3',
            'jurusan'     => 'Jurusan Legacy Uji',
            'org_unit_id' => $orgUnitId,
            'is_active'   => true,
        ]);
        $this->createdProgramIds[] = $program->id;

        return $program;
    }

    private function makeGlobalQuestionnaire(): int
    {
        $id = DB::connection(self::CONN)->table('questionnaires')->insertGetId([
            'code'         => 'GLOBAL-UJI-' . uniqid(),
            'title'        => 'Kuesioner Global Uji',
            'period_year'  => (int) now()->year,
            'version'      => 1,
            'status'       => 'published',
            'published_at' => now(),
            'program_id'   => null,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
        $this->createdQuestionnaireIds[] = $id;

        return $id;
    }

    private function makeAlumni(int $programId, string $nim, bool $withConsent = true): AlumniProfile
    {
        $alumni = AlumniProfile::create([
            'nim'             => $nim,
            'name'            => 'Alumni Uji',
            'email'           => $nim . '@example.test',
            'program_id'      => $programId,
            'graduation_year' => (int) now()->year,
            'is_active'       => true,
            'consent_at'      => $withConsent ? now() : null,
            'consent_version' => $withConsent ? config('privacy.notice_version') : null,
        ]);
        $this->createdAlumniIds[] = $alumni->id;

        return $alumni;
    }

    private function submitPayload(Program $program, string $nim, int $questionnaireId): array
    {
        return [
            'nim'               => $nim,
            'name'              => 'Alumni Uji',
            'email'             => $nim . '@example.test',
            'tahun_lulus'       => (int) now()->year,
            'kdpstmsmh'         => $program->code,
            'questionnaire_ids' => [$questionnaireId],
        ];
    }

    public static function institutionTypeProvider(): array
    {
        return [
            'politeknik'  => ['politeknik'],
            'universitas' => ['universitas'],
            'institut'    => ['institut'],
        ];
    }

    // ── 1. Submisi berhasil dan tertaut ke prodi yang benar ───────────

    #[DataProvider('institutionTypeProvider')]
    public function test_submit_succeeds_and_links_correct_program_regardless_of_institution_type(string $institutionType): void
    {
        config(['institution.structure_template' => $institutionType]);

        $orgUnitId = $this->leafOrgUnitFor($institutionType);
        $program   = $this->makeProgram($orgUnitId);
        $qnrId     = $this->makeGlobalQuestionnaire();
        $nim       = 'NIM' . random_int(100000, 999999);
        $alumni    = $this->makeAlumni($program->id, $nim);

        Sanctum::actingAs($alumni, ['*'], 'alumni');

        $response = $this->postJson('/api/tracer-study/submit', $this->submitPayload($program, $nim, $qnrId));

        $response->assertStatus(201);

        $this->assertDatabaseHas('alumni_profiles', [
            'nim'        => $nim,
            'program_id' => $program->id,
        ], self::CONN);

        $this->assertDatabaseHas('responses', [
            'alumni_id'        => $alumni->id,
            'questionnaire_id' => $qnrId,
            'status'           => 'submitted',
        ], self::CONN);
    }

    // ── 2. Kepemilikan NIM tetap ditegakkan ────────────────────────────

    #[DataProvider('institutionTypeProvider')]
    public function test_submit_rejects_nim_mismatch_regardless_of_institution_type(string $institutionType): void
    {
        config(['institution.structure_template' => $institutionType]);

        $orgUnitId = $this->leafOrgUnitFor($institutionType);
        $program   = $this->makeProgram($orgUnitId);
        $qnrId     = $this->makeGlobalQuestionnaire();
        $ownNim    = 'NIM' . random_int(100000, 999999);
        $otherNim  = 'NIM' . random_int(100000, 999999);
        $alumni    = $this->makeAlumni($program->id, $ownNim);

        Sanctum::actingAs($alumni, ['*'], 'alumni');

        $response = $this->postJson('/api/tracer-study/submit', $this->submitPayload($program, $otherNim, $qnrId));

        $response->assertStatus(403);
        $this->assertDatabaseMissing('alumni_profiles', ['nim' => $otherNim], self::CONN);
    }

    // ── 3. Persetujuan privasi tetap wajib ─────────────────────────────

    #[DataProvider('institutionTypeProvider')]
    public function test_submit_requires_consent_regardless_of_institution_type(string $institutionType): void
    {
        config(['institution.structure_template' => $institutionType]);

        $orgUnitId = $this->leafOrgUnitFor($institutionType);
        $program   = $this->makeProgram($orgUnitId);
        $qnrId     = $this->makeGlobalQuestionnaire();
        $nim       = 'NIM' . random_int(100000, 999999);
        $alumni    = $this->makeAlumni($program->id, $nim, withConsent: false);

        Sanctum::actingAs($alumni, ['*'], 'alumni');

        $response = $this->postJson('/api/tracer-study/submit', $this->submitPayload($program, $nim, $qnrId));

        $response->assertStatus(451);
    }

    // ── 4. RBAC-18: isi-ulang tetap ditolak ────────────────────────────

    #[DataProvider('institutionTypeProvider')]
    public function test_submit_blocks_duplicate_submission_regardless_of_institution_type(string $institutionType): void
    {
        config(['institution.structure_template' => $institutionType]);

        $orgUnitId = $this->leafOrgUnitFor($institutionType);
        $program   = $this->makeProgram($orgUnitId);
        $qnrId     = $this->makeGlobalQuestionnaire();
        $nim       = 'NIM' . random_int(100000, 999999);
        $alumni    = $this->makeAlumni($program->id, $nim);

        Sanctum::actingAs($alumni, ['*'], 'alumni');

        $payload = $this->submitPayload($program, $nim, $qnrId);
        $this->postJson('/api/tracer-study/submit', $payload)->assertStatus(201);

        $this->postJson('/api/tracer-study/submit', $payload)->assertStatus(409);
    }

    // ── 5. Dua cabang unit organisasi bernama sama tidak saling bocor ──

    /**
     * Dua prodi dengan induk unit organisasi BERBEDA (dua Departemen di dua
     * Fakultas berbeda pada mode universitas, sengaja diberi nama level
     * menengah yang SAMA PERSIS) tidak boleh saling bocor lewat jalur
     * submit -- kiriman alumni prodi A harus tetap tertaut ke prodi A.
     */
    public function test_submit_does_not_cross_link_programs_under_different_org_unit_branches(): void
    {
        config(['institution.structure_template' => 'universitas']);

        $fakultasA = $this->makeOrgUnit($this->levelId('universitas', 1), 'Fakultas A ' . uniqid());
        $fakultasB = $this->makeOrgUnit($this->levelId('universitas', 1), 'Fakultas B ' . uniqid());
        $deptA     = $this->makeOrgUnit($this->levelId('universitas', 2), 'Departemen Sama', $fakultasA);
        $deptB     = $this->makeOrgUnit($this->levelId('universitas', 2), 'Departemen Sama', $fakultasB);

        $programA = $this->makeProgram($deptA);
        $programB = $this->makeProgram($deptB);
        $qnrId    = $this->makeGlobalQuestionnaire();

        $nimA    = 'NIM' . random_int(100000, 999999);
        $alumniA = $this->makeAlumni($programA->id, $nimA);

        Sanctum::actingAs($alumniA, ['*'], 'alumni');

        $this->postJson('/api/tracer-study/submit', $this->submitPayload($programA, $nimA, $qnrId))
            ->assertStatus(201);

        $this->assertDatabaseHas('alumni_profiles', [
            'nim'        => $nimA,
            'program_id' => $programA->id,
        ], self::CONN);
        $this->assertDatabaseMissing('alumni_profiles', [
            'nim'        => $nimA,
            'program_id' => $programB->id,
        ], self::CONN);
    }
}
