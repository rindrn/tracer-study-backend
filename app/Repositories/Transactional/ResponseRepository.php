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

    /**
     * Kuesioner mana saja, dari daftar yang diberikan, yang pengisiannya sudah
     * SELESAI (submitted/verified) bagi seorang alumni.
     *
     * Dipakai jalur submit untuk menegakkan RBAC-18 (satu kali pengisian per
     * kuesioner). Sengaja mengembalikan daftar, bukan boolean: alumni bisa
     * punya beberapa kuesioner aktif sekaligus, dan pesan galatnya perlu
     * menyebut kuesioner MANA yang sudah terkunci — bukan sekadar menolak
     * seluruh kiriman tanpa keterangan.
     *
     * @param  array<int> $questionnaireIds
     * @return array<int>
     */
    public function findSubmittedQuestionnaireIds(int $alumniId, array $questionnaireIds): array
    {
        if (empty($questionnaireIds)) {
            return [];
        }

        return DB::connection(self::CONN)->table('responses')
            ->where('alumni_id', $alumniId)
            ->whereIn('questionnaire_id', $questionnaireIds)
            ->whereIn('status', ['submitted', 'verified'])
            ->orderBy('questionnaire_id')
            ->pluck('questionnaire_id')
            ->map(fn ($id) => (int) $id)
            ->all();
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

    /**
     * Hapus pengisian yang BELUM selesai ('started') untuk satu pasangan
     * kuesioner-alumni. Dipakai jalur submit: draf yang tertinggal digantikan
     * baris submisi yang baru.
     *
     * Baris 'submitted'/'verified' sengaja TIDAK ikut terhapus. Dulu method ini
     * menghapus tanpa memandang status, dan itulah yang membuat
     * UNIQUE(questionnaire_id, alumni_id) tidak pernah berfungsi sebagai
     * penjaga: kiriman kedua menghapus dulu baris lamanya, jadi aturan uniknya
     * tidak pernah tersentuh dan jawaban alumni tertimpa diam-diam. Sekarang
     * pengisian yang sudah selesai ditolak lebih awal di
     * TracerStudySubmitService (RBAC-18), dan lapisan ini menjadi jaring
     * pengaman kedua: seandainya pemeriksaan itu terlewat, insert-nya akan
     * gagal karena bentrok unique — berisik, bukan diam-diam menimpa.
     *
     * @return int jumlah draf yang terhapus
     */
    public function deleteDraftByQuestionnaireAndAlumni(int $questionnaireId, int $alumniId): int
    {
        return DB::connection(self::CONN)->table('responses')
            ->where('questionnaire_id', $questionnaireId)
            ->where('alumni_id', $alumniId)
            ->where('status', 'started')
            ->delete();   // response_answers ikut terhapus lewat cascade FK
    }

    /**
     * Kembalikan status responden dari finished (submitted/verified) ke
     * ongoing (started), DENGAN MEMPERTAHANKAN seluruh jawabannya.
     *
     * Sebelumnya method ini menghapus response_answers lalu menghapus baris
     * responses-nya, sehingga namanya menyesatkan: tidak ada yang menjadi
     * ongoing, jawaban alumni hilang permanen, dan alumni harus mengisi
     * ulang dari kosong. Itu bertentangan dengan ISI-03.
     *
     * Sekarang cukup ubah status. Tiga jalur yang sudah ada langsung
     * bekerja tanpa perubahan:
     *
     *   - respondedQuestionnaireIds() tidak menghitung 'started', sehingga
     *     borang alumni terbuka kembali;
     *   - GET tracer-study/draft memuat response_answers yang tertinggal
     *     sebagai draf, sehingga jawaban lama muncul kembali di formulir;
     *   - ETL hanya memproses 'submitted', sehingga alumni ini tidak ikut
     *     di snapshot berikutnya. Baris fakta snapshot LAMA sengaja tidak
     *     disentuh -- isinya pernyataan historis "per tanggal itu keadaan
     *     alumni ini begini", yang tetap benar meski jawabannya kini
     *     dibuka kembali.
     *
     * submitted_at dikosongkan karena pengirimannya dibatalkan. started_at
     * dibiarkan apa adanya: kalau baris ini lahir sebelum fitur draf ada,
     * nilainya memang tidak pernah diketahui, dan mengisinya dengan now()
     * akan memalsukan durasi pengisian.
     */
    public function resetToOngoing(int $questionnaireId, int $alumniId): bool
    {
        return $this->reopenForAlumni($alumniId, [$questionnaireId]) > 0;
    }

    /**
     * Buka kembali pengisian terkirim milik satu alumni pada sekumpulan
     * kuesioner.
     *
     * Membuka sebagian kini SAH, dan itu perubahan yang disengaja. Dulu
     * lingkupnya dipaksa mencakup seluruh kuesioner aktif alumni karena
     * membuka satu saja tidak menghasilkan apa-apa: status pengisian dibaca
     * sebagai satu boleaan untuk semua kuesioner sekaligus, sehingga alumni
     * tetap tertahan di layar "Anda Sudah Mengisi"; dan seandainya bisa masuk
     * pun, kuesioner yang masih terkirim ikut tampil kosong lalu tertimpa saat
     * dikirim.
     *
     * Keduanya sudah ditutup: statusnya kini per kuesioner
     * (QuestionnaireFetchService::respondedQuestionnaireIds), formulir hanya
     * menampilkan kuesioner yang belum selesai, dan submit hanya menyentuh
     * kuesioner yang memang terbuka
     * (TracerStudySubmitService::resolveTargets). Penentuan lingkupnya
     * dipindahkan ke ResponseReopenService — di sini tinggal menjalankan.
     *
     * @param  array<int> $questionnaireIds kuesioner yang hendak dibuka kembali;
     *                    sudah disaring pemanggil terhadap kuesioner aktif alumni
     * @return int jumlah pengisian yang berhasil dibuka kembali
     */
    public function reopenForAlumni(int $alumniId, array $questionnaireIds): int
    {
        if (empty($questionnaireIds)) {
            return 0;
        }

        return DB::connection(self::CONN)->table('responses')
            ->where('alumni_id', $alumniId)
            ->whereIn('questionnaire_id', $questionnaireIds)
            ->whereIn('status', ['submitted', 'verified'])
            ->update([
                'status'       => 'started',
                'submitted_at' => null,
                'updated_at'   => Carbon::now(),
            ]);
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
     * Upsert draf: satu baris responses per (kuesioner, alumni), DIPERTAHANKAN
     * lintas autosave.
     *
     * Bedanya dengan jalur submit yang delete-lalu-insert: autosave berjalan
     * ratusan kali selama satu pengisian. Kalau barisnya dibuang dan dibuat
     * ulang tiap kali, primary key-nya berganti terus sehingga tidak ada yang
     * bisa merujuk draf itu, dan sequence-nya membengkak percuma. Tabel
     * responses sudah punya UNIQUE(questionnaire_id, alumni_id) — aturan itu
     * seharusnya dipatuhi dengan memperbarui, bukan menghapus.
     *
     * Jawaban tetap ditulis ulang seluruhnya tiap simpan (~100 baris). Itu
     * disengaja: menghitung selisih per pertanyaan menambah rumit tanpa
     * kebutuhan yang terbukti. Yang mahal dan berbahaya adalah churn baris
     * induknya, dan itu yang dihilangkan di sini.
     *
     * @return int|null response_id, atau null kalau barisnya sudah submitted/
     *         verified — draf tidak boleh menimpa pengisian yang sudah selesai.
     */
    public function upsertDraft(int $questionnaireId, int $alumniId, array $records): ?int
    {
        $now      = Carbon::now();
        $existing = $this->findByQuestionnaireAndAlumni($questionnaireId, $alumniId);

        if ($existing !== null && in_array($existing->status, ['submitted', 'verified'], strict: true)) {
            return null;
        }

        if ($existing !== null) {
            DB::connection(self::CONN)->table('responses')
                ->where('id', $existing->id)
                ->update([
                    // started_at hanya diisi kalau belum ada — nilainya adalah
                    // kapan alumni MULAI, bukan kapan terakhir menyimpan.
                    'started_at' => $existing->started_at ?? $now,
                    'updated_at' => $now,
                ]);
            $responseId = $existing->id;
        } else {
            $responseId = $this->createResponse($questionnaireId, $alumniId, 'started');
        }

        DB::connection(self::CONN)->table('response_answers')
            ->where('response_id', $responseId)
            ->delete();
        $this->bulkInsertAnswers($responseId, $records);

        return $responseId;
    }

    /**
     * Ambil draf (status 'started') milik alumni untuk sekumpulan kuesioner.
     * Baris yang sudah submitted/verified sengaja diabaikan — itu bukan draf.
     *
     * @return array{answers: array<string,string>, saved_at: ?string}
     */
    public function getDraft(array $questionnaireIds, int $alumniId): array
    {
        if (empty($questionnaireIds)) {
            return ['answers' => [], 'saved_at' => null];
        }

        $rows = DB::connection(self::CONN)->table('responses')
            ->whereIn('questionnaire_id', $questionnaireIds)
            ->where('alumni_id', $alumniId)
            ->where('status', 'started')
            ->get();

        if ($rows->isEmpty()) {
            return ['answers' => [], 'saved_at' => null];
        }

        $answers = DB::connection(self::CONN)->table('response_answers')
            ->whereIn('response_id', $rows->pluck('id')->toArray())
            ->get()
            ->pluck('answer_text', 'question_code')
            ->toArray();

        return [
            'answers' => $answers,
            // ISO8601 supaya FE bisa membandingkannya dengan waktu draf lokal
            // tanpa menebak zona waktu.
            'saved_at' => Carbon::parse($rows->max('updated_at'))->toIso8601String(),
        ];
    }

    /**
     * Hapus draf milik alumni ("mulai ulang"). Hanya menyentuh status 'started'
     * — pengisian yang sudah dikirim tidak boleh ikut terhapus dari sini.
     *
     * @return int jumlah draf yang terhapus
     */
    public function deleteDraft(array $questionnaireIds, int $alumniId): int
    {
        if (empty($questionnaireIds)) {
            return 0;
        }

        return DB::connection(self::CONN)->table('responses')
            ->whereIn('questionnaire_id', $questionnaireIds)
            ->where('alumni_id', $alumniId)
            ->where('status', 'started')
            ->delete();   // response_answers ikut terhapus lewat cascade FK
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
