<?php
// app/Repositories/Transactional/AlumniProfileRepository.php
namespace App\Repositories\Transactional;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * AlumniProfileRepository — query persistence untuk aggregate root AlumniProfile.
 *
 * Aggregate ini meliputi tabel utama `alumni_profiles`. Akses ke tabel lain
 * (employment_records, education_records, responses) di-delegasikan ke
 * repository masing-masing. Repo ini hanya bertugas persistence, tidak tahu
 * soal business rules atau role-based filtering.
 */
class AlumniProfileRepository
{
    private const CONN = 'oltp';

    // ═══════════════════════════════════════════════════════════
    // READ
    // ═══════════════════════════════════════════════════════════

    /**
     * Cari alumni berdasarkan NIM atau email (untuk keperluan login alumni),
     * sekaligus join ke programs supaya FE dapat info prodi.
     */
    public function findByNimOrEmailWithProgram(string $identifier): ?object
    {
        return DB::connection(self::CONN)
            ->table('alumni_profiles')
            ->leftJoin('programs', 'alumni_profiles.program_id', '=', 'programs.id')
            ->select(
                'alumni_profiles.id',
                'alumni_profiles.nim',
                'alumni_profiles.name',
                'alumni_profiles.email',
                'alumni_profiles.phone',
                'alumni_profiles.program_id',
                'alumni_profiles.entry_year',
                'alumni_profiles.graduation_year',
                'alumni_profiles.is_active',
                'alumni_profiles.nik',
                'programs.name as program_name',
                'programs.code as program_code',
                'programs.degree as program_degree',
            )
            ->where(function ($q) use ($identifier) {
                $q->where('alumni_profiles.nim', $identifier)
                  ->orWhere('alumni_profiles.email', $identifier);
            })
            ->first();
    }

    /** Alumni by NIM — dipakai saat submit kuesioner (upsert check). */
    public function findByNim(string $nim): ?object
    {
        return DB::connection(self::CONN)
            ->table('alumni_profiles')
            ->where('nim', $nim)
            ->first();
    }

    /** Detail alumni + info prodi untuk halaman admin. */
    public function findByIdWithProgram(int $id): ?object
    {
        return DB::connection(self::CONN)
            ->table('alumni_profiles')
            ->leftJoin('programs', 'alumni_profiles.program_id', '=', 'programs.id')
            ->select(
                'alumni_profiles.*',
                'programs.name as program_name',
                'programs.jurusan as jurusan_name',
            )
            ->where('alumni_profiles.id', $id)
            ->first();
    }

    /**
     * Paginasi alumni + data employment/education untuk panel admin.
     *
     * @param array{program_id?: int, search?: string, questionnaire_id?: int} $filters
     */
    public function paginateForAdmin(array $filters, int $perPage): LengthAwarePaginator
    {
        $query = DB::connection(self::CONN)->table('alumni_profiles')
            ->leftJoin('programs', 'alumni_profiles.program_id', '=', 'programs.id')
            ->leftJoin('employment_records', 'alumni_profiles.id', '=', 'employment_records.alumni_id')
            ->leftJoin('education_records', 'alumni_profiles.id', '=', 'education_records.alumni_id')
            ->select(
                'alumni_profiles.*',
                'programs.name as program_name',
                'programs.jurusan as jurusan_name',
                'employment_records.employment_status',
                'employment_records.waiting_months',
                'employment_records.salary_current',
                'employment_records.company_name',
                'employment_records.job_title',
                'employment_records.work_city',
                'education_records.is_further_study',
                'education_records.institution_name',
            );

        if (!empty($filters['program_id'])) {
            $query->where('alumni_profiles.program_id', $filters['program_id']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('alumni_profiles.nim', 'like', "%{$search}%")
                  ->orWhere('alumni_profiles.name', 'like', "%{$search}%");
            });
        }

        return $query->paginate($perPage);
    }

    /**
     * Ambil semua alumni untuk laporan export (join programs + responses).
     *
     * @param array{program_id?: int, questionnaire_id?: int} $filters
     */
    public function getForReport(array $filters = []): Collection
    {
        $query = DB::connection(self::CONN)->table('alumni_profiles')
            ->leftJoin('responses', 'alumni_profiles.id', '=', 'responses.alumni_id')
            ->leftJoin('programs', 'alumni_profiles.program_id', '=', 'programs.id')
            ->select(
                'alumni_profiles.*',
                'responses.id as response_id',
                'programs.name as program_name',
                'programs.code as program_code',
                'programs.jurusan as jurusan_name',
            );

        if (!empty($filters['questionnaire_id'])) {
            $query->where('responses.questionnaire_id', $filters['questionnaire_id']);
        }
        if (!empty($filters['program_id'])) {
            $query->where('alumni_profiles.program_id', $filters['program_id']);
        }

        return collect($query->get());
    }

    // ═══════════════════════════════════════════════════════════
    // WRITE
    // ═══════════════════════════════════════════════════════════

    public function create(array $data): int
    {
        $now = now();
        return DB::connection(self::CONN)->table('alumni_profiles')->insertGetId(
            array_merge($data, ['created_at' => $now, 'updated_at' => $now])
        );
    }

    public function updateById(int $id, array $data): bool
    {
        return DB::connection(self::CONN)->table('alumni_profiles')
            ->where('id', $id)
            ->update(array_merge($data, ['updated_at' => now()])) > 0;
    }

    public function deleteById(int $id): bool
    {
        return DB::connection(self::CONN)->table('alumni_profiles')
            ->where('id', $id)
            ->delete() > 0;
    }

    /**
     * Upsert alumni by NIM — kalau sudah ada, update; kalau belum, insert.
     * Return alumni_id.
     */
    public function upsertByNim(string $nim, array $data): int
    {
        $existing = $this->findByNim($nim);
        $now = now();

        if ($existing) {
            DB::connection(self::CONN)->table('alumni_profiles')
                ->where('id', $existing->id)
                ->update(array_merge($data, ['updated_at' => $now]));
            return $existing->id;
        }

        return DB::connection(self::CONN)->table('alumni_profiles')->insertGetId(
            array_merge($data, ['nim' => $nim, 'created_at' => $now, 'updated_at' => $now])
        );
    }
}
