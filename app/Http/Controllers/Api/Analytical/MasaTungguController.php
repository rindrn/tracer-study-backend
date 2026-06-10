<?php

namespace App\Http\Controllers\Api\Analytical;

use App\Http\Controllers\Controller;
use App\Services\Analytical\MasaTungguService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * MasaTungguController
 *
 * Segmen: Masa Tunggu Kerja Lulusan (KPI 5)
 *
 * Routes (semua di dalam auth:sanctum group):
 *
 *   GET /api/dashboard/masa-tunggu/bar
 *       → Bar % lulusan masa tunggu ≤ 6 bulan per prodi (combo chart di FE)
 *
 *   GET /api/dashboard/masa-tunggu/distribusi
 *       → Bar horizontal distribusi rentang: 0-3, 3-6, >6 bulan
 *
 *   GET /api/dashboard/masa-tunggu/drill-down
 *       → List alumni berdasarkan rentang masa tunggu yang diklik
 *
 *   GET /api/dashboard/masa-tunggu/bandingkan
 *       → Perbandingan distribusi masa tunggu per prodi (halaman Bandingkan)
 *
 * Taruh di: app/Http/Controllers/Api/Analytical/MasaTungguController.php
 */
class MasaTungguController extends Controller
{
    public function __construct(
        private readonly MasaTungguService $service,
    ) {}

    // ──────────────────────────────────────────────────────────────

    /**
     * GET /api/dashboard/masa-tunggu/bar
     *
     * Grafik combo (bar rata-rata + garis tren) masa tunggu per tahun lulus.
     * Setiap bar = avg_masa_tunggu_bekerja + pct_cepat (≤ 6 bulan) per prodi.
     *
     * Query params (semua opsional):
     *   jenjang         string   Filter jenjang prodi (D3 / D4)
     *   jurusan         string   Filter jurusan
     *   nama_prodi      string   Filter nama program studi (exact)
     *   tahun_lulus     string   Filter tahun lulus alumni
     *   minggu_snapshot string   Filter minggu snapshot DW (contoh: W-48)
     *
     * Response 200:
     * {
     *   "success": true,
     *   "data": {
     *     "filters": { "jenjang": "D4" },
     *     "threshold": { "lam_ban_pt": 3, "rata_rata_institusi": 4.0 },
     *     "data": [
     *       {
     *         "nama_prodi": "Teknik Informatika",
     *         "jenjang": "D4",
     *         "jurusan": "Teknik Informatika",
     *         "tahun_lulus": "2023",
     *         "count_alumni": 95,
     *         "count_terserap": 81,
     *         "count_masa_tunggu_cepat": 62,
     *         "pct_cepat": 76.5,
     *         "avg_masa_tunggu_bekerja": 3.8
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
            'tahun_lulus'     => 'nullable|string|max:5',
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
     * GET /api/dashboard/masa-tunggu/distribusi
     *
     * Distribusi jumlah & persentase alumni berdasarkan rentang masa tunggu.
     * Rentang: 0-3 bulan (hijau), 3-6 bulan (oranye), >6 bulan (merah).
     *
     * Query params (semua opsional):
     *   jenjang         string
     *   jurusan         string
     *   nama_prodi      string
     *   tahun_lulus     string
     *   minggu_snapshot string
     *
     * Response 200:
     * {
     *   "success": true,
     *   "data": {
     *     "filters": {},
     *     "summary": {
     *       "avg_masa_tunggu": 4.2,
     *       "min_masa_tunggu": 0,
     *       "max_masa_tunggu": 18,
     *       "total_bekerja": 105
     *     },
     *     "distribusi": [
     *       { "rentang": "0-3",  "label": "< 3 bulan",  "count": 45, "pct": 42.9, "color": "green" },
     *       { "rentang": "3-6",  "label": "3-6 bulan",  "count": 38, "pct": 36.2, "color": "orange" },
     *       { "rentang": ">6",   "label": "> 6 bulan",  "count": 22, "pct": 21.0, "color": "red" }
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
            'tahun_lulus'     => 'nullable|string|max:5',
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
     * GET /api/dashboard/masa-tunggu/drill-down
     *
     * Dipanggil saat user klik bar rentang masa tunggu di chart distribusi.
     * Parameter `rentang` wajib diisi.
     *
     * Query params:
     *   rentang         string   "0-3" | "3-6" | ">6" — WAJIB
     *   nama_prodi      string   Filter opsional
     *   jenjang         string   Filter opsional
     *   jurusan         string   Filter opsional
     *   tahun_lulus     string   Filter opsional
     *   minggu_snapshot string   Filter opsional
     *   search          string   Cari nama / NIM alumni
     *   page            int      Default: 1
     *   per_page        int      Default: 15, max: 100
     *
     * Response 200:
     * {
     *   "success": true,
     *   "data": {
     *     "rentang": "0-3",
     *     "filters": { "nama_prodi": "Teknik Informatika" },
     *     "pagination": { "page": 1, "per_page": 15, "total_on_page": 15 },
     *     "data": [
     *       {
     *         "nama": "Eko Prasetyo",
     *         "nim": "3240006",
     *         "nama_prodi": "Teknik Pendingin",
     *         "jenjang": "D3",
     *         "tahun_lulus": "2024",
     *         "masa_tunggu_bekerja": 2,
     *         "status": "Bekerja"
     *       }
     *     ]
     *   }
     * }
     */
    public function drillDown(Request $request): JsonResponse
    {
        $params = $request->validate([
            'rentang'         => 'required|string|in:0-3,3-6,>6',
            'jenjang'         => 'nullable|string|in:D3,D4',
            'jurusan'         => 'nullable|string|max:100',
            'nama_prodi'      => 'nullable|string|max:100',
            'tahun_lulus'     => 'nullable|string|max:5',
            'minggu_snapshot' => 'nullable|string|max:10',
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

    // ──────────────────────────────────────────────────────────────

    /**
     * GET /api/dashboard/masa-tunggu/bandingkan
     *
     * Halaman Bandingkan Prodi — perbandingan distribusi masa tunggu + avg per prodi.
     *
     * Query params:
     *   prodi[]         array    Daftar nama_prodi yang dipilih chip (opsional, kosong = semua)
     *   jenjang         string
     *   jurusan         string
     *   tahun_lulus     string
     *   minggu_snapshot string
     *
     * Response 200:
     * {
     *   "success": true,
     *   "data": {
     *     "filters": {},
     *     "prodi_list": ["Teknik Informatika", "Teknik Kimia", ...],
     *     "data": [
     *       {
     *         "nama_prodi": "Teknik Informatika",
     *         "jenjang": "D4",
     *         "jurusan": "Teknik Informatika",
     *         "total_bekerja": 81,
     *         "avg_masa_tunggu": 3.8,
     *         "count_0_3": 45, "pct_0_3": 55.6,
     *         "count_3_6": 24, "pct_3_6": 29.6,
     *         "count_lebih_6": 12, "pct_lebih_6": 14.8
     *       }
     *     ]
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