<?php

namespace App\Http\Controllers\Api\Transactional;

use App\Http\Controllers\Controller;
use App\Http\Validators\QuestionnaireValidator;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class QuestionnaireController extends Controller
{
    public function __construct(
        private readonly QuestionnaireValidator $validator,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user(); // null for public access
        $query = DB::connection('oltp')->table('questionnaires');

        // Role-based filtering: prodi sees only global + own prodi
        if ($user && method_exists($user, 'isKaprodi') && $user->isKaprodi()) {
            $query->where(function ($q) use ($user) {
                $q->whereNull('program_id')
                  ->orWhere('program_id', $user->program_id);
            });
        }

        $rows = $query->orderByDesc('id')->get();

        // Build response counts — scoped by prodi if needed
        if ($user && method_exists($user, 'isKaprodi') && $user->isKaprodi()) {
            // For prodi: count only responses from their program's alumni
            $responseCounts = DB::connection('oltp')->table('responses')
                ->join('alumni_profiles', 'responses.alumni_id', '=', 'alumni_profiles.id')
                ->where('alumni_profiles.program_id', $user->program_id)
                ->selectRaw('responses.questionnaire_id, COUNT(*) as count')
                ->groupBy('responses.questionnaire_id')
                ->pluck('count', 'responses.questionnaire_id');
        } else {
            // Admin / public: count all
            $responseCounts = DB::connection('oltp')->table('responses')
                ->selectRaw('questionnaire_id, COUNT(*) as count')
                ->groupBy('questionnaire_id')
                ->pluck('count', 'questionnaire_id');
        }

        $data = $rows->map(function ($row) use ($responseCounts) {
            $questionnaire = $this->loadQuestionnaire((int) $row->id);
            if ($questionnaire) {
                $questionnaire['response_count'] = (int) ($responseCounts[$row->id] ?? 0);
            }
            return $questionnaire;
        })->values();

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $data,
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $questionnaire = $this->loadQuestionnaire($id);

        if (! $questionnaire) {
            return response()->json([
                'success' => false,
                'message' => 'Kuisioner tidak ditemukan.',
            ], 404);
        }

        $questionnaire['response_count'] = DB::connection('oltp')
            ->table('responses')
            ->where('questionnaire_id', $id)
            ->count();

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $questionnaire,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validator->validateCreate($request->all());
        $now = Carbon::now();

        $questionnaireId = DB::connection('oltp')->transaction(function () use ($validated, $now) {
            $programId = $this->resolveProgramId($validated);
            $baseCode = $validated['code'] ?? Str::slug($validated['title']) . '-' . ($validated['period_year'] ?? (int) $now->format('Y'));
            $version = $validated['version'] ?? $this->nextVersionForCode($baseCode);

            $questionnaireId = DB::connection('oltp')->table('questionnaires')->insertGetId([
                'code' => $baseCode,
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'target' => $validated['target'] ?? null,
                'sample_respondents' => isset($validated['respondents']) ? json_encode(array_values($validated['respondents'])) : null,
                'period_year' => (int) ($validated['period_year'] ?? (int) $now->format('Y')),
                'version' => $version,
                'status' => $validated['status'],
                'program_id' => $programId,
                'published_at' => $validated['status'] === 'published' ? $now : null,
                'created_by' => auth()->id(),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $this->syncSections($questionnaireId, $validated['sections'], $now);

            return $questionnaireId;
        });

        return response()->json([
            'success' => true,
            'message' => 'Kuisioner berhasil disimpan.',
            'data' => $this->loadQuestionnaire((int) $questionnaireId),
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $this->validator->validateUpdate($request->all());
        $now = Carbon::now();

        $updated = DB::connection('oltp')->transaction(function () use ($id, $validated, $now) {
            $existing = DB::connection('oltp')->table('questionnaires')->where('id', $id)->first();

            if (! $existing) {
                return null;
            }

            $programId = $this->resolveProgramId($validated, $existing->program_id);
            $code = $validated['code'] ?? $existing->code;
            $version = $validated['version'] ?? $existing->version;

            DB::connection('oltp')->table('questionnaires')->where('id', $id)->update([
                'code' => $code,
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'target' => $validated['target'] ?? null,
                'sample_respondents' => isset($validated['respondents']) ? json_encode(array_values($validated['respondents'])) : null,
                'period_year' => (int) ($validated['period_year'] ?? $existing->period_year),
                'version' => $version,
                'status' => $validated['status'],
                'program_id' => $programId,
                'published_at' => $validated['status'] === 'published' ? ($existing->published_at ?? $now) : $existing->published_at,
                'updated_at' => $now,
            ]);

            DB::connection('oltp')->table('questionnaire_questions')->where('questionnaire_id', $id)->delete();
            DB::connection('oltp')->table('questionnaire_sections')->where('questionnaire_id', $id)->delete();

            $this->syncSections($id, $validated['sections'], $now);

            return $id;
        });

        if (! $updated) {
            return response()->json([
                'success' => false,
                'message' => 'Kuisioner tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Kuisioner berhasil diperbarui.',
            'data' => $this->loadQuestionnaire((int) $id),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        // Check if questionnaire has responses — block deletion if so
        $responseCount = DB::connection('oltp')
            ->table('responses')
            ->where('questionnaire_id', $id)
            ->count();

        if ($responseCount > 0) {
            return response()->json([
                'success' => false,
                'message' => "Kuisioner tidak dapat dihapus karena sudah memiliki {$responseCount} responden.",
            ], 422);
        }

        $deleted = DB::connection('oltp')->table('questionnaires')->where('id', $id)->delete();

        if (! $deleted) {
            return response()->json([
                'success' => false,
                'message' => 'Kuisioner tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Kuisioner berhasil dihapus.',
        ]);
    }

    private function resolveProgramId(array $validated, ?int $fallback = null): ?int
    {
        if (! empty($validated['program_id'])) {
            return (int) $validated['program_id'];
        }

        if (! empty($validated['program_code'])) {
            $program = DB::connection('oltp')->table('programs')->where('code', $validated['program_code'])->first();
            return $program?->id;
        }

        return $fallback;
    }

    private function nextVersionForCode(string $code): int
    {
        $latest = DB::connection('oltp')->table('questionnaires')
            ->where('code', $code)
            ->max('version');

        return $latest ? ((int) $latest + 1) : 1;
    }

    private function syncSections(int $questionnaireId, array $sections, Carbon $now): void
    {
        foreach (array_values($sections) as $sectionIndex => $sectionData) {
            $sectionId = DB::connection('oltp')->table('questionnaire_sections')->insertGetId([
                'questionnaire_id' => $questionnaireId,
                'title' => $sectionData['title'],
                'description' => $sectionData['description'] ?? null,
                'order_no' => (int) ($sectionData['order_no'] ?? ($sectionIndex + 1)),
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach (array_values($sectionData['questions']) as $questionIndex => $questionData) {
                $questionId = DB::connection('oltp')->table('questionnaire_questions')->insertGetId([
                    'questionnaire_id' => $questionnaireId,
                    'section_id' => $sectionId,
                    'code' => $questionData['code'] ?: Str::slug($questionData['question']) . '-' . ($questionIndex + 1),
                    'question_text' => $questionData['question'],
                    'question_type' => $this->mapQuestionTypeToDatabase($questionData['type']),
                    'is_required' => (bool) ($questionData['required'] ?? false),
                    'order_no' => (int) ($questionData['order_no'] ?? ($questionIndex + 1)),
                    'metadata' => json_encode($this->buildQuestionMetadata($questionData)),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                foreach (array_values($questionData['options'] ?? []) as $optionIndex => $optionData) {
                    $label = is_array($optionData) ? ($optionData['label'] ?? '') : (string) $optionData;
                    $value = is_array($optionData) ? ($optionData['value'] ?? null) : null;
                    $code = is_array($optionData) && ! empty($optionData['code'])
                        ? $optionData['code']
                        : 'opt_' . ($optionIndex + 1);

                    DB::connection('oltp')->table('questionnaire_options')->insert([
                        'question_id' => $questionId,
                        'option_code' => $code,
                        'option_label' => $label,
                        'option_value' => $value,
                        'order_no' => (int) ($optionData['order_no'] ?? ($optionIndex + 1)),
                        'is_active' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }
    }

    private function mapQuestionTypeToDatabase(string $frontendType): string
    {
        return match ($frontendType) {
            'short' => 'short_text',
            'paragraph' => 'long_text',
            'multiple_choice' => 'single_choice',
            'checkbox' => 'multiple_choice',
            'dropdown' => 'single_choice',
            'linear_scale' => 'number',
            'rating' => 'number',
            'boolean' => 'single_choice',
            'date' => 'date',
            'time' => 'short_text',
            default => 'short_text',
        };
    }

    private function buildQuestionMetadata(array $questionData): array
    {
        $metadata = [
            'original_type' => $questionData['type'],
            'allowOther' => $questionData['allowOther'] ?? false,
        ];

        if (isset($questionData['scaleMin'])) {
            $metadata['scaleMin'] = $questionData['scaleMin'];
        }

        if (isset($questionData['scaleMax'])) {
            $metadata['scaleMax'] = $questionData['scaleMax'];
        }

        if (! empty($questionData['gridRows'])) {
            $metadata['gridRows'] = array_values($questionData['gridRows']);
        }

        if (! empty($questionData['gridColumns'])) {
            $metadata['gridColumns'] = array_values($questionData['gridColumns']);
        }

        return $metadata;
    }

    private function loadQuestionnaire(int $id): ?array
    {
        $questionnaire = DB::connection('oltp')->table('questionnaires')->where('id', $id)->first();

        if (! $questionnaire) {
            return null;
        }

        $sections = DB::connection('oltp')->table('questionnaire_sections')
            ->where('questionnaire_id', $id)
            ->orderBy('order_no')
            ->get();

        $questions = DB::connection('oltp')->table('questionnaire_questions')
            ->where('questionnaire_id', $id)
            ->orderBy('order_no')
            ->get();

        $options = DB::connection('oltp')->table('questionnaire_options')
            ->whereIn('question_id', $questions->pluck('id')->all())
            ->orderBy('order_no')
            ->get()
            ->groupBy('question_id');

        $questionsBySection = $questions->groupBy(fn ($question) => $question->section_id ?? 0);

        $mappedSections = $sections->map(function ($section) use ($questionsBySection, $options) {
            $sectionQuestions = ($questionsBySection[$section->id] ?? collect())->map(function ($question) use ($options) {
                $metadata = $question->metadata ? json_decode($question->metadata, true) : [];

                return [
                    'id' => $question->id,
                    'code' => $question->code,
                    'question' => $question->question_text,
                    'question_text' => $question->question_text,
                    'type' => $metadata['original_type'] ?? $this->mapQuestionTypeToFrontend($question->question_type),
                    'description' => null,
                    'options' => ($options->get($question->id, collect()))->map(fn ($option) => [
                        'id' => $option->id,
                        'code' => $option->option_code,
                        'label' => $option->option_label,
                        'value' => $option->option_value,
                        'order_no' => $option->order_no,
                    ])->values()->toArray(),
                    'required' => (bool) $question->is_required,
                    'allowOther' => $metadata['allowOther'] ?? false,
                    'scaleMin' => $metadata['scaleMin'] ?? 1,
                    'scaleMax' => $metadata['scaleMax'] ?? 5,
                    'gridRows' => $metadata['gridRows'] ?? [],
                    'gridColumns' => $metadata['gridColumns'] ?? [],
                ];
            })->values()->toArray();

            return [
                'id' => $section->id,
                'title' => $section->title,
                'description' => $section->description,
                'questions' => $sectionQuestions,
            ];
        })->values()->toArray();

        if (empty($mappedSections) && $questions->isNotEmpty()) {
            $mappedSections[] = [
                'id' => 0,
                'title' => 'Bagian 1',
                'description' => null,
                'questions' => $questions->map(function ($question) use ($options) {
                    $metadata = $question->metadata ? json_decode($question->metadata, true) : [];

                    return [
                        'id' => $question->id,
                        'code' => $question->code,
                        'question' => $question->question_text,
                        'question_text' => $question->question_text,
                        'type' => $metadata['original_type'] ?? $this->mapQuestionTypeToFrontend($question->question_type),
                        'description' => null,
                        'options' => ($options->get($question->id, collect()))->map(fn ($option) => [
                            'id' => $option->id,
                            'code' => $option->option_code,
                            'label' => $option->option_label,
                            'value' => $option->option_value,
                            'order_no' => $option->order_no,
                        ])->values()->toArray(),
                        'required' => (bool) $question->is_required,
                        'allowOther' => $metadata['allowOther'] ?? false,
                        'scaleMin' => $metadata['scaleMin'] ?? 1,
                        'scaleMax' => $metadata['scaleMax'] ?? 5,
                        'gridRows' => $metadata['gridRows'] ?? [],
                        'gridColumns' => $metadata['gridColumns'] ?? [],
                    ];
                })->values()->toArray(),
            ];
        }

        return [
            'id' => $questionnaire->id,
            'code' => $questionnaire->code,
            'title' => $questionnaire->title,
            'description' => $questionnaire->description,
            'target' => $questionnaire->target,
            'respondents' => $questionnaire->sample_respondents ? json_decode($questionnaire->sample_respondents, true) : [],
            'period_year' => (int) $questionnaire->period_year,
            'version' => (int) $questionnaire->version,
            'status' => $questionnaire->status,
            'program_id' => $questionnaire->program_id,
            'is_global' => is_null($questionnaire->program_id),
            'sections' => $mappedSections,
        ];
    }

    private function mapQuestionTypeToFrontend(string $dbType): string
    {
        return match ($dbType) {
            'short_text' => 'short',
            'long_text' => 'paragraph',
            'single_choice' => 'multiple_choice',
            'multiple_choice' => 'checkbox',
            'number' => 'short',
            'date' => 'date',
            'boolean' => 'multiple_choice',
            default => 'short',
        };
    }
}