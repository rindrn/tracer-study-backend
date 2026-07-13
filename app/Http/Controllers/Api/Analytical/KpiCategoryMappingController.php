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
            'is_active'      => 'nullable|boolean',
            'all'            => 'nullable|boolean',
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

    // GET /api/kpi-category-mappings/formula?semantic_role=&digunakan_oleh=
    public function formula(Request $request): JsonResponse
    {
        $request->validate([
            'semantic_role'  => 'required|string|max:50',
            'digunakan_oleh' => 'required|string|max:50',
        ]);

        return response()->json([
            'success' => true,
            'data'    => $this->service->formula(
                $request->query('semantic_role'),
                $request->query('digunakan_oleh'),
            ),
        ]);
    }
}
