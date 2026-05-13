<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\StoreAlumniRequest;
use App\Http\Requests\Api\Admin\UpdateAlumniRequest;
use App\Services\Transactional\AdminAlumniService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin/AlumniController — CRUD alumni untuk panel admin.
 *
 * Role scope di-handle di service:
 *   - admin  : full akses
 *   - kaprodi: hanya prodinya
 *   - p2mpp  : read-only (tidak bisa store/update/destroy)
 */
class AlumniController extends Controller
{
    public function __construct(
        private readonly AdminAlumniService $service,
    ) {}

    /** GET /api/admin/alumni */
    public function index(Request $request): JsonResponse
    {
        $result = $this->service->list(
            user:    $request->user(),
            filters: ['search' => $request->query('search')],
            perPage: (int) $request->query('per_page', 15),
        );

        return response()->json(['success' => true, 'data' => $result]);
    }

    /**
     * GET /api/admin/alumni/stats
     *
     * Ringkasan statistik alumni untuk dashboard kaprodi / admin.
     * Kaprodi: scoped ke prodinya saja; admin: seluruh prodi.
     */
    public function stats(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->service->getStats($request->user()),
        ]);
    }

    /** GET /api/admin/alumni/{id} */
    public function show(Request $request, int $id): JsonResponse
    {
        $alumni = $this->service->show($request->user(), $id);
        return response()->json(['success' => true, 'data' => $alumni]);
    }

    /** POST /api/admin/alumni */
    public function store(StoreAlumniRequest $request): JsonResponse
    {
        $id = $this->service->create($request->user(), $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Data alumni berhasil ditambahkan.',
            'data'    => ['id' => $id],
        ], 201);
    }

    /** PUT /api/admin/alumni/{id} */
    public function update(UpdateAlumniRequest $request, int $id): JsonResponse
    {
        $this->service->update($request->user(), $id, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Data alumni berhasil diperbarui.',
        ]);
    }

    /** DELETE /api/admin/alumni/{id} */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->service->delete($request->user(), $id);

        return response()->json([
            'success' => true,
            'message' => 'Data alumni berhasil dihapus.',
        ]);
    }
}
