<?php

namespace App\Http\Controllers\Api\Analytical;

use App\Http\Controllers\Controller;
use App\Services\Analytical\FilterMetaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * FilterMetaController
 *
 * Endpoint tunggal untuk semua opsi global filter dashboard.
 * FE memanggil ini SEKALI saat dashboard mount, lalu simpan
 * hasilnya di state management (Zustand / Pinia / Redux).
 *
 * Route:
 *   GET /api/dashboard/meta/filter-options
 *
 * Response 200:
 * {
 *   "success": true,
 *   "data": {
 *     "tahun_lulus": ["2024","2023","2022","2021","2020"],
 *     "minggu_snapshot": [
 *       { "minggu_snapshot": "W-48", "tahun_snapshot": "2024", "label": "W-48 / 2024" },
 *       { "minggu_snapshot": "W-36", "tahun_snapshot": "2024", "label": "W-36 / 2024" }
 *     ],
 *     "jenjang": ["D3","D4"],
 *     "jurusan": [
 *       { "jurusan": "Akuntansi",          "jenjang": "D4" },
 *       { "jurusan": "Teknik Elektro",     "jenjang": "D3" },
 *       { "jurusan": "Teknik Informatika", "jenjang": "D3" }
 *     ],
 *     "prodi": [
 *       { "nama_prodi": "Akuntansi Manajemen",  "jurusan": "Akuntansi",          "jenjang": "D4", "kode_prodi": "62401" },
 *       { "nama_prodi": "Teknik Informatika",   "jurusan": "Teknik Informatika", "jenjang": "D3", "kode_prodi": "55401" }
 *     ]
 *   }
 * }
 *
 * Taruh di: app/Http/Controllers/Api/Analytical/FilterMetaController.php
 */
class FilterMetaController extends Controller
{
    public function __construct(
        private readonly FilterMetaService $service,
    ) {}

    public function filterOptions(): JsonResponse
    {
        try {
            $dto = $this->service->getFilterOptions();

            return response()->json([
                'success' => true,
                'data'    => $dto->toArray(),
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }
    }
}