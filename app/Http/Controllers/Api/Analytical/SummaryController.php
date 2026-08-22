<?php

namespace App\Http\Controllers\Api\Analytical;

use App\Support\Degree;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Analytical\Concerns\EnforcesProdiScope;
use App\Services\Analytical\SummaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SummaryController extends Controller
{
    use EnforcesProdiScope;

    public function __construct(
        private readonly SummaryService $service,
    ) {}

    // ──────────────────────────────────────────────────────────────

    /**
     * GET /api/dashboard/overview/summary
     *
     * Query params (semua opsional):
     *   jenjang          string   D3 | D4 | S2 | S1
     *   nama_prodi       string   Nama program studi (exact match)
     *   graduation_year  string   Filter tahun lulus alumni
     */
    public function summary(Request $request): JsonResponse
    {
        $request->validate([
            'jenjang'         => Degree::filterRule(),
            'nama_prodi'      => 'nullable|string|max:100',
            'graduation_year' => 'nullable|string|max:5',
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