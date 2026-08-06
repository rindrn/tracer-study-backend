<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\Transactional\PublicReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Pengelolaan laporan Tracer Study tahunan oleh Ketua Tracer.
 *
 * Route-nya dijaga middleware role:head_tracer -- requirement menyebut Ketua
 * Tracer secara spesifik, jadi tidak menumpang permission admin lain yang
 * sudah dipegang tracer_team.
 */
class PublicReportController extends Controller
{
    /**
     * Batas ukuran unggahan dalam kilobyte.
     *
     * PERHATIAN: nilai ini tidak ada gunanya kalau php.ini masih memakai
     * bawaan upload_max_filesize=2M / post_max_size=8M -- PHP menolak request
     * SEBELUM Laravel sempat memvalidasi, dan yang sampai ke pengguna adalah
     * galat yang membingungkan (request body kosong, bukan pesan validasi).
     * Laporan seperti contoh POLBAN (31 halaman penuh gambar) puluhan MB,
     * jadi php.ini harus dinaikkan bersamaan dengan angka ini.
     */
    private const MAX_UPLOAD_KB = 51200; // 50 MB

    public function __construct(
        private readonly PublicReportService $service,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->service->listForAdmin(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title'        => ['required', 'string', 'max:200'],
            'description'  => ['nullable', 'string', 'max:2000'],
            'report_year'  => ['required', 'integer', 'min:1990', 'max:2100'],
            'is_published' => ['nullable', 'boolean'],
            // mimes:pdf mengecek isi berkas, bukan hanya ekstensinya.
            'file'         => ['required', 'file', 'mimes:pdf', 'max:' . self::MAX_UPLOAD_KB],
        ]);

        try {
            $report = $this->service->create(
                file:       $request->file('file'),
                attributes: $validated,
                uploadedBy: $request->user()?->id,
            );
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Laporan berhasil diunggah.',
            'data'    => $report,
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'title'        => ['sometimes', 'string', 'max:200'],
            'description'  => ['nullable', 'string', 'max:2000'],
            'report_year'  => ['sometimes', 'integer', 'min:1990', 'max:2100'],
            'is_published' => ['sometimes', 'boolean'],
        ]);

        try {
            $report = $this->service->update($id, $validated);
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Laporan berhasil diperbarui.',
            'data'    => $report,
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            $this->service->delete($id);
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 404);
        }

        return response()->json(['success' => true, 'message' => 'Laporan berhasil dihapus.']);
    }
}
