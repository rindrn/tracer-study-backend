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

    // GET /api/thresholds
    public function index(Request $request): JsonResponse
    {
        $result = $this->service->list((int) $request->query('per_page', 15));
        return response()->json(['success' => true, 'message' => 'OK', ...$result]);
    }

    // GET /api/thresholds/{id}
    public function show(int $id): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->service->show($id)->toArray(),
        ]);
    }

    // POST /api/thresholds
    public function store(Request $request): JsonResponse
    {
        $validated = $this->validator->validateCreate($request->all());
        $result    = $this->service->create($validated);  // ← langsung array

        return response()->json([
            'success' => true,
            'message' => 'Threshold berhasil dibuat.',
            'data'    => $result->toArray(),
        ], 201);
    }

    // PUT /api/thresholds/{id}
    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $this->validator->validateUpdate($request->all());
        $result    = $this->service->update($id, $validated);  // ← langsung array

        return response()->json([
            'success' => true,
            'message' => 'Threshold berhasil diperbarui.',
            'data'    => $result->toArray(),
        ]);
    }

    // DELETE /api/thresholds/{id}
    public function destroy(int $id): JsonResponse
    {
        $this->service->delete($id);

        return response()->json([
            'success' => true,
            'message' => 'Threshold berhasil dihapus.',
        ]);
    }
}