<?php
// app/Services/Transactional/ReportService.php
namespace App\Services\Transactional;

use App\Exports\TracerStudyMultiSheetExport;
use App\Models\Transactional\User;
use App\Repositories\Transactional\AlumniProfileRepository;
use App\Repositories\Transactional\ProgramRepository;
use App\Repositories\Transactional\QuestionnaireRepository;
use App\Repositories\Transactional\ResponseRepository;

/**
 * ReportService — build data untuk export Excel tracer study.
 *
 * Mengorkestrasi 4 repo untuk assembling struktur multi-sheet:
 *   Sheet 1: Ministry (kolom f*)
 *   Sheet 2..N: Per-prodi (pertanyaan custom prodi)
 */
class ReportService
{
    /** Kode identitas yang tidak ditampilkan sebagai kolom pertanyaan. */
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
     * Bangun export object siap di-download.
     *
     * @param User     $user kepentingan scope (kaprodi hanya prodinya)
     * @param int|null $questionnaireId filter opsional
     */
    public function buildAlumniResponsesExport(User $user, ?int $questionnaireId): TracerStudyMultiSheetExport
    {
        // 1. Ambil alumni (filtered by role + optional questionnaire_id)
        $filters = array_filter([
            'program_id'       => $user->isKaprodi() ? $user->program_id : null,
            'questionnaire_id' => $questionnaireId,
        ], fn ($v) => $v !== null);

        $alumniProfiles = $this->alumniRepo->getForReport($filters);

        // 2. Ambil semua jawaban + group per response_id
        $responseIds = $alumniProfiles->pluck('response_id')->filter()->toArray();
        $answers     = $this->responseRepo->getAnswersByResponseIds($responseIds);

        $answersGrouped = $answers
            ->groupBy('response_id')
            ->map(fn ($items) => $items->pluck('answer_text', 'question_code')->toArray());

        // Suntikkan answers ke profil alumni
        $alumniData = $alumniProfiles->map(function ($item) use ($answersGrouped) {
            $item->answers = $item->response_id ? ($answersGrouped->get($item->response_id) ?? []) : [];
            return $item;
        });

        // 3. Pisahkan kode ministry (f*) vs prodi custom
        [$ministryCodes, $prodiCodes] = $this->splitQuestionCodes(
            $answers->pluck('question_code')->unique()->toArray()
        );

        // 4. Label pertanyaan untuk header
        $questionLabels = $this->questionnaireRepo->getQuestionLabelsByCode(
            array_merge($ministryCodes, $prodiCodes),
        );

        $ministryQuestions = $this->buildHeaderList($ministryCodes, $questionLabels);

        // 5. Build per-prodi question + alumni grouping
        $prodiQuestionsByProgram = $this->buildProdiQuestionsByProgram();
        $alumniByProdi = $alumniData->groupBy('program_code');

        $prodiQuestionsGrouped = [];
        foreach ($alumniByProdi as $prodiCode => $prodiAlumni) {
            if (!$prodiCode) {
                continue;
            }
            $prodiQuestionsGrouped[$prodiCode] = [
                'name'      => $prodiAlumni->first()->program_name ?? $prodiCode,
                'questions' => $prodiQuestionsByProgram[$prodiCode] ?? [],
                'alumni'    => $prodiAlumni,
            ];
        }

        return new TracerStudyMultiSheetExport(
            $alumniData,
            $ministryQuestions,
            $prodiQuestionsGrouped,
        );
    }

    // ═══════════════════════════════════════════════════════════
    // Private helpers
    // ═══════════════════════════════════════════════════════════

    /** Pisahkan kode: yang diawali "f" + alfanumerik = ministry, sisanya = prodi custom. */
    private function splitQuestionCodes(array $allCodes): array
    {
        $ministry = [];
        $prodi    = [];

        foreach ($allCodes as $code) {
            if (in_array($code, self::IDENTITY_CODES, strict: true)) {
                continue;
            }
            if (preg_match('/^f\w+$/i', $code)) {
                $ministry[] = $code;
            } else {
                $prodi[] = $code;
            }
        }

        sort($ministry);
        sort($prodi);

        return [$ministry, $prodi];
    }

    /** Build header list [['code' => ..., 'label' => "text (code)"], ...] dengan truncate teks. */
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
     * Untuk tiap prodi yang punya kuesioner aktif, ambil daftar pertanyaannya
     * → keyed by program_code, dipakai untuk build per-prodi sheet.
     */
    private function buildProdiQuestionsByProgram(): array
    {
        $prodiQnrs  = $this->questionnaireRepo->listActiveProdiQuestionnaires();
        $programMap = $this->programRepo->allIndexedById();

        $result = [];
        foreach ($prodiQnrs as $programId => $qnr) {
            $program = $programMap[$programId] ?? null;
            if (!$program) {
                continue;
            }

            $codes  = $this->questionnaireRepo->getQuestionCodesByQuestionnaireId($qnr->id);
            $labels = $this->questionnaireRepo->getQuestionLabelsByCode($codes);

            $result[$program->code] = $this->buildHeaderList($codes, $labels);
        }

        return $result;
    }
}
