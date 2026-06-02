<?php

namespace App\Http\Controllers\Api\Analytical;

use App\Http\Controllers\Controller;
use App\Services\Analytical\KeterserapanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * KeterserapanController
 *
 * Segmen: Tingkat Keterserapan Lulusan
 *
 * Routes (semua di dalam auth:sanctum group):
 *
 *   GET /api/dashboard/keterserapan/bar
 *       → Bar stacked 100% distribusi status per tahun lulus
 *
 *   GET /api/dashboard/keterserapan/pie
 *       → Pie distribusi status alumni (snapshot terkini)
 *
 *   GET /api/dashboard/keterserapan/drill-down
 *       → Drill-down list alumni per status (modal klik chart)
 *       → Drill-down list alumni per tahun lulus (modal klik chart)
 *
 *   GET /api/dashboard/keterserapan/bandingkan
 *       → Perbandingan keterserapan per prodi (halaman Bandingkan)
 *       Chip filter prodi berasal dari GET /api/dashboard/meta/filter-options — tidak perlu endpoint terpisah.
 *
 * Taruh di: app/Http/Controllers/Api/Analytical/KeterserapanController.php
 */
class KeterserapanController extends Controller
{
    public function __construct(
        private readonly KeterserapanService $service,
    ) {}

    // ──────────────────────────────────────────────────────────────

    /**
     * GET /api/dashboard/keterserapan/bar
     *
     * Grafik bar keterserapan per tahun lulus.
     * Setiap bar menampilkan: terserap (Bekerja + Wirausaha + Studi Lanjut) vs tidak terserap.
     * Breakdown per status juga disertakan untuk keperluan tooltip hover.
     *
     * Definisi "terserap" mengikuti IKU 2 Kemendikbud:
     *   Bekerja | Wirausaha/Wiraswasta | Studi Lanjut | Studi & Bekerja
     *
     * Query params (semua opsional):
     *   jenjang         string   Filter jenjang prodi (D3 / D4)
     *   jurusan         string   Filter jurusan
     *   nama_prodi      string   Filter nama program studi (exact)
     *   minggu_snapshot string   Filter minggu snapshot DW (contoh: W-48)
     *
     * Tidak menerima tahun_lulus karena tahun_lulus = sumbu X chart.
     *
     * Response 200:
     * {
     *   "success": true,
     *   "data": {
     *     "chart_type": "bar_keterserapan",
     *     "filters": { "jenjang": "D3" },
     *     "available_tahun": ["2024","2023","2022","2021","2020"],
     *     "available_snapshots": [
     *       {"minggu_snapshot":"W-48","tahun_snapshot":"2024"},
     *       {"minggu_snapshot":"W-36","tahun_snapshot":"2024"}
     *     ],
     *     "status_terserap": ["Bekerja","Wirausaha","Wiraswasta","Studi Lanjut","Studi & Bekerja"],
     *     "data": [
     *       {
     *         "tahun_lulus": "2022",
     *         "total": 1700,
     *         "count_terserap": 1185,
     *         "pct_terserap": 69.7,
     *         "count_tidak": 515,
     *         "pct_tidak": 30.3,
     *         "breakdown": [
     *           { "status": "Bekerja",        "count": 980, "pct": 57.6, "kategori": "terserap" },
     *           { "status": "Studi Lanjut",   "count": 85,  "pct": 5.0,  "kategori": "terserap" },
     *           { "status": "Wirausaha",      "count": 120, "pct": 7.1,  "kategori": "terserap" },
     *           { "status": "Tidak Bekerja",  "count": 390, "pct": 22.9, "kategori": "tidak"    },
     *           { "status": "Mencari Kerja",  "count": 125, "pct": 7.4,  "kategori": "tidak"    }
     *         ]
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
     * GET /api/dashboard/keterserapan/pie
     *
     * Query params (semua opsional):
     *   jenjang         string
     *   jurusan         string
     *   nama_prodi      string
     *   tahun_lulus     string   Filter tahun lulus alumni (contoh: 2022)
     *   minggu_snapshot string   Biasanya diisi untuk snapshot terkini
     *
     * Response 200:
     * {
     *   "success": true,
     *   "data": {
     *     "chart_type": "pie",
     *     "filters": { "minggu_snapshot": "W-48" },
     *     "total": 8900,
     *     "data": [
     *       { "status": "Bekerja",       "count": 5100, "pct": 57.3 },
     *       { "status": "Wirausaha",     "count": 780,  "pct": 8.8  },
     *       { "status": "Studi Lanjut",  "count": 350,  "pct": 3.9  },
     *       { "status": "Tidak Bekerja", "count": 2670, "pct": 30.0 }
     *     ]
     *   }
     * }
     */
    public function pie(Request $request): JsonResponse
    {
        $params = $request->validate([
            'jenjang'         => 'nullable|string|in:D3,D4',
            'jurusan'         => 'nullable|string|max:100',
            'nama_prodi'      => 'nullable|string|max:100',
            'tahun_lulus'     => 'nullable|string|max:5',
            'minggu_snapshot' => 'nullable|string|max:10',
        ]);

        try {
            $dto = $this->service->getPie($params);
            return response()->json(['success' => true, 'data' => $dto->toArray()]);
        } catch (\RuntimeException $e) {
            return $this->serviceError($e);
        }
    }

    // ──────────────────────────────────────────────────────────────

    /**
     * GET /api/dashboard/keterserapan/drill-down
     *
     * Dipanggil saat user klik segmen chart (pie atau bar per tahun).
     * Minimal salah satu dari status atau tahun_lulus wajib diisi.
     *
     * Query params:
     *   status          string   Label status alumni ("Bekerja", "terserap", "tidak", dll.)
     *                            Wajib diisi jika tahun_lulus kosong.
     *   tahun_lulus     string   Tahun lulus yang diklik di bar chart.
     *                            Wajib diisi jika status kosong.
     *   jenjang         string
     *   jurusan         string
     *   nama_prodi      string
     *   minggu_snapshot string
     *   search          string
     *   page            int      Default: 1
     *   per_page        int      Default: 15, max: 100
     *
     * Response 200:
     * {
     *   "success": true,
     *   "data": {
     *     "status": "terserap",
     *     "filters": { "tahun_lulus": "2023" },
     *     "pagination": { "page": 1, "per_page": 15, "total_on_page": 15 },
     *     "data": [
     *       {
     *         "nama": "Irfan Hakim",
     *         "nim": "3230000",
     *         "nama_prodi": "Teknik Elektronika",
     *         "jenjang": "D3",
     *         "tahun_lulus": "2023",
     *         "status": "Bekerja"
     *       }
     *     ]
     *   }
     * }
     */
    public function drillDown(Request $request): JsonResponse
    {
        $params = $request->validate([
            'status'          => 'nullable|string|max:50',
            'jenjang'         => 'nullable|string|in:D3,D4',
            'jurusan'         => 'nullable|string|max:100',
            'nama_prodi'      => 'nullable|string|max:100',
            'tahun_lulus'     => 'nullable|string|max:5',
            'minggu_snapshot' => 'nullable|string|max:10',
            'search'          => 'nullable|string|max:100',
            'page'            => 'nullable|integer|min:1',
            'per_page'        => 'nullable|integer|min:5|max:100',
        ]);

        if (empty($params['status']) && empty($params['tahun_lulus'])) {
            return response()->json([
                'success' => false,
                'message' => 'Parameter status atau tahun_lulus wajib diisi.',
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
     * GET /api/dashboard/keterserapan/bandingkan
     *
     * Halaman Bandingkan Prodi — bar stacked 100% + tabel ringkasan.
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
     *     "filters": { "jenjang": "D3" },
     *     "prodi_list": ["Teknik Informatika", "Teknik Kimia", ...],
     *     "chart": [
     *       {
     *         "nama_prodi": "Teknik Kimia",
     *         "jenjang": "D3",
     *         "jurusan": "Teknik Kimia",
     *         "total": 48,
     *         "statuses": [
     *           { "label": "Bekerja",        "count": 13, "pct": 27.1 },
     *           { "label": "Mencari Kerja",  "count": 3,  "pct": 6.3  },
     *           { "label": "Studi Lanjut",   "count": 7,  "pct": 14.6 },
     *           { "label": "Wiraswasta",     "count": 7,  "pct": 14.6 },
     *           { "label": "Studi & Bekerja","count": 12, "pct": 25.0 },
     *           { "label": "Belum Bekerja",  "count": 6,  "pct": 12.5 }
     *         ]
     *       }
     *     ],
     *     "table": [
     *       {
     *         "nama_prodi": "Teknik Kimia",
     *         "jenjang": "D3",
     *         "jurusan": "Teknik Kimia",
     *         "total": 48,
     *         "bekerja":       { "count": 13, "pct": 27.1 },
     *         "mencari_kerja": { "count": 3,  "pct": 6.3  },
     *         "studi_lanjut":  { "count": 7,  "pct": 14.6 },
     *         "wiraswasta":    { "count": 7,  "pct": 14.6 },
     *         "studi_bekerja": { "count": 12, "pct": 25.0 },
     *         "belum_bekerja": { "count": 6,  "pct": 12.5 }
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