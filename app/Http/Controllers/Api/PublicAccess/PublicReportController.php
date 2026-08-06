<?php

namespace App\Http\Controllers\Api\PublicAccess;

use App\Http\Controllers\Controller;
use App\Services\Transactional\PublicDisplaySettingService;
use App\Services\Transactional\PublicReportService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Laporan Tracer Study untuk masyarakat umum -- TANPA autentikasi.
 *
 * Namespace PublicAccess (bukan "Public") karena `public` kata kunci PHP dan
 * tidak sah sebagai bagian namespace.
 *
 * Dua penjaga yang tidak boleh dilepas dari sini:
 *   - Hanya laporan is_published yang terlihat maupun terunduh; service
 *     memakai findPublishedById(), bukan findById().
 *   - Rentang tahun pengarsipan diterapkan di sisi server. Kalau hanya
 *     disaring di frontend, siapa pun bisa melewatinya lewat query string.
 */
class PublicReportController extends Controller
{
    public function __construct(
        private readonly PublicReportService $reports,
        private readonly PublicDisplaySettingService $settings,
    ) {}

    /** GET /api/public/reports */
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->reports->listForPublic($this->settings->getRangeFilter()),
        ]);
    }

    /**
     * GET /api/public/reports/{id}/download
     *
     * Dilayani PHP, bukan tautan langsung ke disk, supaya pencabutan publikasi
     * langsung berlaku dan jumlah unduhan bisa dihitung.
     */
    public function download(int $id): BinaryFileResponse|JsonResponse
    {
        $file = $this->reports->prepareDownload($id);

        if ($file === null) {
            return response()->json([
                'success' => false,
                'message' => 'Laporan tidak ditemukan atau belum dipublikasikan.',
            ], 404);
        }

        return response()->download($file['path'], $file['name'], [
            'Content-Type' => $file['mime'],
        ]);
    }

    /**
     * GET /api/public/reports/{id}/preview
     *
     * Sama dengan download() tapi tanpa Content-Disposition attachment, supaya
     * PDF bisa ditampilkan langsung di dalam <iframe> pratinjau halaman publik
     * alih-alih memicu unduhan.
     */
    public function preview(int $id): BinaryFileResponse|JsonResponse
    {
        $file = $this->reports->prepareDownload($id, countAsDownload: false);

        if ($file === null) {
            return response()->json([
                'success' => false,
                'message' => 'Laporan tidak ditemukan atau belum dipublikasikan.',
            ], 404);
        }

        return response()->file($file['path'], ['Content-Type' => $file['mime']]);
    }
}
