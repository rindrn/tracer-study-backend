<?php

namespace App\Jobs;

use App\Exceptions\BusinessException;
use App\Models\Transactional\PddiktiImport;
use App\Services\Transactional\PddiktiImport\PddiktiImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Tahap 1 impor PDDikti: menilai seluruh berkas tanpa menulis data. FE
 * mempoll pddikti_imports (GET /api/pddikti-imports/{id}) untuk progress bar
 * lalu menampilkan pratinjau galatnya. Lihat PddiktiImportService::validate().
 */
class ValidatePddiktiImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 900;

    public function __construct(private readonly int $importId) {}

    public function handle(PddiktiImportService $service): void
    {
        $import = PddiktiImport::find($this->importId);
        if ($import === null || $import->status !== 'validating') {
            return;
        }

        try {
            $service->validate($import);
        } catch (Throwable $e) {
            // BusinessException membawa pesan untuk petugas (mis. kolom wajib
            // tidak ada); selain itu kesalahan sistem yang tidak perlu dibaca
            // mentah-mentah di layar.
            $message = $e instanceof BusinessException
                ? $e->getMessage()
                : 'Berkas tidak dapat dibaca. Pastikan berkasnya .xlsx atau .xls yang tidak rusak.';

            Log::error("ValidatePddiktiImportJob: impor id={$this->importId} gagal: {$e->getMessage()}");
            $import->update(['status' => 'failed', 'error_message' => $message, 'finished_at' => now()]);
        }
    }

    /**
     * Worker mati di tengah jalan (kehabisan memori, di-kill) tidak sempat
     * masuk catch di atas. Laravel memanggil ini saat job itu akhirnya
     * dinyatakan gagal; tanpanya baris impor tertahan di 'validating' dan FE
     * menampilkan spinner selamanya.
     */
    public function failed(?Throwable $e): void
    {
        PddiktiImport::whereKey($this->importId)->where('status', 'validating')->update([
            'status'        => 'failed',
            'error_message' => 'Pemeriksaan berkas terhenti. Coba unggah ulang; bila berulang, hubungi admin sistem.',
            'finished_at'   => now(),
        ]);
    }
}
