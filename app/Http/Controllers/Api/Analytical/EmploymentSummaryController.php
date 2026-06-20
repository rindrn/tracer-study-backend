<?php

namespace App\Http\Controllers\Api\Analytical;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Analytical\Concerns\EnforcesProdiScope;
use App\Services\Analytical\EmploymentSummaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * EmploymentSummaryController
 *
 * Segmen: Summary Cards di Employment Page (6 card di atas tabs KPI 4-12).
 * Source: Cube.js / OLAP — lewat EmploymentSummaryRepository yang reuse
 * Repository KPI 4/5/6/7/8/12 yang sudah established.
 *
 * Route (di dalam auth:sanctum group):
 *   GET /api/dashboard/employment/summary
 */
class EmploymentSummaryController extends Controller
{
    use EnforcesProdiScope;

    public function __construct(
        private readonly EmploymentSummaryService $service,
    ) {}

    /**
     * GET /api/dashboard/employment/summary
     *
     * Query params (semua opsional):
     *   jenjang          string   D3 | D4
     *   jurusan          string   Filter opsional
     *   nama_prodi       string   Filter opsional
     *   tahun_lulus      string   Filter opsional
     *   minggu_snapshot  string   Filter opsional
     */
    public function summary(Request $request): JsonResponse
    {
        $request->validate([
            'jenjang'         => 'nullable|string|in:D3,D4',
            'jurusan'         => 'nullable|string|max:100',
            'nama_prodi'      => 'nullable|string|max:100',
            'tahun_lulus'     => 'nullable|string|max:5',
            'minggu_snapshot' => 'nullable|string|max:10',
        ]);
        $p = $this->scopedParams($request);
 
        try {
            $dto = $this->service->getSummary($p);
            return response()->json(['success' => true, 'data' => $dto->toArray()]);
        } catch (\RuntimeException $e) {
            return $this->serviceError($e);
        }
    }

    private function serviceError(\RuntimeException $e): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
        ], Response::HTTP_SERVICE_UNAVAILABLE);
    }
}