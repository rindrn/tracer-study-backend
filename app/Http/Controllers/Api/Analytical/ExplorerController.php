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
            'cube'      => 'nullable|string|max:100',
        ]);

        try {
            $data = $this->service->dimensionValues(
                $request->query('dimension'),
                $this->scopedParams($request),
                $request->query('cube'),
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
            // Boleh kosong bila ada rumus; syarat "minimal satu" dicek di service.
            'measures'           => 'present|array',
            'measures.*'         => 'string|max:100',
            'dimensions'         => 'present|array',
            'dimensions.*'       => 'string|max:100',
            ...self::filterRules(),
            'formulas'           => 'sometimes|array|max:9',
            'formulas.*.key'     => 'required|string|max:20',
            'formulas.*.label'   => 'required|string|max:150',
            'formulas.*.left'    => 'required|string|max:100',
            'formulas.*.op'      => 'required|string|in:div,sub,add,mul',
            'formulas.*.right'   => 'required|string|max:100',
            'formulas.*.format'  => 'required|string|max:20',
            'min_n'              => 'nullable|integer|min:1|max:100000',
            'sort'               => 'nullable|array',
            'sort.by'            => 'required_with:sort|string|max:100',
            'sort.direction'     => 'required_with:sort|in:asc,desc',
            'sort.limit'         => 'nullable|integer|min:1|max:500',
            'column_dimension'   => 'nullable|string|max:100',
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
            ...self::filterRules(),
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

    /** Saringan: "sama dengan"/"bukan" berisi nilai, "ada nilainya"/"kosong" tanpa nilai. */
    private static function filterRules(): array
    {
        return [
            'filters'            => 'present|array',
            'filters.*.member'   => 'required|string|max:100',
            'filters.*.operator' => 'sometimes|string|in:equals,notEquals,set,notSet',
            'filters.*.values'   => 'present|array',
            'filters.*.values.*' => 'nullable|string|max:255',
        ];
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
