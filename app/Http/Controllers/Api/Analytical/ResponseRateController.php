<?php

namespace App\Http\Controllers\Api\Analytical;

use App\Http\Controllers\Controller;
use App\Services\Analytical\ResponseRateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ResponseRateController extends Controller
{
    public function __construct(
        private readonly ResponseRateService $service,
    ) {}

    // ──────────────────────────────────────────────────────────────

    /**
     * GET /api/dashboard/response-rate/bar
     *
     * Stacked bar horizontal per prodi:
     *   - responded     (% Sudah Merespons = on_going + selesai)
     *   - notResponded  (% Belum Merespons)
     *
     * Query params (semua opsional):
     *   jenjang          string   D3 | D4 (kolom asli: programs.degree)
     *   nama_prodi       string   Nama program studi (exact match, programs.name)
     *   graduation_year  string   Filter tahun lulus alumni (kolom asli: alumni_profiles.graduation_year)
     *   sort             string   valueDesc (default) | valueAsc | name
     */
    public function bar(Request $request): JsonResponse
    {
        $params = $request->validate([
            'jenjang'         => 'nullable|string|in:D3,D4',
            'nama_prodi'      => 'nullable|string|max:100',
            'graduation_year' => 'nullable|string|max:5',
            'sort'            => 'nullable|string|in:valueDesc,valueAsc,name',
        ]);

        try {
            $dto = $this->service->getBar($params);
            return response()->json(['success' => true, 'data' => $dto->toArray()]);
        } catch (\RuntimeException $e) {
            return $this->serviceError($e);
        }
    }

    // ──────────────────────────────────────────────────────────────

    /**
     * GET /api/dashboard/response-rate/pie
     *
     * Pie 3 status pengisian survei (aggregate keseluruhan, BUKAN per prodi):
     *   Selesai, Sedang Mengisi, Belum Mengisi.
     *
     * Query params (semua opsional):
     *   jenjang, nama_prodi, graduation_year — sama seperti /bar
     */
    public function pie(Request $request): JsonResponse
    {
        $params = $request->validate([
            'jenjang'         => 'nullable|string|in:D3,D4',
            'nama_prodi'      => 'nullable|string|max:100',
            'graduation_year' => 'nullable|string|max:5',
        ]);

        try {
            $dto = $this->service->getPie($params);
            return response()->json(['success' => true, 'data' => $dto->toArray()]);
        } catch (\RuntimeException $e) {
            return $this->serviceError($e);
        }
    }

    public function trend(Request $request): JsonResponse
    {
        $params = $request->validate([
            'jenjang'         => 'nullable|string|in:D3,D4',
            'nama_prodi'      => 'nullable|string|max:100',
            'graduation_year' => 'nullable|string|max:5',
        ]);

        try {
            $dto = $this->service->getTrend($params);
            return response()->json(['success' => true, 'data' => $dto->toArray()]);
        } catch (\RuntimeException $e) {
            return $this->serviceError($e);
        }
    }

    public function drillDown(Request $request): JsonResponse
    {
        $params = $request->validate([
            'status'          => 'required|string|in:belum_mengisi,on_going,selesai',
            'jenjang'         => 'nullable|string|in:D3,D4',
            'nama_prodi'      => 'nullable|string|max:100',
            'graduation_year' => 'nullable|string|max:5',
            'search'          => 'nullable|string|max:100',
            'page'            => 'nullable|integer|min:1',
            'per_page'        => 'nullable|integer|min:5|max:100',
        ]);

        try {
            $dto = $this->service->getDrillDown($params);
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