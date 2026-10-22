<?php

namespace App\Http\Controllers\Api\Transactional;

use App\Http\Controllers\Controller;
use App\Jobs\RunPddiktiImportJob;
use App\Jobs\ValidatePddiktiImportJob;
use App\Models\Transactional\PddiktiImport;
use App\Services\Transactional\PddiktiImport\PddiktiImportFiles;
use App\Services\Transactional\PddiktiImport\PddiktiImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Impor jawaban tracer study berformat PDDikti — Ketua Tracer dan Tim Tracer.
 *
 * Alurnya: unggah → validasi (job) → pratinjau → konfirmasi → impor (job).
 * FE mempoll show() untuk progress bar kedua tahap. Lihat PddiktiImportService.
 */
class PddiktiImportController extends Controller
{
    /** Jumlah galat per halaman di layar pratinjau. */
    private const ISSUES_PER_PAGE = 100;

    public function __construct(private readonly PddiktiImportService $service) {}

    public function template(): BinaryFileResponse
    {
        return response()
            ->download(PddiktiImportFiles::template(), 'templat-impor-pddikti.xlsx')
            ->deleteFileAfterSend();
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:20480'],
        ], [
            'file.mimes' => 'Berkas harus berformat .xlsx atau .xls.',
            'file.max'   => 'Ukuran berkas maksimal 20 MB.',
        ]);

        $file = $request->file('file');

        $import = PddiktiImport::create([
            'status'        => 'validating',
            'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
            'file_path'     => $file->store('pddikti-imports', 'local'),
            'uploaded_by'   => $request->user()?->id,
        ]);

        ValidatePddiktiImportJob::dispatch($import->id);

        return response()->json(['success' => true, 'data' => $this->present($import->fresh(), 1)], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $import = PddiktiImport::find($id);
        if ($import === null) {
            return $this->notFound();
        }

        return response()->json([
            'success' => true,
            'data'    => $this->present($import, max(1, (int) $request->query('issues_page', 1)), $request->query('level')),
        ]);
    }

    public function errors(int $id): BinaryFileResponse|JsonResponse
    {
        $import = PddiktiImport::find($id);
        if ($import === null) {
            return $this->notFound();
        }
        if ($import->error_rows === 0) {
            return response()->json(['success' => false, 'message' => 'Tidak ada baris galat pada impor ini.'], 422);
        }

        return response()
            ->download(PddiktiImportFiles::errors($import), "galat-impor-{$import->id}.xlsx")
            ->deleteFileAfterSend();
    }

    public function confirm(int $id): JsonResponse
    {
        $import = PddiktiImport::find($id);
        if ($import === null) {
            return $this->notFound();
        }
        if ($import->status !== 'validated') {
            return response()->json(['success' => false, 'message' => 'Impor ini tidak sedang menunggu konfirmasi.'], 409);
        }
        if ($import->valid_rows === 0) {
            return response()->json(['success' => false, 'message' => 'Tidak ada baris sah yang bisa diimpor.'], 422);
        }

        $import->update(['status' => 'importing', 'processed_rows' => 0]);
        RunPddiktiImportJob::dispatch($import->id);

        return response()->json(['success' => true, 'data' => $this->present($import->fresh(), 1)]);
    }

    /**
     * Sebelum impor dijalankan: sekadar menutup pratinjau. Sesudahnya (selesai
     * atau berhenti di tengah): menghapus seluruh data yang dibawa impor ini.
     */
    public function cancel(int $id): JsonResponse
    {
        $import = PddiktiImport::find($id);
        if ($import === null) {
            return $this->notFound();
        }

        match ($import->status) {
            'validated', 'validating' => $import->update(['status' => 'cancelled', 'finished_at' => now()]),
            'completed', 'failed'     => $this->service->rollback($import),
            default                   => null,
        };

        $import->refresh();
        if (!in_array($import->status, ['cancelled', 'rolled_back'], true)) {
            return response()->json(['success' => false, 'message' => 'Impor yang sedang berjalan tidak bisa dibatalkan. Tunggu sampai selesai.'], 409);
        }

        return response()->json(['success' => true, 'data' => $this->present($import, 1)]);
    }

    private function present(PddiktiImport $import, int $issuesPage, ?string $level = null): array
    {
        $issues = collect($import->issues ?? []);
        if (in_array($level, ['error', 'warning', 'skipped'], true)) {
            $issues = $issues->where('level', $level)->values();
        }

        return [
            'id'             => $import->id,
            'status'         => $import->status,
            'original_name'  => $import->original_name,
            'total_rows'     => $import->total_rows,
            'processed_rows' => $import->processed_rows,
            'progress'       => $import->total_rows > 0 ? min(100, (int) floor($import->processed_rows * 100 / $import->total_rows)) : 0,
            'valid_rows'     => $import->valid_rows,
            'error_rows'     => $import->error_rows,
            'skipped_rows'   => $import->skipped_rows,
            'warning_count'  => $import->warning_count,
            'imported_rows'  => $import->imported_rows,
            'cohorts'        => $import->cohorts ?? [],
            'issues'         => $issues->forPage($issuesPage, self::ISSUES_PER_PAGE)->values(),
            'issues_total'   => $issues->count(),
            'issues_page'    => $issuesPage,
            'issues_per_page' => self::ISSUES_PER_PAGE,
            'error_message'  => $import->error_message,
            'validated_at'   => $import->validated_at,
            'started_at'     => $import->started_at,
            'finished_at'    => $import->finished_at,
            'created_at'     => $import->created_at,
        ];
    }

    private function notFound(): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'Impor tidak ditemukan.'], 404);
    }
}
