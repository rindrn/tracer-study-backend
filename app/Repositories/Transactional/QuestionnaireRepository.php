<?php
// app/Repositories/Transactional/QuestionnaireRepository.php
namespace App\Repositories\Transactional;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * QuestionnaireRepository — aggregate root Questionnaire.
 *
 * Mengelola 4 tabel terkait form builder:
 *   - questionnaires            (header kuesioner)
 *   - questionnaire_sections    (bagian / section)
 *   - questionnaire_questions   (pertanyaan)
 *   - questionnaire_options     (opsi jawaban)
 *
 * Repo ini tidak wrap transaction — transaction di-handle di Service layer.
 */
class QuestionnaireRepository
{
    private const CONN = 'oltp';

    // ═══════════════════════════════════════════════════════════
    // READ — header kuesioner
    // ═══════════════════════════════════════════════════════════

    /** Semua kuesioner (raw header); kalau $programId diberikan, filter global + prodi tsb. */
    public function listHeaders(?int $programId = null): Collection
    {
        $query = DB::connection(self::CONN)->table('questionnaires');

        if ($programId !== null) {
            $query->where(function ($q) use ($programId) {
                $q->whereNull('program_id')
                  ->orWhere('program_id', $programId);
            });
        }

        return collect($query->orderByDesc('id')->get());
    }

    public function findHeaderById(int $id): ?object
    {
        return DB::connection(self::CONN)
            ->table('questionnaires')
            ->where('id', $id)
            ->first();
    }

    /** Kuesioner aktif global (program_id NULL, status published). */
    public function findActiveGlobal(): ?object
    {
        return DB::connection(self::CONN)->table('questionnaires')
            ->whereNull('program_id')
            ->where('status', 'published')
            ->first();
    }

    /** Kuesioner aktif untuk program tertentu. */
    public function findActiveByProgram(int $programId): ?object
    {
        return DB::connection(self::CONN)->table('questionnaires')
            ->where('program_id', $programId)
            ->where('status', 'published')
            ->first();
    }

    /** Published kuesioner untuk global OR prodi tertentu (list). */
    public function findActiveForProdi(int $programId): Collection
    {
        return collect(
            DB::connection(self::CONN)->table('questionnaires')
                ->where('status', 'published')
                ->where(function ($q) use ($programId) {
                    $q->whereNull('program_id')
                      ->orWhere('program_id', $programId);
                })
                ->get()
        );
    }

    /** Semua kuesioner prodi yang published, keyed by program_id. */
    public function listActiveProdiQuestionnaires(): Collection
    {
        return collect(
            DB::connection(self::CONN)->table('questionnaires')
                ->whereNotNull('program_id')
                ->where('status', 'published')
                ->get()
        )->keyBy('program_id');
    }

    // ═══════════════════════════════════════════════════════════
    // READ — sections / questions / options (grouped)
    // ═══════════════════════════════════════════════════════════

    /** Sections untuk banyak questionnaire, grouped by questionnaire_id. */
    public function getSectionsGrouped(array $questionnaireIds): Collection
    {
        return collect(
            DB::connection(self::CONN)->table('questionnaire_sections')
                ->whereIn('questionnaire_id', $questionnaireIds)
                ->orderBy('order_no')
                ->get()
        )->groupBy('questionnaire_id');
    }

    /** Questions untuk banyak questionnaire, flat list. */
    public function getQuestions(array $questionnaireIds): Collection
    {
        return collect(
            DB::connection(self::CONN)->table('questionnaire_questions')
                ->whereIn('questionnaire_id', $questionnaireIds)
                ->orderBy('order_no')
                ->get()
        );
    }

    /** Options untuk banyak question, grouped by question_id. */
    public function getOptionsGrouped(array $questionIds): Collection
    {
        if (empty($questionIds)) {
            return collect();
        }
        return collect(
            DB::connection(self::CONN)->table('questionnaire_options')
                ->whereIn('question_id', $questionIds)
                ->orderBy('order_no')
                ->get()
        )->groupBy('question_id');
    }

    /** Sections untuk 1 questionnaire. */
    public function getSectionsByQuestionnaireId(int $questionnaireId): Collection
    {
        return collect(
            DB::connection(self::CONN)->table('questionnaire_sections')
                ->where('questionnaire_id', $questionnaireId)
                ->orderBy('order_no')
                ->get()
        );
    }

    /** Questions untuk 1 questionnaire. */
    public function getQuestionsByQuestionnaireId(int $questionnaireId): Collection
    {
        return collect(
            DB::connection(self::CONN)->table('questionnaire_questions')
                ->where('questionnaire_id', $questionnaireId)
                ->orderBy('order_no')
                ->get()
        );
    }

    /** Kode pertanyaan untuk 1 questionnaire (dipakai TracerStudySubmit). */
    public function getQuestionCodesByQuestionnaireId(int $questionnaireId): array
    {
        return DB::connection(self::CONN)->table('questionnaire_questions')
            ->where('questionnaire_id', $questionnaireId)
            ->pluck('code')
            ->toArray();
    }

    /** Map question_text by code (untuk label header export). */
    public function getQuestionLabelsByCode(array $codes): array
    {
        if (empty($codes)) {
            return [];
        }
        return DB::connection(self::CONN)->table('questionnaire_questions')
            ->whereIn('code', $codes)
            ->pluck('question_text', 'code')
            ->toArray();
    }

    // ═══════════════════════════════════════════════════════════
    // READ — versi / counter
    // ═══════════════════════════════════════════════════════════

    public function nextVersionForCode(string $code): int
    {
        $latest = DB::connection(self::CONN)->table('questionnaires')
            ->where('code', $code)
            ->max('version');
        return $latest ? ((int) $latest + 1) : 1;
    }

    public function countResponses(int $questionnaireId): int
    {
        return DB::connection(self::CONN)->table('responses')
            ->where('questionnaire_id', $questionnaireId)
            ->count();
    }

    /**
     * Count responses per questionnaire. Kalau $programId ada, filter per prodi.
     * Return: collection of [questionnaire_id => count].
     */
    public function countResponsesGroupedAll(?int $programId = null): Collection
    {
        $query = DB::connection(self::CONN)->table('responses');

        if ($programId !== null) {
            $query->join('alumni_profiles', 'responses.alumni_id', '=', 'alumni_profiles.id')
                  ->where('alumni_profiles.program_id', $programId);
        }

        return $query
            ->selectRaw('responses.questionnaire_id, COUNT(*) as count')
            ->groupBy('responses.questionnaire_id')
            ->pluck('count', 'responses.questionnaire_id');
    }

    // ═══════════════════════════════════════════════════════════
    // WRITE — header kuesioner
    // ═══════════════════════════════════════════════════════════

    public function insertHeader(array $data): int
    {
        $now = Carbon::now();
        return DB::connection(self::CONN)->table('questionnaires')->insertGetId(
            array_merge($data, ['created_at' => $now, 'updated_at' => $now])
        );
    }

    public function updateHeader(int $id, array $data): bool
    {
        return DB::connection(self::CONN)->table('questionnaires')
            ->where('id', $id)
            ->update(array_merge($data, ['updated_at' => Carbon::now()])) > 0;
    }

    public function deleteHeader(int $id): bool
    {
        return DB::connection(self::CONN)->table('questionnaires')
            ->where('id', $id)
            ->delete() > 0;
    }

    // ═══════════════════════════════════════════════════════════
    // WRITE — sections / questions / options
    // ═══════════════════════════════════════════════════════════

    public function deleteSectionsAndQuestions(int $questionnaireId): void
    {
        // Options akan terhapus by CASCADE dari questions (jika FK on delete cascade ada)
        // Kalau tidak ada CASCADE, hapus dulu options by question_id.
        $questionIds = DB::connection(self::CONN)->table('questionnaire_questions')
            ->where('questionnaire_id', $questionnaireId)
            ->pluck('id')
            ->toArray();

        if (!empty($questionIds)) {
            DB::connection(self::CONN)->table('questionnaire_options')
                ->whereIn('question_id', $questionIds)
                ->delete();
        }

        DB::connection(self::CONN)->table('questionnaire_questions')
            ->where('questionnaire_id', $questionnaireId)
            ->delete();

        DB::connection(self::CONN)->table('questionnaire_sections')
            ->where('questionnaire_id', $questionnaireId)
            ->delete();
    }

    public function insertSection(array $data): int
    {
        $now = Carbon::now();
        return DB::connection(self::CONN)->table('questionnaire_sections')->insertGetId(
            array_merge($data, ['created_at' => $now, 'updated_at' => $now])
        );
    }

    public function insertQuestion(array $data): int
    {
        $now = Carbon::now();
        return DB::connection(self::CONN)->table('questionnaire_questions')->insertGetId(
            array_merge($data, ['created_at' => $now, 'updated_at' => $now])
        );
    }

    public function insertOption(array $data): void
    {
        $now = Carbon::now();
        DB::connection(self::CONN)->table('questionnaire_options')->insert(
            array_merge($data, ['created_at' => $now, 'updated_at' => $now])
        );
    }
}
