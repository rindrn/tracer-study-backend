<?php

namespace App\Http\Controllers\Api\Analytical;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Analytical\Concerns\EnforcesProdiScope;
use App\Services\Analytical\ResponseRateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ResponseRateController extends Controller
{
    use EnforcesProdiScope;

    public function __construct(
        private readonly ResponseRateService $service,
    ) {}

    /**
     * GET /api/dashboard/response-rate/bar
     */
    public function bar(Request $request): JsonResponse
    {
        $request->validate([
            'jenjang'         => 'nullable|string|in:D3,D4',
            'nama_prodi'      => 'nullable|string|max:100',
            'graduation_year' => 'nullable|string|max:5',
            'sort'            => 'nullable|string|in:valueDesc,valueAsc,name',
        ]);
        $p = $this->scopedParams($request);

        try {
            $dto = $this->service->getBar($p);
            return response()->json(['success' => true, 'data' => $dto->toArray()]);
        } catch (\RuntimeException $e) {
            return $this->serviceError($e);
        }
    }

    /**
     * GET /api/dashboard/response-rate/pie
     */
    public function pie(Request $request): JsonResponse
    {
        $request->validate([
            'jenjang'         => 'nullable|string|in:D3,D4',
            'nama_prodi'      => 'nullable|string|max:100',
            'graduation_year' => 'nullable|string|max:5',
        ]);
        $p = $this->scopedParams($request);

        try {
            $dto = $this->service->getPie($p);
            return response()->json(['success' => true, 'data' => $dto->toArray()]);
        } catch (\RuntimeException $e) {
            return $this->serviceError($e);
        }
    }

    /**
     * GET /api/dashboard/response-rate/trend
     */
    public function trend(Request $request): JsonResponse
    {
        $request->validate([
            'jenjang'         => 'nullable|string|in:D3,D4',
            'nama_prodi'      => 'nullable|string|max:100',
            'graduation_year' => 'nullable|string|max:5',
        ]);
        $p = $this->scopedParams($request);

        try {
            $dto = $this->service->getTrend($p);
            return response()->json(['success' => true, 'data' => $dto->toArray()]);
        } catch (\RuntimeException $e) {
            return $this->serviceError($e);
        }
    }

    /**
     * GET /api/dashboard/response-rate/drill-down
     *
     * status: nilai langsung dari DB — submitted | ongoing | started
     *   submitted = Selesai
     *   ongoing   = Sedang Mengisi
     *   started   = Belum Mengisi
     */
    public function drillDown(Request $request): JsonResponse
    {
        $request->validate([
            'status'          => 'required|string|in:submitted,ongoing,started',  // ← sesuai nilai DB
            'jenjang'         => 'nullable|string|in:D3,D4',
            'nama_prodi'      => 'nullable|string|max:100',
            'graduation_year' => 'nullable|string|max:5',
            'search'          => 'nullable|string|max:100',
            'page'            => 'nullable|integer|min:1',
            'per_page'        => 'nullable|integer|min:5|max:100',
        ]);
        $p = $this->scopedParams($request);

        try {
            $dto = $this->service->getDrillDown($p);
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