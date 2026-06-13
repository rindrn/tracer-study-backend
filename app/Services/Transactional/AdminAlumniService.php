<?php
// app/Services/Transactional/AdminAlumniService.php
namespace App\Services\Transactional;

use App\Exceptions\BusinessException;
use App\Models\Transactional\User;
use App\Repositories\Transactional\AlumniProfileRepository;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * AdminAlumniService — business logic CRUD alumni untuk panel admin.
 *
 * Tanggung jawab:
 * - Translate role user (admin, p2mpp, kaprodi) jadi filter scope.
 * - Enforce business rule: P2MPP read-only; kaprodi hanya bisa akses prodinya.
 * - Orkestrasi ke AlumniProfileRepository.
 */
class AdminAlumniService
{
    public function __construct(
        private readonly AlumniProfileRepository $alumniRepo,
    ) {}

    // ═══════════════════════════════════════════════════════════
    // LIST (paginate) — with has_responded flag for Kaprodi dashboard
    // ═══════════════════════════════════════════════════════════
    public function list(User $user, array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $filters = $this->applyRoleScope($user, $filters);
        return $this->alumniRepo->paginateForAdminWithResponseStatus($filters, $perPage);
    }

    // ═══════════════════════════════════════════════════════════
    // STATS — total / sudah mengisi / belum mengisi / response rate
    // ═══════════════════════════════════════════════════════════
    /**
     * Return statistik alumni untuk dashboard (kaprodi = prodinya saja, admin = semua).
     *
     * Kuesioner yang dipakai sebagai baseline "sudah mengisi" adalah
     * kuesioner global (program_id NULL, status published) — lihat
     * AlumniProfileRepository::countStatsByProgram.
     */
    public function getStats(User $user): array
    {
        $programId = $user->isKaprodi() ? $user->program_id : null;

        $stats = $this->alumniRepo->countStatsByProgram($programId);

        $stats['response_rate'] = $stats['total'] > 0
            ? round($stats['answered'] / $stats['total'] * 100, 1)
            : 0.0;

        return $stats;
    }

    // ═══════════════════════════════════════════════════════════
    // SHOW
    // ═══════════════════════════════════════════════════════════
    public function show(User $user, int $id): object
    {
        $alumni = $this->alumniRepo->findByIdWithProgram($id);

        if (!$alumni) {
            throw new BusinessException('Alumni tidak ditemukan.', 404);
        }

        $this->assertKaprodiCanAccessProgram($user, $alumni->program_id);

        return $alumni;
    }

    // ═══════════════════════════════════════════════════════════
    // CREATE
    // ═══════════════════════════════════════════════════════════
    public function create(User $user, array $data): int
    {
        $this->assertCanWrite($user);

        // Kaprodi: paksa program_id ke prodinya
        if ($user->isKaprodi()) {
            $data['program_id'] = $user->program_id;
        }

        return $this->alumniRepo->create($data);
    }

    // ═══════════════════════════════════════════════════════════
    // UPDATE
    // ═══════════════════════════════════════════════════════════
    public function update(User $user, int $id, array $data): void
    {
        $this->assertCanWrite($user);

        $alumni = $this->alumniRepo->findByIdWithProgram($id);
        if (!$alumni) {
            throw new BusinessException('Alumni tidak ditemukan.', 404);
        }

        $this->assertKaprodiCanAccessProgram($user, $alumni->program_id, forWrite: true);

        // Kaprodi tidak boleh memindahkan alumni ke prodi lain
        if ($user->isKaprodi() && isset($data['program_id'])) {
            unset($data['program_id']);
        }

        $this->alumniRepo->updateById($id, $data);
    }

    // ═══════════════════════════════════════════════════════════
    // DELETE
    // ═══════════════════════════════════════════════════════════
    public function delete(User $user, int $id): void
    {
        $this->assertCanWrite($user);

        $alumni = $this->alumniRepo->findByIdWithProgram($id);
        if (!$alumni) {
            throw new BusinessException('Alumni tidak ditemukan.', 404);
        }

        $this->assertKaprodiCanAccessProgram($user, $alumni->program_id, forWrite: true);

        $this->alumniRepo->deleteById($id);
    }

    // ═══════════════════════════════════════════════════════════
    // Helpers (private)
    // ═══════════════════════════════════════════════════════════

    /** Tambahkan filter program_id untuk kaprodi supaya hanya lihat alumni prodinya. */
    private function applyRoleScope(User $user, array $filters): array
    {
        if ($user->isKaprodi() && $user->program_id) {
            $filters['program_id'] = $user->program_id;
        }
        return $filters;
    }

    /** P2MPP read-only — block semua operasi tulis. */
    private function assertCanWrite(User $user): void
    {
        if ($user->isP2mpp()) {
            throw new BusinessException('P2MPP tidak diizinkan mengubah data alumni.', 403);
        }
    }

    /** Kaprodi tidak boleh akses alumni di luar prodinya. */
    private function assertKaprodiCanAccessProgram(User $user, ?int $programId, bool $forWrite = false): void
    {
        if ($user->isKaprodi() && $programId !== $user->program_id) {
            $verb = $forWrite ? 'mengubah' : 'mengakses';
            throw new BusinessException(
                "Anda tidak memiliki hak akses untuk {$verb} alumni prodi lain.",
                403,
            );
        }
    }
}
