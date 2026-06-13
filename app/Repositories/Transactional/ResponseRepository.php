<?php
// app/Repositories/Transactional/ResponseRepository.php
namespace App\Repositories\Transactional;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * ResponseRepository — aggregate root Response.
 *
 * Mengelola 2 tabel:
 *   - responses          (header submission per kuesioner per alumni)
 *   - response_answers   (jawaban per pertanyaan)
 *
 * Repo ini tidak meng-handle transaction; tanggung jawab caller (service).
 */
class ResponseRepository
{
    private const CONN = 'oltp';

    // ═══════════════════════════════════════════════════════════
    // READ
    // ═══════════════════════════════════════════════════════════

    public function findByQuestionnaireAndAlumni(int $questionnaireId, int $alumniId): ?object
    {
        return DB::connection(self::CONN)->table('responses')
            ->where('questionnaire_id', $questionnaireId)
            ->where('alumni_id', $alumniId)
            ->first();
    }

    /** Ambil semua answers untuk sekumpulan response_id (dipakai report export). */
    public function getAnswersByResponseIds(array $responseIds): Collection
    {
        if (empty($responseIds)) {
            return collect();
        }
        return collect(
            DB::connection(self::CONN)->table('response_answers')
                ->whereIn('response_id', $responseIds)
                ->get()
        );
    }

    // ═══════════════════════════════════════════════════════════
    // WRITE
    // ═══════════════════════════════════════════════════════════

    public function deleteByQuestionnaireAndAlumni(int $questionnaireId, int $alumniId): void
    {
        DB::connection(self::CONN)->table('responses')
            ->where('questionnaire_id', $questionnaireId)
            ->where('alumni_id', $alumniId)
            ->delete();
    }

    public function createResponse(int $questionnaireId, int $alumniId, string $status = 'submitted'): int
    {
        $now = Carbon::now();
        return DB::connection(self::CONN)->table('responses')->insertGetId([
            'questionnaire_id' => $questionnaireId,
            'alumni_id'        => $alumniId,
            'status'           => $status,
            'submitted_at'     => $now,
            'created_at'       => $now,
            'updated_at'       => $now,
        ]);
    }

    /**
     * Bulk insert ke response_answers.
     * $records: list of ['question_code' => ..., 'answer_text' => ...] (response_id akan di-inject).
     */
    public function bulkInsertAnswers(int $responseId, array $records): void
    {
        if (empty($records)) {
            return;
        }
        $now = Carbon::now();
        $data = array_map(fn ($r) => [
            'response_id'   => $responseId,
            'question_code' => $r['question_code'],
            'answer_text'   => $r['answer_text'],
            'created_at'    => $now,
            'updated_at'    => $now,
        ], $records);

        DB::connection(self::CONN)->table('response_answers')->insert($data);
    }
}
