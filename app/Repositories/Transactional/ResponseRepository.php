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

    /**
     * Reset status responden dari finished (submitted/verified) ke ongoing (started).
     * Menghapus jawaban dan reset status agar alumni bisa mengisi ulang.
     */
    public function resetToOngoing(int $questionnaireId, int $alumniId): bool
    {
        $response = $this->findByQuestionnaireAndAlumni($questionnaireId, $alumniId);
        if (!$response || !in_array($response->status, ['submitted', 'verified'])) {
            return false;
        }

        // Hapus jawaban
        DB::connection(self::CONN)->table('response_answers')
            ->where('response_id', $response->id)
            ->delete();

        // Hapus response row agar alumni bisa submit ulang dari awal
        DB::connection(self::CONN)->table('responses')
            ->where('id', $response->id)
            ->delete();

        return true;
    }

    /**
     * Buat baris responses baru.
     *
     * submitted_at hanya diisi untuk status yang memang berarti "sudah dikirim".
     * Sebelumnya kolom itu selalu diisi now() apa pun statusnya — untuk baris
     * berstatus 'started' (pengisian belum selesai) itu keliru, dan metrik
     * durasi pengisian di SummaryRepository (started_at → submitted_at) akan
     * menghasilkan angka yang tampak sahih padahal karangan.
     *
     * started_at sengaja TIDAK diisi untuk submit sekali jalan: waktu alumni
     * benar-benar mulai mengisi tidak diketahui di titik ini, dan menuliskan
     * now() akan membuat durasi setiap orang jadi ~0 detik. NULL lebih jujur
     * daripada nol palsu (lihat catatan di migration restore_started_at).
     */
    public function createResponse(int $questionnaireId, int $alumniId, string $status = 'submitted'): int
    {
        $now      = Carbon::now();
        $isDone   = in_array($status, ['submitted', 'verified'], strict: true);

        return DB::connection(self::CONN)->table('responses')->insertGetId([
            'questionnaire_id' => $questionnaireId,
            'alumni_id'        => $alumniId,
            'status'           => $status,
            'started_at'       => $isDone ? null : $now,
            'submitted_at'     => $isDone ? $now  : null,
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
