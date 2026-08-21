<?php

namespace App\Http\Controllers\Api\Transactional;

use App\Http\Controllers\Controller;
use App\Services\Transactional\FakultasService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FakultasController extends Controller
{
    public function __construct(
        private readonly FakultasService $service,
    ) {}

    /**
     * GET /api/fakultas
     *
     * Terbuka untuk seluruh peran yang sudah masuk (sama seperti
     * JurusanController::index) -- mengisi dropdown Fakultas di form
     * Kelola Staff untuk role ketua_fakultas.
     */
    public function index(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->service->list()]);
    }

    /** POST /api/fakultas */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Fakultas berhasil ditambahkan.',
            'data'    => $this->service->create($validated['name']),
        ], 201);
    }

    /** PUT /api/fakultas/{id} */
    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'name'      => ['required', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Fakultas berhasil diperbarui.',
            'data'    => $this->service->update($id, $validated['name'], $validated['is_active'] ?? null),
        ]);
    }

    /** DELETE /api/fakultas/{id} */
    public function destroy(int $id): JsonResponse
    {
        $this->service->delete($id);

        return response()->json(['success' => true, 'message' => 'Fakultas berhasil dihapus.']);
    }

    /**
     * PUT /api/fakultas/{id}/jurusans
     *
     * Ganti seluruh keanggotaan jurusan fakultas ini lewat
     * fakultas_jurusan_scopes -- inilah yang menentukan cakupan Ketua
     * Fakultas (gabungan seluruh prodi di seluruh jurusan anggota).
     */
    public function syncJurusans(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'jurusan_ids'   => ['present', 'array'],
            'jurusan_ids.*' => ['integer', 'exists:oltp.jurusans,id'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Keanggotaan jurusan fakultas berhasil disimpan.',
            'data'    => $this->service->syncScopedJurusans($id, $validated['jurusan_ids']),
        ]);
    }
}
