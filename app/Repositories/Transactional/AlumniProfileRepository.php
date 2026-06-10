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
                'programs.degree as program_degree',
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
                'programs.degree as program_degree',
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
                'programs.degree as program_degree',
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
     * Sama seperti paginateForAdmin tapi ditambah kolom `response_status`:
     *   - 'finished'    → responses.status IN ('submitted','verified')
     *   - 'ongoing'     → responses.status = 'started'
     *   - 'not_started' → no response row
     *
     * Dipakai di halaman Data Alumni Prodi (kaprodi).
     *
     * @param array{program_id?: int, search?: string, jurusan?: string, graduation_year?: int} $filters
     */
    public function paginateForAdminWithResponseStatus(array $filters, int $perPage): LengthAwarePaginator
    {
        $conn = DB::connection(self::CONN);

        // Subquery: ambil id kuesioner global published
        $globalQnrIds = $conn->table('questionnaires')
            ->whereNull('program_id')
            ->where('status', 'published')
            ->pluck('id');

        $query = $conn->table('alumni_profiles')
            ->leftJoin('programs', 'alumni_profiles.program_id', '=', 'programs.id')
            ->leftJoin('responses', function ($join) use ($globalQnrIds) {
                $join->on('responses.alumni_id', '=', 'alumni_profiles.id')
                     ->whereIn('responses.questionnaire_id', $globalQnrIds->isEmpty() ? [0] : $globalQnrIds->toArray());
            })
            ->select(
                'alumni_profiles.*',
                'programs.name as program_name',
                'programs.degree as program_degree',
                'programs.jurusan as jurusan_name',
                DB::raw("CASE
                    WHEN responses.status IN ('submitted','verified') THEN 'finished'
                    WHEN responses.status = 'started' THEN 'ongoing'
                    ELSE 'not_started'
                END as response_status"),
            );

        if (!empty($filters['program_id'])) {
            $query->where('alumni_profiles.program_id', $filters['program_id']);
        }

        if (!empty($filters['jurusan'])) {
            $query->where('programs.jurusan', $filters['jurusan']);
        }

        if (!empty($filters['graduation_year'])) {
            $query->where('alumni_profiles.graduation_year', $filters['graduation_year']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('alumni_profiles.nim', 'like', "%{$search}%")
                  ->orWhere('alumni_profiles.name', 'like', "%{$search}%");
            });
        }

        return $query->orderByDesc('alumni_profiles.graduation_year')->orderByDesc('alumni_profiles.id')->paginate($perPage);
    }

    /**
     * List semua alumni yang ditargetkan oleh kuesioner tertentu,
     * termasuk yang belum mengisi (LEFT JOIN responses).
     *
     * Status ada 3 (mutlak):
     *   - 'finished'    → status IN ('submitted','verified')
     *   - 'ongoing'     → status = 'started' (response sudah dibuat tapi belum submit)
     *   - 'not_started' → belum ada response row sama sekali
     *
     * @param array{program_id?: int, search?: string, questionnaire_id: int} $filters
     */
    public function paginateRespondentsByQuestionnaire(array $filters, int $perPage): LengthAwarePaginator
    {
        $conn = DB::connection(self::CONN);
        $questionnaireId = $filters['questionnaire_id'];

        // Ambil info kuesioner untuk filter target
        $questionnaire = $conn->table('questionnaires')
            ->where('id', $questionnaireId)
            ->select('program_id', 'target_graduation_years')
            ->first();

        $query = $conn->table('alumni_profiles')
            ->leftJoin('programs', 'alumni_profiles.program_id', '=', 'programs.id')
            ->leftJoin('responses', function ($join) use ($questionnaireId) {
                $join->on('responses.alumni_id', '=', 'alumni_profiles.id')
                     ->where('responses.questionnaire_id', '=', $questionnaireId);
            })
            ->select(
                'alumni_profiles.id',
                'alumni_profiles.nim',
                'alumni_profiles.name',
                'alumni_profiles.email',
                'alumni_profiles.program_id',
                'alumni_profiles.graduation_year',
                'programs.name as program_name',
                'programs.degree as program_degree',
                'programs.jurusan as jurusan_name',
                'responses.id as response_id',
                DB::raw("CASE
                    WHEN responses.status IN ('submitted','verified') THEN 'finished'
                    WHEN responses.status = 'started' THEN 'ongoing'
                    ELSE 'not_started'
                END as response_status"),
                'responses.submitted_at as response_submitted_at',
                'responses.created_at as response_created_at',
                'responses.updated_at as response_updated_at',
            );

        // Scope berdasarkan target kuesioner
        if ($questionnaire && $questionnaire->program_id) {
            $query->where('alumni_profiles.program_id', $questionnaire->program_id);
        }

        if ($questionnaire && $questionnaire->target_graduation_years) {
            $years = json_decode($questionnaire->target_graduation_years, true);
            if (!empty($years)) {
                $query->whereIn('alumni_profiles.graduation_year', $years);
            }
        }

        // Filter tambahan dari request
        if (!empty($filters['program_id'])) {
            $query->where('alumni_profiles.program_id', $filters['program_id']);
        }

        if (!empty($filters['jurusan'])) {
            $query->where('programs.jurusan', $filters['jurusan']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('alumni_profiles.nim', 'like', "%{$search}%")
                  ->orWhere('alumni_profiles.name', 'like', "%{$search}%");
            });
        }

        return $query->orderByRaw("CASE
                WHEN responses.status IN ('submitted','verified') THEN 3
                WHEN responses.status = 'started' THEN 2
                ELSE 1
            END")
            ->orderBy('alumni_profiles.name')
            ->paginate($perPage);
    }

    /**
     * Hitung stats alumni per prodi (atau semua kalau $programId null).
     *
     * Return: ['total' => int, 'finished' => int, 'ongoing' => int, 'not_started' => int, 'answered' => int, 'unanswered' => int]
     */
    public function countStatsByProgram(?int $programId, ?string $jurusan = null, ?int $graduationYear = null): array
    {
        $conn = DB::connection(self::CONN);

        // Total alumni
        $totalQuery = $conn->table('alumni_profiles');
        if ($programId !== null) {
            $totalQuery->where('program_id', $programId);
        } elseif ($jurusan !== null) {
            $totalQuery->join('programs', 'alumni_profiles.program_id', '=', 'programs.id')
                ->where('programs.jurusan', $jurusan);
        }
        if ($graduationYear !== null) {
            $totalQuery->where('alumni_profiles.graduation_year', $graduationYear);
        }
        $total = $totalQuery->count();

        // Kuesioner global published
        $globalQnrIds = $conn->table('questionnaires')
            ->whereNull('program_id')
            ->where('status', 'published')
            ->pluck('id');

        if ($globalQnrIds->isEmpty()) {
            return ['total' => $total, 'finished' => 0, 'ongoing' => 0, 'not_started' => $total, 'answered' => 0, 'unanswered' => $total];
        }

        // Finish: submitted or verified
        $finishQuery = $conn->table('alumni_profiles')
            ->join('responses', 'responses.alumni_id', '=', 'alumni_profiles.id')
            ->whereIn('responses.questionnaire_id', $globalQnrIds->toArray())
            ->whereIn('responses.status', ['submitted', 'verified']);
        if ($programId !== null) {
            $finishQuery->where('alumni_profiles.program_id', $programId);
        } elseif ($jurusan !== null) {
            $finishQuery->join('programs', 'alumni_profiles.program_id', '=', 'programs.id')
                ->where('programs.jurusan', $jurusan);
        }
        if ($graduationYear !== null) {
            $finishQuery->where('alumni_profiles.graduation_year', $graduationYear);
        }
        $finish = $finishQuery->distinct('alumni_profiles.id')->count('alumni_profiles.id');

        // Ongoing: started
        $ongoingQuery = $conn->table('alumni_profiles')
            ->join('responses', 'responses.alumni_id', '=', 'alumni_profiles.id')
            ->whereIn('responses.questionnaire_id', $globalQnrIds->toArray())
            ->where('responses.status', 'started');
        if ($programId !== null) {
            $ongoingQuery->where('alumni_profiles.program_id', $programId);
        } elseif ($jurusan !== null) {
            $ongoingQuery->join('programs', 'alumni_profiles.program_id', '=', 'programs.id')
                ->where('programs.jurusan', $jurusan);
        }
        if ($graduationYear !== null) {
            $ongoingQuery->where('alumni_profiles.graduation_year', $graduationYear);
        }
        $ongoing = $ongoingQuery->distinct('alumni_profiles.id')->count('alumni_profiles.id');

        $notStarted = max($total - $finish - $ongoing, 0);

        return [
            'total'       => $total,
            'finished'    => $finish,
            'ongoing'     => $ongoing,
            'not_started' => $notStarted,
            'answered'    => $finish,
            'unanswered'  => $total - $finish,
        ];
    }

    public function getAvailableGraduationYears(?int $programId, ?string $jurusan = null): array
    {
        $query = DB::connection(self::CONN)->table('alumni_profiles')
            ->whereNotNull('graduation_year');

        if ($programId !== null) {
            $query->where('program_id', $programId);
        } elseif ($jurusan !== null) {
            $query->join('programs', 'alumni_profiles.program_id', '=', 'programs.id')
                ->where('programs.jurusan', $jurusan);
        }

        return $query->distinct()->orderByDesc('graduation_year')->pluck('graduation_year')->toArray();
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
                'programs.degree as program_degree',
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

    /** Bulk insert alumni rows (used by Excel import). */
    public function bulkInsert(array $rows): void
    {
        $now = now();
        $records = array_map(fn ($row) => array_merge($row, ['created_at' => $now, 'updated_at' => $now]), $rows);

        foreach (array_chunk($records, 100) as $chunk) {
            DB::connection(self::CONN)->table('alumni_profiles')->insert($chunk);
        }
    }
}
