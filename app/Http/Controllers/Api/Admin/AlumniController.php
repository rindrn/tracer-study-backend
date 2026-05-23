<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\StoreAlumniRequest;
use App\Http\Requests\Api\Admin\UpdateAlumniRequest;
use App\Services\Transactional\AdminAlumniService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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

    /** GET /api/alumni */
    public function index(Request $request): JsonResponse
    {
        $questionnaireId = $request->query('questionnaire_id');
        $result = $this->service->list(
            user:    $request->user(),
            filters: [
                'search' => $request->query('search'),
                'questionnaire_id' => $questionnaireId ? (int) $questionnaireId : null,
                'jurusan' => $request->query('jurusan'),
                'program_id' => $request->query('program_id') ? (int) $request->query('program_id') : null,
                'graduation_year' => $request->query('graduation_year') ? (int) $request->query('graduation_year') : null,
            ],
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

    /**
     * GET /api/admin/alumni/template
     *
     * Download template Excel kosongan (hanya header) untuk acuan import alumni.
     * Dipakai admin / kepala tracer sebelum mengisi data untuk bulk import.
     */
    public function downloadTemplate(Request $request): BinaryFileResponse
    {
        return Excel::download(
            $this->service->buildImportTemplate(),
            'Template_Import_Alumni.xlsx',
        );
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

    /** POST /api/admin/alumni/import */
    public function importAlumni(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:5120'],
        ]);

        $result = $this->service->importFromExcel($request->file('file'));

        return response()->json([
            'success' => true,
            'message' => "{$result['imported']} data alumni berhasil diimpor.",
            'data'    => $result,
        ]);
    }

    /** POST /api/alumni/{alumniId}/reset-response */
    public function resetResponse(Request $request, int $alumniId): JsonResponse
    {
        $request->validate(['questionnaire_id' => ['required', 'integer']]);

        $repo = app(\App\Repositories\Transactional\ResponseRepository::class);
        $reset = $repo->resetToOngoing((int) $request->input('questionnaire_id'), $alumniId);

        if (!$reset) {
            return response()->json([
                'success' => false,
                'message' => 'Responden tidak dalam status finished atau tidak ditemukan.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Status responden berhasil direset ke ongoing.',
        ]);
    }
}
