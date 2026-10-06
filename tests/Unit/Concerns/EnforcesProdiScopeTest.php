<?php

namespace Tests\Unit\Concerns;

use App\Http\Controllers\Api\Analytical\Concerns\EnforcesProdiScope;
use App\Models\Transactional\Fakultas;
use App\Models\Transactional\Jurusan;
use App\Models\Transactional\Program;
use App\Models\Transactional\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Harness publik untuk memanggil method protected di trait EnforcesProdiScope
 * tanpa perlu boot controller Laravel sungguhan (routing, middleware, dsb).
 */
class ProdiScopeHarness
{
    use EnforcesProdiScope;

    public function callScopedParams(Request $request): array
    {
        return $this->scopedParams($request);
    }

    public function callAssertProdiAccess(Request $request, string $requestedProdi): void
    {
        $this->assertProdiAccess($request, $requestedProdi);
    }
}

/**
 * Semua model di sini dibangun in-memory (tidak pernah di-save()), dan
 * setiap relasi yang dibaca trait di-preload lewat setRelation() supaya
 * tidak ada query yang benar-benar dieksekusi ke koneksi `oltp` (Postgres)
 * yang tidak tersedia di lingkungan unit test.
 */
class EnforcesProdiScopeTest extends TestCase
{
    private function harness(): ProdiScopeHarness
    {
        return new ProdiScopeHarness();
    }

    private function requestAs(User $user, array $query = []): Request
    {
        $request = Request::create('/api/analytical/dummy', 'GET', $query);
        $request->setUserResolver(fn () => $user);

        return $request;
    }

    /** #1: kaprodi tanpa program tertaut → 403, bukan lolos tanpa filter. */
    public function test_kaprodi_tanpa_program_ditolak(): void
    {
        $user = new User(['role' => User::ROLE_KAPRODI]);
        $user->setRelation('program', null);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('belum tertaut ke program studi');

        $this->harness()->callScopedParams($this->requestAs($user));
    }

    /** #2: kaprodi dengan program → nama_prodi & jenjang & jurusan dipaksa dari program miliknya, mengabaikan query param. */
    public function test_kaprodi_scope_dipaksa_dari_program_miliknya(): void
    {
        $program = new Program([
            'name'    => 'Teknik Informatika',
            'degree'  => 'D4',
            'jurusan' => 'Teknik Elektro',
        ]);

        $user = new User(['role' => User::ROLE_KAPRODI]);
        $user->setRelation('program', $program);

        // Query param mencoba "menyamar" jadi prodi lain -- harus diabaikan.
        $request = $this->requestAs($user, ['nama_prodi' => 'Akuntansi', 'jenjang' => 'D3']);

        $params = $this->harness()->callScopedParams($request);

        $this->assertSame('Teknik Informatika', $params['nama_prodi']);
        $this->assertSame('D4', $params['jenjang']);
        $this->assertSame('Teknik Elektro', $params['jurusan']);
    }

    /** #3: kajur tanpa jurusan ter-assign (jurusan_id null) → 403. */
    public function test_kajur_tanpa_jurusan_assigned_ditolak(): void
    {
        $user = new User(['role' => User::ROLE_KAJUR]);
        // jurusan_id tetap null -> BelongsTo pulang null tanpa query DB.

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('belum tertaut ke jurusan');

        $this->harness()->callScopedParams($this->requestAs($user));
    }

    /** #4: kajur dengan jurusan ter-assign → id_prodi_in terisi dari seluruh program di jurusannya. */
    public function test_kajur_scope_terisi_dari_seluruh_program_jurusannya(): void
    {
        // 'id' bukan $fillable di model Program -> forceFill() supaya tidak
        // diam-diam diabaikan seperti mass-assignment lewat konstruktor biasa.
        $programA = (new Program(['name' => 'Teknik Informatika D3']))->forceFill(['id' => 10]);
        $programB = (new Program(['name' => 'Teknik Informatika D4']))->forceFill(['id' => 11]);

        $jurusan = new Jurusan(['name' => 'Teknik Informatika']);
        $jurusan->setRelation('programs', collect([$programA, $programB]));

        $user = new User(['role' => User::ROLE_KAJUR, 'jurusan_id' => 1]);
        $user->setRelation('jurusanEntity', $jurusan);

        $params = $this->harness()->callScopedParams($this->requestAs($user));

        $this->assertEqualsCanonicalizing([10, 11], $params['id_prodi_in']);
        $this->assertSame('Teknik Informatika', $params['jurusan']);
    }

    /** #5: dekan meminta jurusan di luar cakupan fakultasnya → 403 (bukan diam-diam diabaikan). */
    public function test_dekan_meminta_jurusan_di_luar_fakultas_ditolak(): void
    {
        $programX = new Program(['id' => 20, 'name' => 'Akuntansi D3']);

        $jurusanScope = new Jurusan(['name' => 'Akuntansi']);
        $jurusanScope->setRelation('programs', collect([$programX]));

        $fakultas = new Fakultas(['name' => 'Fakultas Ekonomi']);
        $fakultas->setRelation('jurusans', collect([$jurusanScope]));

        $user = new User(['role' => User::ROLE_DEKAN, 'fakultas_id' => 1]);
        $user->setRelation('fakultas', $fakultas);

        // Minta jurusan yang BUKAN anggota fakultasnya.
        $request = $this->requestAs($user, ['jurusan' => 'Teknik Informatika']);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('tidak memiliki akses ke jurusan tersebut');

        $this->harness()->callScopedParams($request);
    }

    /** #6: kaprodi minta data prodi lain lewat param eksplisit (drill-down) → assertProdiAccess menolak. */
    public function test_kaprodi_assert_prodi_access_menolak_prodi_lain(): void
    {
        $program = new Program(['name' => 'Teknik Informatika']);

        $user = new User(['role' => User::ROLE_KAPRODI]);
        $user->setRelation('program', $program);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('hanya dapat mengakses data program studi Anda sendiri');

        $this->harness()->callAssertProdiAccess($this->requestAs($user), 'Akuntansi');
    }

    /** #6b: kaprodi minta prodinya sendiri → lolos, tidak ada exception. */
    public function test_kaprodi_assert_prodi_access_mengizinkan_prodi_sendiri(): void
    {
        $program = new Program(['name' => 'Teknik Informatika']);

        $user = new User(['role' => User::ROLE_KAPRODI]);
        $user->setRelation('program', $program);

        $this->harness()->callAssertProdiAccess($this->requestAs($user), 'Teknik Informatika');

        $this->assertTrue(true); // sampai baris ini berarti tidak abort().
    }
}
