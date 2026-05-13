<?php
// app/Services/Transactional/QuestionnaireService.php
namespace App\Services\Transactional;

use App\Exceptions\BusinessException;
use App\Models\Transactional\User;
use App\Repositories\Transactional\ProgramRepository;
use App\Repositories\Transactional\QuestionnaireRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * QuestionnaireService — CRUD form builder + assembling response untuk admin panel.
 *
 * Tanggung jawab:
 *   - Orchestrate create/update (wrap di DB transaction, sync sections)
 *   - Role-based filtering saat list (kaprodi scope)
 *   - Business rule: tidak boleh hapus kuesioner yang sudah ada respondennya
 *   - Mapping tipe pertanyaan FE ↔ DB
 *   - Assembling struktur nested untuk response
 */
class QuestionnaireService
{
    private const CONN = 'oltp';

    public function __construct(
        private readonly QuestionnaireRepository $questionnaireRepo,
        private readonly ProgramRepository       $programRepo,
    ) {}

    // ═══════════════════════════════════════════════════════════
    // LIST — semua kuesioner (role-scoped)
    // ═══════════════════════════════════════════════════════════
    public function list(?User $user): array
    {
        $programId = ($user && $user->isKaprodi()) ? $user->program_id : null;

        $rows = $this->questionnaireRepo->listHeaders($programId);
        $responseCounts = $this->questionnaireRepo->countResponsesGroupedAll($programId);

        return $rows->map(function ($row) use ($responseCounts) {
            $questionnaire = $this->loadQuestionnaire((int) $row->id);
            if ($questionnaire) {
                $questionnaire['response_count'] = (int) ($responseCounts[$row->id] ?? 0);
            }
            return $questionnaire;
        })->values()->toArray();
    }

    // ═══════════════════════════════════════════════════════════
    // SHOW — detail 1 kuesioner
    // ═══════════════════════════════════════════════════════════
    public function show(int $id): array
    {
        $questionnaire = $this->loadQuestionnaire($id);
        if (!$questionnaire) {
            throw new BusinessException('Kuisioner tidak ditemukan.', 404);
        }
        $questionnaire['response_count'] = $this->questionnaireRepo->countResponses($id);
        return $questionnaire;
    }

    // ═══════════════════════════════════════════════════════════
    // CREATE — wrap transaction di service
    // ═══════════════════════════════════════════════════════════
    public function create(array $validated): array
    {
        $now = Carbon::now();

        $id = DB::connection(self::CONN)->transaction(function () use ($validated, $now) {
            $programId = $this->resolveProgramId($validated);
            $baseCode  = $validated['code']
                ?? Str::slug($validated['title']) . '-' . ($validated['period_year'] ?? (int) $now->format('Y'));
            $version   = $validated['version'] ?? $this->questionnaireRepo->nextVersionForCode($baseCode);

            $id = $this->questionnaireRepo->insertHeader([
                'code'               => $baseCode,
                'title'              => $validated['title'],
                'description'        => $validated['description'] ?? null,
                'target'             => $validated['target'] ?? null,
                'sample_respondents' => isset($validated['respondents'])
                    ? json_encode(array_values($validated['respondents']))
                    : null,
                'period_year'        => (int) ($validated['period_year'] ?? (int) $now->format('Y')),
                'version'            => $version,
                'status'             => $validated['status'],
                'program_id'         => $programId,
                'published_at'       => $validated['status'] === 'published' ? $now : null,
                'created_by'         => auth()->id(),
            ]);

            $this->syncSections($id, $validated['sections'], $now);

            return $id;
        });

        return $this->loadQuestionnaire($id);
    }

    // ═══════════════════════════════════════════════════════════
    // UPDATE — wrap transaction
    // ═══════════════════════════════════════════════════════════
    public function update(int $id, array $validated): array
    {
        $now = Carbon::now();

        DB::connection(self::CONN)->transaction(function () use ($id, $validated, $now) {
            $existing = $this->questionnaireRepo->findHeaderById($id);
            if (!$existing) {
                throw new BusinessException('Kuisioner tidak ditemukan.', 404);
            }

            $programId = $this->resolveProgramId($validated, $existing->program_id);
            $code      = $validated['code']    ?? $existing->code;
            $version   = $validated['version'] ?? $existing->version;

            $this->questionnaireRepo->updateHeader($id, [
                'code'               => $code,
                'title'              => $validated['title'],
                'description'        => $validated['description'] ?? null,
                'target'             => $validated['target'] ?? null,
                'sample_respondents' => isset($validated['respondents'])
                    ? json_encode(array_values($validated['respondents']))
                    : null,
                'period_year'        => (int) ($validated['period_year'] ?? $existing->period_year),
                'version'            => $version,
                'status'             => $validated['status'],
                'program_id'         => $programId,
                'published_at'       => $validated['status'] === 'published'
                    ? ($existing->published_at ?? $now)
                    : $existing->published_at,
            ]);

            $this->questionnaireRepo->deleteSectionsAndQuestions($id);
            $this->syncSections($id, $validated['sections'], $now);
        });

        return $this->loadQuestionnaire($id);
    }

    // ═══════════════════════════════════════════════════════════
    // DELETE — blocked if has responses
    // ═══════════════════════════════════════════════════════════
    public function delete(int $id): void
    {
        $responseCount = $this->questionnaireRepo->countResponses($id);
        if ($responseCount > 0) {
            throw new BusinessException(
                "Kuisioner tidak dapat dihapus karena sudah memiliki {$responseCount} responden.",
                422,
            );
        }

        $deleted = $this->questionnaireRepo->deleteHeader($id);
        if (!$deleted) {
            throw new BusinessException('Kuisioner tidak ditemukan.', 404);
        }
    }

    // ═══════════════════════════════════════════════════════════
    // PRIVATE HELPERS (dipindah dari controller lama)
    // ═══════════════════════════════════════════════════════════

    /** Resolve program_id dari input (program_id langsung atau program_code). */
    private function resolveProgramId(array $validated, ?int $fallback = null): ?int
    {
        if (!empty($validated['program_id'])) {
            return (int) $validated['program_id'];
        }
        if (!empty($validated['program_code'])) {
            return $this->programRepo->findByCode($validated['program_code'])?->id;
        }
        return $fallback;
    }

    /** Insert semua section + question + option untuk 1 kuesioner. */
    private function syncSections(int $questionnaireId, array $sections, Carbon $now): void
    {
        foreach (array_values($sections) as $sectionIndex => $sectionData) {
            $sectionId = $this->questionnaireRepo->insertSection([
                'questionnaire_id' => $questionnaireId,
                'title'            => $sectionData['title'],
                'description'      => $sectionData['description'] ?? null,
                'order_no'         => (int) ($sectionData['order_no'] ?? ($sectionIndex + 1)),
                'is_active'        => true,
            ]);

            foreach (array_values($sectionData['questions']) as $questionIndex => $questionData) {
                $questionId = $this->questionnaireRepo->insertQuestion([
                    'questionnaire_id' => $questionnaireId,
                    'section_id'       => $sectionId,
                    'code'             => $questionData['code'] ?: Str::slug($questionData['question']) . '-' . ($questionIndex + 1),
                    'question_text'    => $questionData['question'],
                    'question_type'    => $this->mapQuestionTypeToDatabase($questionData['type']),
                    'is_required'      => (bool) ($questionData['required'] ?? false),
                    'order_no'         => (int) ($questionData['order_no'] ?? ($questionIndex + 1)),
                    'metadata'         => json_encode($this->buildQuestionMetadata($questionData)),
                ]);

                foreach (array_values($questionData['options'] ?? []) as $optionIndex => $optionData) {
                    $label = is_array($optionData) ? ($optionData['label'] ?? '') : (string) $optionData;
                    $value = is_array($optionData) ? ($optionData['value'] ?? null) : null;
                    $code  = is_array($optionData) && !empty($optionData['code'])
                        ? $optionData['code']
                        : 'opt_' . ($optionIndex + 1);

                    $this->questionnaireRepo->insertOption([
                        'question_id'  => $questionId,
                        'option_code'  => $code,
                        'option_label' => $label,
                        'option_value' => $value,
                        'order_no'     => (int) ($optionData['order_no'] ?? ($optionIndex + 1)),
                        'is_active'    => true,
                    ]);
                }
            }
        }
    }

    private function mapQuestionTypeToDatabase(string $frontendType): string
    {
        return match ($frontendType) {
            'short'           => 'short_text',
            'paragraph'       => 'long_text',
            'multiple_choice' => 'single_choice',
            'checkbox'        => 'multiple_choice',
            'dropdown'        => 'single_choice',
            'linear_scale'    => 'number',
            'rating'          => 'number',
            'boolean'         => 'single_choice',
            'date'            => 'date',
            'time'            => 'short_text',
            default           => 'short_text',
        };
    }

    private function mapQuestionTypeToFrontend(string $dbType): string
    {
        return match ($dbType) {
            'short_text'      => 'short',
            'long_text'       => 'paragraph',
            'single_choice'   => 'multiple_choice',
            'multiple_choice' => 'checkbox',
            'number'          => 'short',
            'date'            => 'date',
            'boolean'         => 'multiple_choice',
            default           => 'short',
        };
    }

    private function buildQuestionMetadata(array $questionData): array
    {
        $metadata = [
            'original_type' => $questionData['type'],
            'allowOther'    => $questionData['allowOther'] ?? false,
        ];

        foreach (['scaleMin', 'scaleMax'] as $key) {
            if (isset($questionData[$key])) {
                $metadata[$key] = $questionData[$key];
            }
        }
        foreach (['gridRows', 'gridColumns'] as $key) {
            if (!empty($questionData[$key])) {
                $metadata[$key] = array_values($questionData[$key]);
            }
        }

        return $metadata;
    }

    /**
     * Load 1 kuesioner lengkap (header + sections + questions + options) sebagai array
     * siap-konsumsi untuk response JSON.
     */
    private function loadQuestionnaire(int $id): ?array
    {
        $questionnaire = $this->questionnaireRepo->findHeaderById($id);
        if (!$questionnaire) {
            return null;
        }

        $sections  = $this->questionnaireRepo->getSectionsByQuestionnaireId($id);
        $questions = $this->questionnaireRepo->getQuestionsByQuestionnaireId($id);
        $options   = $this->questionnaireRepo->getOptionsGrouped(
            $questions->pluck('id')->all()
        );

        $questionsBySection = $questions->groupBy(fn ($q) => $q->section_id ?? 0);

        $mappedSections = $sections->map(function ($section) use ($questionsBySection, $options) {
            $sectionQuestions = ($questionsBySection[$section->id] ?? collect())->map(
                fn ($q) => $this->mapQuestionFull($q, $options)
            )->values()->toArray();

            return [
                'id'          => $section->id,
                'title'       => $section->title,
                'description' => $section->description,
                'questions'   => $sectionQuestions,
            ];
        })->values()->toArray();

        // Fallback: tidak ada sections tapi ada questions → bungkus ke 1 section default
        if (empty($mappedSections) && $questions->isNotEmpty()) {
            $mappedSections[] = [
                'id'          => 0,
                'title'       => 'Bagian 1',
                'description' => null,
                'questions'   => $questions->map(fn ($q) => $this->mapQuestionFull($q, $options))
                    ->values()->toArray(),
            ];
        }

        return [
            'id'           => $questionnaire->id,
            'code'         => $questionnaire->code,
            'title'        => $questionnaire->title,
            'description'  => $questionnaire->description,
            'target'       => $questionnaire->target,
            'respondents'  => $questionnaire->sample_respondents
                ? json_decode($questionnaire->sample_respondents, true)
                : [],
            'period_year'  => (int) $questionnaire->period_year,
            'version'      => (int) $questionnaire->version,
            'status'       => $questionnaire->status,
            'program_id'   => $questionnaire->program_id,
            'is_global'    => is_null($questionnaire->program_id),
            'sections'     => $mappedSections,
        ];
    }

    /** Shape 1 question full (untuk admin panel) — versi "full" dengan scale/grid. */
    private function mapQuestionFull(object $question, \Illuminate\Support\Collection $optionsGrouped): array
    {
        $metadata = $question->metadata ? json_decode($question->metadata, true) : [];

        return [
            'id'            => $question->id,
            'code'          => $question->code,
            'question'      => $question->question_text,
            'question_text' => $question->question_text,
            'type'          => $metadata['original_type'] ?? $this->mapQuestionTypeToFrontend($question->question_type),
            'description'   => null,
            'options'       => ($optionsGrouped->get($question->id, collect()))->map(fn ($o) => [
                'id'       => $o->id,
                'code'     => $o->option_code,
                'label'    => $o->option_label,
                'value'    => $o->option_value,
                'order_no' => $o->order_no,
            ])->values()->toArray(),
            'required'    => (bool) $question->is_required,
            'allowOther'  => $metadata['allowOther'] ?? false,
            'scaleMin'    => $metadata['scaleMin']   ?? 1,
            'scaleMax'    => $metadata['scaleMax']   ?? 5,
            'gridRows'    => $metadata['gridRows']   ?? [],
            'gridColumns' => $metadata['gridColumns'] ?? [],
        ];
    }
}
