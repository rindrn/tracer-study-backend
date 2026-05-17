<?php
// app/Services/Transactional/QuestionnaireFetchService.php
namespace App\Services\Transactional;

use App\Exceptions\BusinessException;
use App\Repositories\Transactional\ProgramRepository;
use App\Repositories\Transactional\QuestionnaireRepository;
use Illuminate\Support\Collection;

/**
 * QuestionnaireFetchService — fetch kuesioner aktif (global + prodi) untuk alumni UI.
 *
 * Menangani assembling response dari 4 tabel (questionnaires + sections +
 * questions + options) menjadi struktur nested yang siap dikonsumsi FE.
 */
class QuestionnaireFetchService
{
    public function __construct(
        private readonly ProgramRepository       $programRepo,
        private readonly QuestionnaireRepository $questionnaireRepo,
    ) {}

    /**
     * Ambil daftar kuesioner aktif untuk prodi tertentu (berdasarkan kode prodi).
     *
     * @return array  list of questionnaire objects dengan nested sections/questions
     * @throws BusinessException 400 kalau kode prodi kosong, 404 kalau tidak dikenal
     */
    public function getActiveForms(?string $kodeProdi): array
    {
        if (!$kodeProdi) {
            throw new BusinessException(
                'Parameter kode_prodi (misal: kdpstmsmh) wajib disertakan di URL.',
                400,
            );
        }

        $program = $this->programRepo->findByCode($kodeProdi);
        if (!$program) {
            throw new BusinessException('Kode program studi tidak dikenali.', 404);
        }

        $questionnaires = $this->questionnaireRepo->findActiveForProdi($program->id);

        if ($questionnaires->isEmpty()) {
            return [];
        }

        $questionnaireIds = $questionnaires->pluck('id')->toArray();
        $sectionsByQnr    = $this->questionnaireRepo->getSectionsGrouped($questionnaireIds);
        $questions        = $this->questionnaireRepo->getQuestions($questionnaireIds);
        $optionsByQuestion = $this->questionnaireRepo->getOptionsGrouped(
            $questions->pluck('id')->toArray()
        );
        $questionsBySection = $questions->groupBy('section_id');

        return $questionnaires->map(function ($qnr) use ($sectionsByQnr, $questionsBySection, $optionsByQuestion, $questions) {
            return $this->assembleQuestionnaire(
                $qnr,
                $sectionsByQnr->get($qnr->id, collect()),
                $questionsBySection,
                $optionsByQuestion,
                $questions,
            );
        })->values()->toArray();
    }

    // ═══════════════════════════════════════════════════════════
    // Private: assembling structure (dipindah dari controller lama)
    // ═══════════════════════════════════════════════════════════

    private function assembleQuestionnaire(
        object $qnr,
        Collection $sections,
        Collection $questionsBySection,
        Collection $optionsByQuestion,
        Collection $allQuestions,
    ): object {
        $qnr->is_global = is_null($qnr->program_id);

        if ($sections->isNotEmpty()) {
            $qnr->sections = $sections->map(function ($sec) use ($questionsBySection, $optionsByQuestion) {
                $secQuestions = $questionsBySection->get($sec->id, collect());
                return (object) [
                    'id'          => $sec->id,
                    'title'       => $sec->title,
                    'description' => $sec->description ?? null,
                    'questions'   => $secQuestions->map(
                        fn ($q) => $this->mapQuestion($q, $optionsByQuestion)
                    )->values(),
                ];
            })->values();

            // Flat list untuk backward compat
            $qnr->questions = $sections->flatMap(
                fn ($sec) => $questionsBySection->get($sec->id, collect())
                    ->map(fn ($q) => $this->mapQuestion($q, $optionsByQuestion))
            )->values();
        } else {
            // Fallback: tidak punya sections → flat questions saja
            $qnr->sections  = [];
            $qnr->questions = $allQuestions
                ->where('questionnaire_id', $qnr->id)
                ->map(fn ($q) => $this->mapQuestion($q, $optionsByQuestion))
                ->values();
        }

        return $qnr;
    }

    private function mapQuestion(object $q, Collection $optionsByQuestion): object
    {
        $rawOptions = $optionsByQuestion->get($q->id, collect());
        $metadata   = $q->metadata ? json_decode($q->metadata) : null;

        return (object) [
            'id'               => $q->id,
            'questionnaire_id' => $q->questionnaire_id,
            'question_code'    => $q->code,
            'question_text'    => $q->question_text,
            'question_type'    => $q->question_type,
            'is_required'      => $q->is_required,
            'order_no'         => $q->order_no,
            'metadata'         => $metadata,
            'options'          => $rawOptions->map(fn ($o) => [
                'id'    => $o->id,
                'code'  => $o->option_code,
                'label' => $o->option_label,
                'value' => $o->option_code, // FE pakai option_code sebagai value
            ])->values(),
        ];
    }
}
