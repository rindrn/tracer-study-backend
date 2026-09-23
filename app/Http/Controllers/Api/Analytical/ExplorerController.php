<?php

namespace App\Http\Controllers\Api\Analytical;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Analytical\Concerns\EnforcesProdiScope;
use App\Services\Analytical\ExplorerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * ExplorerController
 *
 * Halaman Insight — OLAP Explorer milik SmartTracer sendiri (bukan Metabase).
 *
 * Routes (semua di dalam auth:sanctum group):
 *
 *   GET  /api/dashboard/explorer/catalog
 *        → daftar sumber data, ukuran, dan dimensi yang boleh dipilih
 *
 *   GET  /api/dashboard/explorer/dimension-values?dimension=DimProdi.jurusan
 *        → nilai unik satu dimensi untuk dropdown filter (sudah dibatasi scope role)
 *
 *   POST /api/dashboard/explorer/query
 *        body: { cube, measures[], dimensions[], filters: [{ member, values[] }] }
 *        → baris datar ber-key nama member Cube.js; pivot dikerjakan FE
 *
 *   POST /api/dashboard/explorer/drill-down
 *        body: { cube, measure, filters, search?, page?, per_page? }
 *        → daftar alumni di balik satu angka (berhalaman)
 *
 * Scope role (kaprodi/kajur/dekan) diambil dari token lewat EnforcesProdiScope,
 * tidak pernah dari body — pengguna tidak bisa melebarkan cakupannya sendiri.
 */
class ExplorerController extends Controller
{
    use EnforcesProdiScope;

    public function __construct(
        private readonly ExplorerService $service,
    ) {}

    public function catalog(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->service->catalog()]);
    }

    public function dimensionValues(Request $request): JsonResponse
    {
        $request->validate([
            'dimension' => 'required|string|max:100',
        ]);

        try {
            $data = $this->service->dimensionValues(
                $request->query('dimension'),
                $this->scopedParams($request),
            );
            return response()->json(['success' => true, 'data' => $data]);
        } catch (\RuntimeException $e) {
            return $this->serviceError($e);
        }
    }

    public function query(Request $request): JsonResponse
    {
        $body = $request->validate([
            'cube'               => 'required|string|max:100',
            'measures'           => 'required|array|min:1',
            'measures.*'         => 'string|max:100',
            'dimensions'         => 'present|array',
            'dimensions.*'       => 'string|max:100',
            'filters'            => 'present|array',
            'filters.*.member'   => 'required|string|max:100',
            'filters.*.values'   => 'required|array',
            'filters.*.values.*' => 'nullable|string|max:255',
        ]);

        try {
            $data = $this->service->query($body, $this->scopedParams($request));
            return response()->json(['success' => true, 'data' => $data]);
        } catch (\RuntimeException $e) {
            return $this->serviceError($e);
        }
    }

    /**
     * POST /api/dashboard/explorer/drill-down
     * body: { cube, measure, filters: [{ member, values[] }], search?, page?, per_page? }
     */
    public function drillDown(Request $request): JsonResponse
    {
        $body = $request->validate([
            'cube'               => 'required|string|max:100',
            'measure'            => 'required|string|max:100',
            'filters'            => 'present|array',
            'filters.*.member'   => 'required|string|max:100',
            'filters.*.values'   => 'required|array',
            'filters.*.values.*' => 'nullable|string|max:255',
            'search'             => 'nullable|string|max:100',
            'page'               => 'sometimes|integer|min:1',
            'per_page'           => 'sometimes|integer|min:5|max:100',
        ]);

        try {
            $data = $this->service->drillDown($body, $this->scopedParams($request));
            return response()->json(['success' => true, 'data' => $data]);
        } catch (\RuntimeException $e) {
            return $this->serviceError($e);
        }
    }

    // ──────────────────────────────────────────────────────────────
    //  PRIVATE
    // ──────────────────────────────────────────────────────────────

    private function serviceError(\RuntimeException $e): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
        ], Response::HTTP_SERVICE_UNAVAILABLE);
    }
}
