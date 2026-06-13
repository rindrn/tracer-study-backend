<?php

namespace App\Http\Controllers\Api\Analytical;

use App\Http\Controllers\Controller;
use App\Services\Analytical\PendapatanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * PendapatanController
 *
 * Segmen: Pendapatan Lulusan
 *
 * Routes (semua di dalam auth:sanctum group):
 *
 *   GET /api/dashboard/pendapatan/bar
 *       → Dual-axis chart: avg gaji (bar) + % ≥ 1,2× UMP (line) per tahun lulus
 *
 *   GET /api/dashboard/pendapatan/distribusi
 *       → Grouped bar: proporsi < 1,2× UMP vs ≥ 1,2× UMP per tahun lulus
 *
 *   GET /api/dashboard/pendapatan/drill-down
 *       → List alumni per segmen UMP atau per tahun lulus (modal klik chart)
 *
 *   GET /api/dashboard/pendapatan/bandingkan
 *       → Perbandingan pendapatan per prodi (halaman Bandingkan)
 *
 * Pre-agg Cube.js: FactTracerStudy.distribusi_gaji
 */
class PendapatanController extends Controller
{
    public function __construct(
        private readonly PendapatanService $service,
    ) {}

    // ──────────────────────────────────────────────────────────────

    /**
     * GET /api/dashboard/pendapatan/bar
     *
     * Dual-axis chart per tahun lulus.
     * Sumbu kiri  (bar)  : rata-rata gaji (Rp)
     * Sumbu kanan (line) : % alumni ≥ 1,2× UMP
     *
     *
     * Query params (semua opsional):
     *   jenjang         string   D3 | D4
     *   jurusan         string
     *   nama_prodi      string
     *   minggu_snapshot string
     *
     * Response 200:
     * {
     *   "success": true,
     *   "data": {
     *     "chart_type": "dual_axis_pendapatan",
     *     "filters": {},
     *     "available_tahun": ["2021","2022","2023","2024"],
     *     "data": [
     *       {
     *         "tahun_lulus": "2021",
     *         "avg_gaji": 3500000,
     *         "min_gaji": 1200000,
     *         "max_gaji": 11000000,
     *         "total_alumni_ump": 1484,
     *         "count_above_ump": 772,
     *         "pct_above_ump": 52.0
     *       }
     *     ]
     *   }
     * }
     */
    public function bar(Request $request): JsonResponse
    {
        $params = $request->validate([
            'jenjang'         => 'nullable|string|in:D3,D4',
            'jurusan'         => 'nullable|string|max:100',
            'nama_prodi'      => 'nullable|string|max:100',
            'minggu_snapshot' => 'nullable|string|max:10',
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
     * GET /api/dashboard/pendapatan/distribusi
     *
     * Grouped bar: proporsi < 1,2× UMP vs ≥ 1,2× UMP per tahun lulus.
     * Denominator: count_dengan_data_ump (alumni yang ada data gaji + UMP ref).
     *
     * Query params (semua opsional):
     *   jenjang         string
     *   jurusan         string
     *   nama_prodi      string
     *   minggu_snapshot string
     *
     * Response 200:
     * {
     *   "success": true,
     *   "data": {
     *     "chart_type": "grouped_bar_ump",
     *     "filters": {},
     *     "available_tahun": ["2021","2022","2023","2024"],
     *     "data": [
     *       {
     *         "tahun_lulus": "2021",
     *         "total_alumni_ump": 1484,
     *         "count_below_ump": 712,
     *         "count_above_ump": 772,
     *         "pct_below_ump": 48.0,
     *         "pct_above_ump": 52.0
     *       }
     *     ]
     *   }
     * }
     */
    public function distribusi(Request $request): JsonResponse
    {
        $params = $request->validate([
            'jenjang'         => 'nullable|string|in:D3,D4',
            'jurusan'         => 'nullable|string|max:100',
            'nama_prodi'      => 'nullable|string|max:100',
            'minggu_snapshot' => 'nullable|string|max:10',
        ]);

        try {
            $dto = $this->service->getDistribusi($params);
            return response()->json(['success' => true, 'data' => $dto->toArray()]);
        } catch (\RuntimeException $e) {
            return $this->serviceError($e);
        }
    }

    // ──────────────────────────────────────────────────────────────

    /**
     * GET /api/dashboard/pendapatan/drill-down
     *
     * Dipanggil saat user klik bar/segmen chart.
     * Minimal salah satu dari segmen_ump atau tahun_lulus wajib diisi.
     *
     * Query params:
     *   segmen_ump      string   'above_ump' | 'below_ump'
     *                            Wajib diisi jika tahun_lulus kosong.
     *   tahun_lulus     string   Tahun lulus yang diklik.
     *                            Wajib diisi jika segmen_ump kosong.
     *   jenjang         string
     *   jurusan         string
     *   nama_prodi      string
     *   minggu_snapshot string
     *   search          string   Cari nama / NIM alumni
     *   page            int      Default: 1
     *   per_page        int      Default: 15, max: 100
     *
     * Response 200:
     * {
     *   "success": true,
     *   "data": {
     *     "segmen": "above_ump",
     *     "filters": { "tahun_lulus": "2023" },
     *     "pagination": { "page": 1, "per_page": 15, "total_on_page": 15 },
     *     "data": [
     *       {
     *         "nama": "Budi Santoso",
     *         "nim": "3230001",
     *         "nama_prodi": "Teknik Elektronika",
     *         "tahun_lulus": "2023",
     *         "perusahaan": "PT Astra International",
     *         "take_home_pay": 5500000,
     *         "flag_above_ump": 1
     *       }
     *     ]
     *   }
     * }
     */
    public function drillDown(Request $request): JsonResponse
    {
        $params = $request->validate([
            'segmen_ump'      => 'nullable|string|in:above_ump,below_ump',
            'jenjang'         => 'nullable|string|in:D3,D4',
            'jurusan'         => 'nullable|string|max:100',
            'nama_prodi'      => 'nullable|string|max:100',
            'tahun_lulus'     => 'nullable|string|max:5',
            'minggu_snapshot' => 'nullable|string|max:10',
            'search'          => 'nullable|string|max:100',
            'page'            => 'nullable|integer|min:1',
            'per_page'        => 'nullable|integer|min:5|max:100',
        ]);

        if (empty($params['segmen_ump']) && empty($params['tahun_lulus'])) {
            return response()->json([
                'success' => false,
                'message' => 'Parameter segmen_ump atau tahun_lulus wajib diisi.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $dto = $this->service->getDrillDown($params);
            return response()->json(['success' => true, 'data' => $dto->toArray()]);
        } catch (\RuntimeException $e) {
            return $this->serviceError($e);
        }
    }

    // ──────────────────────────────────────────────────────────────

    /**
     * GET /api/dashboard/pendapatan/bandingkan
     *
     * Halaman Bandingkan Prodi — Pendapatan.
     * statuses[] hanya 2 label: "≥ 1,2× UMP" dan "< 1,2× UMP".
     * Juga menyertakan avg_gaji per prodi untuk tooltip tambahan.
     *
     * Query params:
     *   prodi[]         array    Daftar nama_prodi yang dipilih chip
     *   jenjang         string
     *   jurusan         string
     *   tahun_lulus     string
     *   minggu_snapshot string
     *
     * Response 200:
     * {
     *   "success": true,
     *   "data": {
     *     "filters": { "jenjang": "D3" },
     *     "prodi_list": ["Teknik Informatika", "Teknik Kimia", ...],
     *     "chart": [
     *       {
     *         "nama_prodi": "Teknik Informatika",
     *         "jenjang": "D3",
     *         "jurusan": "Teknik Informatika",
     *         "total": 120,
     *         "avg_gaji": 4200000,
     *         "statuses": [
     *           { "label": "≥ 1,2× UMP", "count": 78, "pct": 65.0 },
     *           { "label": "< 1,2× UMP", "count": 42, "pct": 35.0 }
     *         ]
     *       }
     *     ],
     *     "table": [ ... ]
     *   }
     * }
     */
    public function bandingkan(Request $request): JsonResponse
    {
        $params = $request->validate([
            'prodi'           => 'nullable|array',
            'prodi.*'         => 'string|max:100',
            'jenjang'         => 'nullable|string|in:D3,D4',
            'jurusan'         => 'nullable|string|max:100',
            'tahun_lulus'     => 'nullable|string|max:5',
            'minggu_snapshot' => 'nullable|string|max:10',
        ]);

        try {
            $dto = $this->service->getBandingkan($params);
            return response()->json(['success' => true, 'data' => $dto->toArray()]);
        } catch (\RuntimeException $e) {
            return $this->serviceError($e);
        }
    }

    // ──────────────────────────────────────────────────────────────

    private function serviceError(\RuntimeException $e): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
        ], Response::HTTP_SERVICE_UNAVAILABLE);
    }
}