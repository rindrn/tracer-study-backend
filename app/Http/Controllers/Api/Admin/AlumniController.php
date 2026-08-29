<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\StoreAlumniRequest;
use App\Http\Requests\Api\Admin\UpdateAlumniRequest;
use App\Services\Transactional\AdminAlumniService;
use App\Services\Transactional\AuditLogService;
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
        private readonly AuditLogService $audit,
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
                'response_status' => $request->query('response_status'),
            ],
            perPage: (int) $request->query('per_page', 15),
        );

        return response()->json(['success' => true, 'data' => $result]);
    }

    /**
     * GET /api/admin/alumni/respondent-stats?questionnaire_id=…
     *
     * Hitungan finished / ongoing / not_started untuk satu kuesioner.
     * Terpisah dari index() karena kartu ringkasan harus menghitung SELURUH
     * responden, bukan hanya halaman yang sedang dibuka.
     */
    public function respondentStats(Request $request): JsonResponse
    {
        $questionnaireId = $request->query('questionnaire_id');

        if (!$questionnaireId) {
            return response()->json([
                'success' => false,
                'message' => 'Parameter questionnaire_id wajib diisi.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data'    => $this->service->respondentStats($request->user(), [
                'questionnaire_id' => (int) $questionnaireId,
                'search'           => $request->query('search'),
                'jurusan'          => $request->query('jurusan'),
                'program_id'       => $request->query('program_id') ? (int) $request->query('program_id') : null,
                'graduation_year'  => $request->query('graduation_year') ? (int) $request->query('graduation_year') : null,
            ]),
        ]);
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
            'data'    => $this->service->getStats(
                $request->user(),
                $request->query('graduation_year') ? (int) $request->query('graduation_year') : null,
                $request->query('program_id') ? (int) $request->query('program_id') : null,
            ),
        ]);
    }

    /**
     * GET /api/alumni/stats-by-program
     *
     * Stats yang sama, dipecah per prodi. Dipakai layar kartu prodi di
     * halaman Data Alumni sebelum tabelnya ditampilkan.
     */
    public function statsByProgram(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->service->getStatsByProgram(
                $request->user(),
                $request->query('graduation_year') ? (int) $request->query('graduation_year') : null,
            ),
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

    /**
     * POST /api/admin/alumni/export-audit
     *
     * Ekspor "Data Alumni" (tombol Export di Kelola Mahasiswa) dirakit
     * sepenuhnya di klien (ExcelJS, lihat StudentManagementPage.tsx) — tidak
     * ada permintaan ke backend untuk mengunduh berkasnya. Tanpa endpoint
     * ini, ekspor data pribadi itu lolos tanpa jejak audit sama sekali,
     * padahal `config/privacy.php` (`audit_read_actions`) mewajibkan
     * `export.alumni` tercatat setara `export.ministry`. Frontend memanggil
     * ini tepat sebelum merakit berkas.
     */
    public function logExport(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'rows' => ['nullable', 'integer'],
        ]);

        $this->audit->record('export.alumni', [
            'entity_type' => 'alumni_profiles',
            'context'     => [
                'rows' => $validated['rows'] ?? null,
            ],
        ]);

        return response()->json(['success' => true]);
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
        // questionnaire_id kini MENENTUKAN lingkup. Petugas membuka halaman
        // responden satu kuesioner tertentu, jadi yang dibuka kembali memang
        // kuesioner itu saja — kuesioner lain milik alumni yang sama tetap
        // terkirim. Sebelumnya nilai ini divalidasi lalu diabaikan, dan satu
        // penekanan tombol membuka semuanya sekaligus.
        $validated = $request->validate(['questionnaire_id' => ['required', 'integer']]);

        $reopened = app(\App\Services\Transactional\ResponseReopenService::class)
            ->reopen($alumniId, [$validated['questionnaire_id']]);

        if ($reopened === 0) {
            return response()->json([
                'success' => false,
                'message' => 'Pengisian alumni pada kuesioner ini tidak berstatus Finish, atau kuesionernya sudah tidak aktif bagi alumni tersebut.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Pengisian alumni pada kuesioner ini dibuka kembali. Jawaban sebelumnya dipertahankan dan akan muncul kembali saat alumni membuka formulir.',
        ]);
    }
}
