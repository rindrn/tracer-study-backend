<?php

namespace App\Http\Controllers\Api\Transactional;

use App\Http\Controllers\Controller;
use App\Http\Validators\QuestionnaireValidator;
use App\Models\Transactional\ApprovalRequest;
use App\Services\Transactional\QuestionnaireService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuestionnaireController extends Controller
{
    public function __construct(
        private readonly QuestionnaireService   $service,
        private readonly QuestionnaireValidator $validator,
    ) {}

    /** GET /api/questionnaires — semua role, kaprodi di-scope ke prodinya di service. */
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data'    => $this->service->list($request->user()),
        ]);
    }

    /** GET /api/questionnaires/{id} — public untuk FE fetch struktur form. */
    public function show(int $id): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data'    => $this->service->show($id),
        ]);
    }

    /** POST /api/questionnaires */
    public function store(Request $request): JsonResponse
    {
        $validated = $this->validator->validateCreate($request->all());

        // Tracer team hanya bisa buat draft (perlu approval head_tracer untuk publish)
        if ($request->user()->isTracerTeam()) {
            $validated['status'] = 'draft';
        }

        $data = $this->service->create($validated);

        // Buat approval request jika tracer_team
        if ($request->user()->isTracerTeam()) {
            ApprovalRequest::create([
                'requester_id' => $request->user()->id,
                'type'         => ApprovalRequest::TYPE_ADD_QUESTIONNAIRE,
                'payload'      => ['questionnaire_id' => $data['id'], 'title' => $data['title']],
                'status'       => ApprovalRequest::STATUS_PENDING,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => $request->user()->isTracerTeam()
                ? 'Kuisioner berhasil diajukan sebagai draft. Menunggu approval Super Admin.'
                : 'Kuisioner berhasil disimpan.',
            'data'    => $data,
        ], 201);
    }

    /** PUT /api/questionnaires/{id} */
    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $this->validator->validateUpdate($request->all());
        $data = $this->service->update($id, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Kuisioner berhasil diperbarui.',
            'data'    => $data,
        ]);
    }

    /** DELETE /api/questionnaires/{id} */
    public function destroy(int $id): JsonResponse
    {
        $this->service->delete($id);

        return response()->json([
            'success' => true,
            'message' => 'Kuisioner berhasil dihapus.',
        ]);
    }
}
