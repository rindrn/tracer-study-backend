<?php
// app/Http/Controllers/Api/Transactional/ThresholdIndicatorController.php
namespace App\Http\Controllers\Api\Transactional;

use App\Http\Controllers\Controller;
use App\Http\Validators\ThresholdIndicatorValidator;
use App\Services\Transactional\ThresholdIndicatorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ThresholdIndicatorController extends Controller
{
    public function __construct(
        private readonly ThresholdIndicatorService   $service,
        private readonly ThresholdIndicatorValidator $validator,
    ) {}

    // GET /api/threshold-indicators
    // Semua role yang login bisa akses (untuk populate dropdown)
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->service->list(),
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $this->validator->validateUpdate($request->all(), $id);
        return response()->json([
            'success' => true,
            'message' => 'Indikator berhasil diperbarui.',
            'data'    => $this->service->update($id, $validated),
        ]);
    }
}