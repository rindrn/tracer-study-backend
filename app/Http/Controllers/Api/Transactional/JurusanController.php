<?php

namespace App\Http\Controllers\Api\Transactional;

use App\Http\Controllers\Controller;
use App\Services\Transactional\JurusanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JurusanController extends Controller
{
    public function __construct(
        private readonly JurusanService $service,
    ) {}

    /**
     * GET /api/jurusans
     *
     * Terbuka untuk seluruh peran yang sudah masuk, bukan hanya Ketua
     * Tracer: daftar ini mengisi penyaring jurusan di halaman Responden,
     * Kelola Staff, dan Kelola Mahasiswa. Menutupnya berarti halaman-halaman
     * itu kembali memakai daftar tetap.
     */
    public function index(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->service->list()]);
    }

    /** POST /api/jurusans */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Jurusan berhasil ditambahkan.',
            'data'    => $this->service->create($validated['name']),
        ], 201);
    }

    /** PUT /api/jurusans/{id} */
    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'name'      => ['required', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $result = $this->service->update($id, $validated['name'], $validated['is_active'] ?? null);

        // Penggantian nama merambat diam-diam ke data lain, jadi akibatnya
        // disebutkan supaya pengguna tahu apa yang barusan ikut berubah.
        $affected = $result['affected'];
        $message  = 'Jurusan berhasil diperbarui.';
        if ($affected['programs'] > 0 || $affected['users'] > 0) {
            $bagian = [];
            if ($affected['programs'] > 0) $bagian[] = "{$affected['programs']} program studi";
            if ($affected['users'] > 0)    $bagian[] = "{$affected['users']} akun staf";
            $message .= ' Nama baru ikut diterapkan pada ' . implode(' dan ', $bagian) . '.';
        }

        return response()->json(['success' => true, 'message' => $message, 'data' => $result]);
    }

    /** DELETE /api/jurusans/{id} */
    public function destroy(int $id): JsonResponse
    {
        $this->service->delete($id);

        return response()->json(['success' => true, 'message' => 'Jurusan berhasil dihapus.']);
    }

    /**
     * PUT /api/jurusans/{id}/programs
     *
     * Ganti seluruh keanggotaan program studi jurusan ini lewat
     * jurusan_program_scopes -- inilah yang menentukan cakupan Kajur
     * (bukan lagi cocokkan teks programs.jurusan).
     */
    public function syncPrograms(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'program_ids'   => ['present', 'array'],
            'program_ids.*' => ['integer', 'exists:oltp.programs,id'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Keanggotaan program studi jurusan berhasil disimpan.',
            'data'    => $this->service->syncScopedPrograms($id, $validated['program_ids']),
        ]);
    }
}
