<?php

namespace App\Http\Controllers\Api\Transactional;

use App\Http\Controllers\Controller;
use App\Http\Validators\QuestionnaireValidator;
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
        $data = $this->service->create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Kuisioner berhasil disimpan.',
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
