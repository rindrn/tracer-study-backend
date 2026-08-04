<?php
// app/Services/Transactional/TracerStudyDraftService.php
namespace App\Services\Transactional;

use App\Exceptions\BusinessException;
use App\Models\Transactional\AlumniProfile;
use App\Repositories\Transactional\QuestionnaireRepository;
use App\Repositories\Transactional\ResponseRepository;
use Illuminate\Support\Facades\DB;

/**
 * TracerStudyDraftService — simpan/muat/hapus pengisian yang belum selesai.
 *
 * Draf disimpan sebagai baris responses berstatus 'started'. Statusnya sengaja
 * memakai enum yang sudah ada, bukan tabel terpisah, supaya seluruh lapisan
 * yang sudah membedakan status ikut benar tanpa perubahan: ETL hanya memproses
 * 'submitted', response rate menghitung 'started' sebagai "Belum Mengisi", dan
 * halaman Data Alumni menampilkannya sebagai "Ongoing".
 *
 * PERBEDAAN PENTING dengan TracerStudySubmitService:
 *
 *   - Identitas TIDAK diambil dari payload. alumni_id datang dari token guard
 *     'alumni', dan prodi/tahun lulus dibaca dari alumni_profiles. Draf
 *     karenanya tidak pernah menyentuh tabel alumni sama sekali — pengisian
 *     setengah jadi tidak bisa menimpa data alumni dengan nilai kosong.
 *
 *   - Barisnya di-update di tempat, bukan dihapus-lalu-dibuat. Lihat
 *     ResponseRepository::upsertDraft().
 *
 *   - Tidak ada validasi kelengkapan. Draf memang setengah jadi; aturan wajib
 *     isi baru ditegakkan saat submit.
 */
class TracerStudyDraftService
{
    private const CONN = 'oltp';

    /** Kunci identitas yang tidak disimpan sebagai jawaban (ada di alumni_profiles). */
    private const IDENTITY_KEYS = ['nim', 'name', 'email', 'phone', 'tahun_lulus', 'kdpstmsmh', 'kode_pt', 'nik', 'npwp'];

    public function __construct(
        private readonly QuestionnaireRepository $questionnaireRepo,
        private readonly ResponseRepository      $responseRepo,
    ) {}

    /**
     * Simpan draf. Aman dipanggil berulang kali (autosave).
     *
     * @param array<string,mixed> $answers jawaban berkunci question_code
     * @return array{saved_at: string, questionnaire_ids: array<int>}
     */
    public function save(AlumniProfile $alumni, array $answers): array
    {
        $questionnaires = $this->resolveQuestionnaires($alumni);

        $records = $this->toAnswerRecords($answers);

        $savedIds = [];
        DB::connection(self::CONN)->transaction(function () use ($questionnaires, $alumni, $records, &$savedIds) {
            foreach ($questionnaires as $qnr) {
                // Kuesioner prodi hanya menerima kode miliknya, sama seperti
                // jalur submit — supaya isi draf dan isi submisi sebangun.
                $filtered = $qnr->is_global
                    ? $records
                    : array_values(array_filter(
                        $records,
                        fn ($r) => in_array($r['question_code'], $qnr->codes, strict: true),
                    ));

                $id = $this->responseRepo->upsertDraft($qnr->id, $alumni->id, $filtered);
                if ($id !== null) {
                    $savedIds[] = $qnr->id;
                }
            }
        });

        return [
            'saved_at'          => now()->toIso8601String(),
            'questionnaire_ids' => $savedIds,
        ];
    }

    /**
     * Muat draf tersimpan.
     *
     * @return array{answers: array<string,string>, saved_at: ?string}
     */
    public function get(AlumniProfile $alumni): array
    {
        $ids = collect($this->resolveQuestionnaires($alumni))->pluck('id')->all();

        return $this->responseRepo->getDraft($ids, $alumni->id);
    }

    /** Hapus draf ("mulai ulang"). Mengembalikan jumlah draf yang terhapus. */
    public function clear(AlumniProfile $alumni): int
    {
        $ids = collect($this->resolveQuestionnaires($alumni))->pluck('id')->all();

        return $this->responseRepo->deleteDraft($ids, $alumni->id);
    }

    // ═══════════════════════════════════════════════════════════
    // Private helpers
    // ═══════════════════════════════════════════════════════════

    /**
     * Kuesioner yang berlaku untuk alumni ini — global (wajib) + prodi (opsional),
     * persis seperti yang dipakai TracerStudySubmitService.
     *
     * @return array<object{id: int, is_global: bool, codes: array<string>}>
     */
    private function resolveQuestionnaires(AlumniProfile $alumni): array
    {
        $graduationYear = (int) ($alumni->graduation_year ?? 0);

        $global = $graduationYear > 0
            ? $this->questionnaireRepo->findActiveGlobalForYear($graduationYear)
            : $this->questionnaireRepo->findActiveGlobal();

        if (! $global) {
            throw new BusinessException('Sistem belum memiliki referensi Kuesioner aktif.', 500);
        }

        $result = [(object) ['id' => $global->id, 'is_global' => true, 'codes' => []]];

        if ($alumni->program_id) {
            $prodi = $graduationYear > 0
                ? $this->questionnaireRepo->findActiveByProgramForYear($alumni->program_id, $graduationYear)
                : $this->questionnaireRepo->findActiveByProgram($alumni->program_id);

            if ($prodi) {
                $result[] = (object) [
                    'id'        => $prodi->id,
                    'is_global' => false,
                    'codes'     => $this->questionnaireRepo->getQuestionCodesByQuestionnaireId($prodi->id),
                ];
            }
        }

        return $result;
    }

    /**
     * Normalisasi jawaban jadi baris response_answers.
     * Bentuknya disamakan dengan TracerStudySubmitService::persistResponse().
     *
     * @return array<array{question_code: string, answer_text: string}>
     */
    private function toAnswerRecords(array $answers): array
    {
        $records = [];

        foreach ($answers as $code => $value) {
            if (in_array($code, self::IDENTITY_KEYS, strict: true)) {
                continue;
            }
            if ($value === null || $value === '' || $value === []) {
                continue;
            }

            $records[] = [
                'question_code' => (string) $code,
                'answer_text'   => match (true) {
                    is_bool($value)  => $value ? '1' : '0',
                    is_array($value) => json_encode($value),
                    default          => (string) $value,
                },
            ];
        }

        return $records;
    }
}
