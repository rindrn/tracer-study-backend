<?php

namespace App\Http\Controllers\Api\Transactional;

use App\Http\Controllers\Controller;
use App\Http\Validators\QuestionSemanticMappingValidator;
use App\Services\Transactional\QuestionSemanticMappingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuestionSemanticMappingController extends Controller
{
    public function __construct(
        private readonly QuestionSemanticMappingService $service,
        private readonly QuestionSemanticMappingValidator $validator,
    ) {}

    // GET /api/question-semantic-mappings?questionnaire_id=&is_active=
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'questionnaire_id' => 'nullable|integer',
            'is_active'        => 'nullable|boolean',
        ]);

        $questionnaireId = $request->filled('questionnaire_id') ? (int) $request->query('questionnaire_id') : null;
        $isActive = $request->has('is_active') ? $request->boolean('is_active') : null;

        return response()->json([
            'success' => true,
            'data'    => $this->service->listMappings($questionnaireId, $isActive)->values(),
        ]);
    }

    // GET /api/question-semantic-mappings/unmapped?questionnaire_id=
    public function unmapped(Request $request): JsonResponse
    {
        $request->validate(['questionnaire_id' => 'required|integer']);

        return response()->json([
            'success' => true,
            'data'    => $this->service->unmapped((int) $request->query('questionnaire_id')),
        ]);
    }

    // GET /api/question-semantic-mappings/similar?questionnaire_id=&question_text=&exclude_code=
    public function similar(Request $request): JsonResponse
    {
        $validated = $this->validator->validateSimilar($request->all());

        return response()->json([
            'success' => true,
            'data'    => $this->service->similar(
                (int) $validated['questionnaire_id'],
                $validated['question_text'],
                $validated['exclude_code'] ?? null,
            ),
        ]);
    }

    // GET /api/question-semantic-mappings/questionnaires
    public function questionnaires(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->service->listQuestionnaires()->values(),
        ]);
    }

    // GET /api/question-semantic-mappings/option-candidates?semantic_role=
    public function optionCandidates(Request $request): JsonResponse
    {
        $request->validate(['semantic_role' => 'required|string|max:50']);

        return response()->json([
            'success' => true,
            'data'    => $this->service->optionCandidates($request->query('semantic_role')),
        ]);
    }

    // POST /api/question-semantic-mappings
    public function store(Request $request): JsonResponse
    {
        $validated = $this->validator->validateStore($request->all());

        $data = $this->service->store($validated, $request->user()?->id);

        return response()->json([
            'success' => true,
            'message' => 'Mapping berhasil dibuat.',
            'data'    => $data,
        ], 201);
    }

    // POST /api/question-semantic-mappings/{id}/deactivate
    public function deactivate(Request $request, int $id): JsonResponse
    {
        $this->service->deactivate($id, $request->user()?->id);

        return response()->json([
            'success' => true,
            'message' => 'Mapping berhasil dinonaktifkan.',
        ]);
    }
}
