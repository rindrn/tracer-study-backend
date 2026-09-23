<?php

namespace App\Http\Controllers\Api\Analytical;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Analytical\Concerns\EnforcesProdiScope;
use App\Http\Requests\Analytical\ExplorerQueryRequest;
use App\Services\Analytical\ExplorerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * ExplorerController
 *
 * Segmen: OLAP Explorer (halaman Insight)
 *
 * Routes (semua di dalam auth:sanctum group):
 *
 *   GET  /api/dashboard/explorer/catalog
 *        → Daftar sumber data, measure, dan dimensi yang boleh disusun
 *
 *   GET  /api/dashboard/explorer/dimension-values?dimension=DimProdi.nama_prodi
 *        → Nilai unik satu dimensi untuk dropdown filter
 *
 *   POST /api/dashboard/explorer/query
 *        → Jalankan query yang disusun pengguna
 *
 * Berbeda dari controller analitik lain, measure dan dimensi di sini datang
 * dari pengguna. Yang menjaganya ada dua lapis dan keduanya di server:
 * OlapCatalog (apa yang boleh diminta) dan EnforcesProdiScope (data siapa yang
 * boleh terlihat). Keduanya tidak bisa dilewati lewat body.
 */
class ExplorerController extends Controller
{
    use EnforcesProdiScope;

    public function __construct(
        private readonly ExplorerService $service,
    ) {}

    /**
     * GET /api/dashboard/explorer/catalog
     *
     * Response 200:
     * {
     *   "success": true,
     *   "data": {
     *     "cubes": [
     *       {
     *         "key": "FactTracerStudy",
     *         "label": "Tracer Study (per alumni)",
     *         "description": "...",
     *         "measures": [{"key":"FactTracerStudy.count_alumni","label":"Jumlah Alumni","format":"integer"}],
     *         "dimension_groups": [
     *           {"group":"Program Studi","members":[{"key":"DimProdi.nama_prodi","label":"Nama Program Studi"}]}
     *         ]
     *       }
     *     ],
     *     "limits": {"max_dimensions":3,"max_measures":5,"max_rows":5000}
     *   }
     * }
     */
    public function catalog(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->service->catalog(),
        ]);
    }

    /**
     * GET /api/dashboard/explorer/dimension-values
     *
     * Query params:
     *   dimension  string  wajib, mis. DimProdi.nama_prodi
     *
     * Response 200:
     * { "success": true, "data": { "dimension": "...", "values": ["D3","D4"] } }
     */
    public function dimensionValues(Request $request): JsonResponse
    {
        $request->validate([
            'dimension' => 'required|string|max:150',
        ]);

        $dimension = $request->query('dimension');
        $p         = $this->scopedParams($request);

        try {
            return response()->json([
                'success' => true,
                'data'    => [
                    'dimension' => $dimension,
                    'values'    => $this->service->dimensionValues($dimension, $p),
                ],
            ]);
        } catch (\RuntimeException $e) {
            return $this->serviceError($e);
        }
    }

    /**
     * POST /api/dashboard/explorer/query
     *
     * Body:
     * {
     *   "cube": "FactTracerStudy",
     *   "measures": ["FactTracerStudy.avg_masa_tunggu_bekerja"],
     *   "dimensions": ["DimProdi.nama_prodi","DimAlumni.tahun_lulus"],
     *   "filters": [{"member":"DimProdi.jenjang","values":["D4"]}],
     *   "limit": 5000
     * }
     *
     * Response 200:
     * {
     *   "success": true,
     *   "data": {
     *     "cube": "FactTracerStudy",
     *     "measures":   [{"key":"...","label":"...","format":"decimal"}],
     *     "dimensions": [{"key":"...","label":"..."}],
     *     "rows": [{"DimProdi.nama_prodi":"D3 Teknik Informatika","DimAlumni.tahun_lulus":"2024","FactTracerStudy.avg_masa_tunggu_bekerja":3.9}],
     *     "row_count": 1,
     *     "truncated": false
     *   }
     * }
     *
     * 422 bila measure/dimensi di luar katalog atau tidak ter-join ke cube.
     * 503 bila Cube.js tidak bisa dihubungi.
     */
    public function query(ExplorerQueryRequest $request): JsonResponse
    {
        $p = $this->scopedParams($request);

        try {
            return response()->json([
                'success' => true,
                'data'    => $this->service->query($request->explorerPayload(), $p),
            ]);
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
