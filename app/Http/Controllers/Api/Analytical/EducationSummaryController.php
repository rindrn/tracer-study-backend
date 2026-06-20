<?php

namespace App\Http\Controllers\Api\Analytical;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Analytical\Concerns\EnforcesProdiScope;
use App\Services\Analytical\EducationSummaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * EducationSummaryController
 *
 * Segmen: Summary Cards di Education Page (6 card di atas tabs KPI 9/10/11).
 * Source: Cube.js / OLAP — FactRangeEvaluasi (KPI 9, 10) + FactTracerStudy (KPI 11).
 *
 * Route (di dalam auth:sanctum group):
 *
 *   GET /api/dashboard/education/summary
 *       → 6 card: Skor Kompetensi, Gap Terbesar, Metode Terbaik,
 *         Avg Persepsi, Mandiri/Keluarga, Beasiswa
 *
 */
class EducationSummaryController extends Controller
{
    use EnforcesProdiScope;

    public function __construct(
        private readonly EducationSummaryService $service,
    ) {}

    /**
     * GET /api/dashboard/education/summary
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