<?php

namespace App\Jobs;

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
 * Tahap 2 impor PDDikti: menulis baris yang sah setelah petugas
 * mengonfirmasi pratinjau. Lihat PddiktiImportService::import().
 *
 * Tiap baris ditulis dalam transaksinya sendiri. Bila job berhenti di tengah
 * jalan, baris yang sudah masuk tetap tercatat dengan import_batch_id-nya,
 * sehingga petugas masih bisa membatalkan impor ini seutuhnya.
 */
class RunPddiktiImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 1800;

    public function __construct(private readonly int $importId) {}

    public function handle(PddiktiImportService $service): void
    {
        $import = PddiktiImport::find($this->importId);
        if ($import === null || $import->status !== 'importing') {
            return;
        }

        try {
            $service->import($import);
        } catch (Throwable $e) {
            Log::error("RunPddiktiImportJob: impor id={$this->importId} gagal: {$e->getMessage()}");
            $this->markFailed();
        }
    }

    /** Worker mati di tengah jalan — lihat ValidatePddiktiImportJob::failed(). */
    public function failed(?Throwable $e): void
    {
        $this->markFailed();
    }

    private function markFailed(): void
    {
        PddiktiImport::whereKey($this->importId)->where('status', 'importing')->update([
            'status'        => 'failed',
            'error_message' => 'Impor berhenti sebelum selesai. Baris yang sudah masuk bisa dibatalkan lewat "Batalkan impor ini".',
            'finished_at'   => now(),
        ]);
    }
}
