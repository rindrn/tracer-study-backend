<?php

namespace App\Http\Controllers\Api\Analytical;

use App\Http\Controllers\Controller;
use App\Services\Analytical\EducationSummaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class EducationSummaryController extends Controller
{
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
        $params = $request->validate([
            'jenjang'         => 'nullable|string|in:D3,D4',
            'jurusan'         => 'nullable|string|max:100',
            'nama_prodi'      => 'nullable|string|max:100',
            'tahun_lulus'     => 'nullable|string|max:5',
            'minggu_snapshot' => 'nullable|string|max:10',
        ]);

        try {
            $dto = $this->service->getSummary($params);
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