<?php

namespace App\Http\Controllers\Api\Analytical;

use App\Http\Controllers\Controller;
use App\Http\Validators\KpiCategoryMappingValidator;
use App\Services\Analytical\KpiCategoryMappingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KpiCategoryMappingController extends Controller
{
    public function __construct(
        private readonly KpiCategoryMappingService $service,
        private readonly KpiCategoryMappingValidator $validator,
    ) {}

    // GET /api/kpi-category-mappings?semantic_role=&digunakan_oleh=&is_active=&all=
    // is_active: filter standar (true=aktif saja [default kalau tidak dikirim,
    // perilaku lama utk Langkah 2], false=nonaktif saja). all=true mengabaikan
    // is_active dan mengembalikan SEMUA baris -- dipakai tab audit "Data
    // Tersimpan" supaya baris yang dinonaktifkan tetap terlihat (forward-only
    // artinya baris tidak pernah hilang dari database; API yang selalu
    // memfilter is_active=true membuatnya SEOLAH hilang dari sudut pandang
    // admin -- bug yang sempat lolos sebelum ini diperbaiki).
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'semantic_role'  => 'nullable|string|max:50',
            'digunakan_oleh' => 'nullable|string|max:50',
            // BUKAN 'boolean' -- rule bawaan Laravel itu HANYA menerima
            // true/false/0/1/'0'/'1' (in_array strict), sedangkan axios
            // mengirim boolean JS sebagai literal string "true"/"false" di
            // query string -- selalu gagal validasi (422) walau ditulis
            // dengan benar di FE. $request->boolean() di bawah sudah benar
            // menafsirkan "true"/"false" via filter_var(), jadi celahnya
            // murni di rule validasi, bukan di logika sesudahnya.
            'is_active'      => 'nullable|in:0,1,true,false',
            'all'            => 'nullable|in:0,1,true,false',
        ]);

        $isActive = $request->boolean('all')
            ? null
            : ($request->has('is_active') ? $request->boolean('is_active') : true);

        return response()->json([
            'success' => true,
            'data'    => $this->service->list(
                $request->query('semantic_role'),
                $request->query('digunakan_oleh'),
                $isActive,
            )->values(),
        ]);
    }

    // GET /api/kpi-category-mappings/taxonomy?semantic_role=
    public function taxonomy(Request $request): JsonResponse
    {
        $request->validate(['semantic_role' => 'required|string|max:50']);

        return response()->json([
            'success' => true,
            'data'    => $this->service->taxonomy($request->query('semantic_role')),
        ]);
    }

    // GET /api/kpi-category-mappings/taxonomy-all -- semua role sekaligus,
    // dipakai selector "role yang sudah aktif" di Langkah 1 (lihat catatan
    // di KpiCategoryMappingService::taxonomyAllRoles()).
    public function taxonomyAll(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->service->taxonomyAllRoles(),
        ]);
    }

    // POST /api/kpi-category-mappings
    public function store(Request $request): JsonResponse
    {
        $validated = $this->validator->validateStore($request->all());

        $data = $this->service->store($validated, $request->user()?->id);

        return response()->json([
            'success' => true,
            'message' => 'KPI category mapping berhasil dibuat.',
            'data'    => $data,
        ], 201);
    }

    // POST /api/kpi-category-mappings/{id}/deactivate
    public function deactivate(Request $request, int $id): JsonResponse
    {
        $this->service->deactivate($id, $request->user()?->id);

        return response()->json([
            'success' => true,
            'message' => 'KPI category mapping berhasil dinonaktifkan.',
        ]);
    }

    // GET /api/kpi-category-mappings/formula?semantic_role=&digunakan_oleh=&minggu_snapshot=
    public function formula(Request $request): JsonResponse
    {
        $request->validate([
            'semantic_role'   => 'required|string|max:50',
            'digunakan_oleh'  => 'required|string|max:50',
            // id_waktu snapshot yang sedang aktif di filter global FE --
            // supaya tooltip point-in-time, bukan selalu definisi hari ini.
            'minggu_snapshot' => 'nullable|string|max:20',
        ]);

        return response()->json([
            'success' => true,
            'data'    => $this->service->formula(
                $request->query('semantic_role'),
                $request->query('digunakan_oleh'),
                $request->query('minggu_snapshot'),
            ),
        ]);
    }
}
