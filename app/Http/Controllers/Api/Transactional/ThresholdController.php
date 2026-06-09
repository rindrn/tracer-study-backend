<?php

namespace App\Http\Controllers\Api\Transactional;

use App\Http\Controllers\Controller;
use App\Http\Validators\ThresholdValidator;
use App\Services\Transactional\ThresholdService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ThresholdController extends Controller
{
    public function __construct(
        private readonly ThresholdService   $service,
        private readonly ThresholdValidator $validator,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $result = $this->service->list((int) $request->query('per_page', 15));
        return response()->json(['success' => true, 'message' => 'OK', ...$result]);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->service->show($id)->toArray(),
        ]);
    }

    // GET /api/lam-versions/{id}/thresholds
    public function byVersion(int $id): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->service->byVersion($id),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validator->validateCreate($request->all());
        return response()->json([
            'success' => true,
            'message' => 'Threshold berhasil dibuat.',
            'data'    => $this->service->create($validated)->toArray(),
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $this->validator->validateUpdate($request->all());
        return response()->json([
            'success' => true,
            'message' => 'Threshold berhasil diperbarui.',
            'data'    => $this->service->update($id, $validated)->toArray(),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->service->delete($id);
        return response()->json([
            'success' => true,
            'message' => 'Threshold berhasil dihapus.',
        ]);
    }

    // POST /api/lam-versions/{id}/thresholds/bulk
    public function bulkStore(Request $request, int $id): JsonResponse
    {
        $validated = $this->validator->validateBulkStore($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Threshold berhasil dibuat.',
            'data'    => $this->service->bulkCreate($id, $validated),
        ], 201);
    }

    // PUT /api/lam-versions/{id}/thresholds/bulk
    public function bulkUpdate(Request $request, int $id): JsonResponse
    {
        $validated = $this->validator->validateBulkUpdate($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Threshold berhasil diperbarui.',
            'data'    => $this->service->bulkUpdate($id, $validated),
        ]);
    }

    // GET /api/dashboard/thresholds?prodi_id=3&indicator=employment_time
    public function forChart(Request $request): JsonResponse
    {
        $request->validate([
            'indicator' => 'required|string',
            'prodi_id'  => 'nullable|integer',
        ]);

        $prodiId       = $request->query('prodi_id') ? (int) $request->query('prodi_id') : null;
        $indicatorKey  = $request->query('indicator');

        return response()->json([
            'success' => true,
            'data'    => $this->service->forChart($prodiId, $indicatorKey),
        ]);
    }

}