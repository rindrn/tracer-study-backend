<?php

namespace App\Http\Controllers\Api\Transactional;

use App\Http\Controllers\Controller;
use App\Services\Transactional\DegreeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DegreeController extends Controller
{
    public function __construct(
        private readonly DegreeService $service,
    ) {}

    /**
     * GET /api/degrees
     *
     * Terbuka untuk seluruh peran yang sudah masuk, sama seperti
     * JurusanController::index — dipakai dropdown jenjang di form Master Data
     * dan label jenjang di grafik.
     *
     * Ini BUKAN sumber untuk penyaring dasbor. Penyaring memakai
     * `filter-meta`, yang hanya memuat jenjang yang benar-benar punya data di
     * gudang data. Dua kebutuhan berbeda: yang ini menjawab "jenjang apa saja
     * yang BOLEH ada", yang itu menjawab "jenjang apa saja yang ADA datanya".
     */
    public function index(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->service->list()]);
    }

    /** POST /api/degrees */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code'       => ['required', 'string', 'max:20'],
            'label'      => ['nullable', 'string', 'max:50'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_active'  => ['nullable', 'boolean'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Jenjang berhasil ditambahkan.',
            'data'    => $this->service->create($validated),
        ], 201);
    }

    /**
     * PUT /api/degrees/{id}
     *
     * Seluruh medan opsional supaya antarmuka bisa mengirim hanya yang
     * berubah — penting karena `code` punya aturan sendiri di service dan
     * mengirimnya kembali tanpa perubahan tidak boleh dianggap percobaan
     * mengubah.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'code'       => ['sometimes', 'string', 'max:20'],
            'label'      => ['sometimes', 'string', 'max:50'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:65535'],
            'is_active'  => ['sometimes', 'boolean'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Jenjang berhasil diperbarui.',
            'data'    => $this->service->update($id, $validated),
        ]);
    }

    /** DELETE /api/degrees/{id} */
    public function destroy(int $id): JsonResponse
    {
        $this->service->delete($id);

        return response()->json(['success' => true, 'message' => 'Jenjang berhasil dihapus.']);
    }
}
