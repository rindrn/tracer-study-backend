<?php
// app/Http/Controllers/Api/Transactional/ThresholdIndicatorController.php
namespace App\Http\Controllers\Api\Transactional;

use App\Http\Controllers\Controller;
use App\Services\Transactional\ThresholdIndicatorService;
use Illuminate\Http\JsonResponse;

class ThresholdIndicatorController extends Controller
{
    public function __construct(
        private readonly ThresholdIndicatorService $service,
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
}