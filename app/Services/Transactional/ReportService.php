<?php
// app/Services/Transactional/ReportService.php
namespace App\Services\Transactional;

use App\Exports\TracerStudyMultiSheetExport;
use App\Exports\Sheets\MinistrySheetExport;
use App\Models\Transactional\User;
use App\Repositories\Transactional\AlumniProfileRepository;
use App\Repositories\Transactional\ProgramRepository;
use App\Repositories\Transactional\QuestionnaireRepository;
use App\Repositories\Transactional\ResponseRepository;

/**
 * ReportService — build data untuk export Excel tracer study.
 *
 * PERBAIKAN PERFORMA (root cause timeout 60 detik, lihat riwayat
 * diskusi untuk detail investigasi):
 *
 * 1. buildProdiQuestionsByProgram() SEBELUMNYA memanggil 2 query
 *    terpisah PER PRODI di dalam loop. Sekarang di-batch: SATU query
 *    untuk semua questionnaire_id prodi sekaligus, di-group di memori.
 *
 * 2. Sheet "Data Kementrian" (yang menampung SEMUA alumni, bisa puluhan
 *    ribu baris) TIDAK LAGI dibangun dari Collection yang di-load penuh
 *    ke memory. MinistrySheetExport sekarang menerima QUERY BUILDER
 *    MENTAH (via AlumniProfileRepository::getForReportQuery()) dan
 *    membaca data dalam chunk (FromQuery + WithChunkReading) -- lihat
 *    MinistrySheetExport untuk detail.
 *
 * 3. Sheet per-prodi TETAP pakai Collection biasa (TIDAK diubah) --
 *    karena datanya sudah pasti jauh lebih kecil (alumni 1 prodi saja,
 *    bukan seluruh institusi), risiko memory di sana rendah dan tidak
 *    perlu kerumitan tambahan FromQuery.
 */
class ReportService
{
    private const IDENTITY_CODES = [
        'nimhsmsmh', 'kdptimsmh', 'tahun_lulus', 'kdpstmsmh',
        'nmmhsmsmh', 'telpomsmh', 'emailmsmh', 'nik', 'npwp',
    ];

    public function __construct(
        private readonly AlumniProfileRepository $alumniRepo,
        private readonly ResponseRepository      $responseRepo,
        private readonly QuestionnaireRepository $questionnaireRepo,
        private readonly ProgramRepository       $programRepo,
    ) {}

    /**
     * @param int $tahunLulus WAJIB diisi -- UI tidak lagi menyediakan
     *            opsi "Semua Tahun" (keputusan eksplisit, lihat catatan
     *            di ReportController soal alasan: campuran questionnaire_id
     *            berbeda dalam satu sheet bisa membingungkan kolom header).
     */
    public function buildAlumniResponsesExport(
        User $user,
        ?int $questionnaireId,
        int $tahunLulus,
    ): TracerStudyMultiSheetExport {
        $filters = array_filter([
            'program_id'      => $user->isKaprodi() ? $user->program_id : null,
            'jurusan'         => $user->isKajur() ? $user->jurusan : null,
            'questionnaire_id' => $questionnaireId,
            'graduation_year' => $tahunLulus,
        ], fn ($v) => $v !== null);

        // ── Sheet "Data Kementrian": query builder mentah, dibaca chunk
        //    oleh MinistrySheetExport sendiri, TIDAK di-execute di sini. ──
        $ministrySheet = new MinistrySheetExport(
            $this->alumniRepo->getForReportQuery($filters),
            $this->responseRepo,
            $this->questionnaireRepo,
            $user,
        );

        // ── Sheet per-prodi: tetap pakai Collection (data kecil, aman) ──
        $alumniProfiles = $this->alumniRepo->getForReport($filters);

        $responseIds = $alumniProfiles->pluck('response_id')->filter()->toArray();
        $answers     = $this->responseRepo->getAnswersByResponseIds($responseIds);

        $answersGrouped = $answers
            ->groupBy('response_id')
            ->map(fn ($items) => $items->pluck('answer_text', 'question_code')->toArray());

        $alumniData = $alumniProfiles->map(function ($item) use ($answersGrouped) {
            $item->answers = $item->response_id ? ($answersGrouped->get($item->response_id) ?? []) : [];
            return $item;
        });

        $restrictToProgramId = $user->isKaprodi() ? $user->program_id : null;

        $prodiQuestionsByProgram = $this->buildProdiQuestionsByProgram($restrictToProgramId);
        $alumniByProdi = $alumniData->groupBy('program_code');

        $prodiQuestionsGrouped = [];
        foreach ($alumniByProdi as $prodiCode => $prodiAlumni) {
            if (!$prodiCode) {
                continue;
            }

            $prodiProgram = $this->programRepo->findByCode($prodiCode);
            if ($restrictToProgramId !== null && $prodiProgram?->id !== $restrictToProgramId) {
                continue;
            }

            $prodiQuestionsGrouped[$prodiCode] = [
                'name'      => $prodiAlumni->first()->program_name ?? $prodiCode,
                'questions' => $prodiQuestionsByProgram[$prodiCode] ?? [],
                'alumni'    => $prodiAlumni,
            ];
        }

        return new TracerStudyMultiSheetExport($ministrySheet, $prodiQuestionsGrouped);
    }

    // ═══════════════════════════════════════════════════════════
    // Private helpers
    // ═══════════════════════════════════════════════════════════

    private function buildHeaderList(array $codes, array $labels): array
    {
        return array_map(function ($code) use ($labels) {
            $text = $labels[$code] ?? $code;
            if (mb_strlen($text) > 80) {
                $text = mb_substr($text, 0, 77) . '...';
            }
            return ['code' => $code, 'label' => "{$text} ({$code})"];
        }, $codes);
    }

    /**
     * SATU query untuk semua questionnaire_id prodi sekaligus (lihat
     * catatan perbaikan N+1 di docblock kelas).
     */
    private function buildProdiQuestionsByProgram(?int $restrictToProgramId = null): array
    {
        $prodiQnrs  = $this->questionnaireRepo->listActiveProdiQuestionnaires();
        $programMap = $this->programRepo->allIndexedById();

        if ($restrictToProgramId !== null) {
            $prodiQnrs = $prodiQnrs->only([$restrictToProgramId]);
        }

        if ($prodiQnrs->isEmpty()) {
            return [];
        }

        $questionnaireIds = $prodiQnrs->pluck('id')->toArray();
        $allQuestions = $this->questionnaireRepo->getQuestions($questionnaireIds);
        $questionsByQnrId = $allQuestions->groupBy('questionnaire_id');

        $result = [];
        foreach ($prodiQnrs as $programId => $qnr) {
            $program = $programMap[$programId] ?? null;
            if (!$program) {
                continue;
            }

            $questionsForQnr = $questionsByQnrId->get($qnr->id, collect());
            $codes  = $questionsForQnr->pluck('code')->toArray();
            $labels = $questionsForQnr->pluck('question_text', 'code')->toArray();

            $result[$program->code] = $this->buildHeaderList($codes, $labels);
        }

        return $result;
    }
}