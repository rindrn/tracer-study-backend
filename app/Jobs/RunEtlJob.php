<?php

namespace App\Jobs;

use App\Models\Transactional\EtlRun;
use App\Services\ETL\EtlOrchestratorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Menjalankan EtlOrchestratorService di background (queue worker), dipicu
 * otomatis setelah admin mengubah question_semantic_mapping (Langkah 1) --
 * lihat QuestionSemanticMappingService::store()/deactivate(). Statusnya
 * ditulis ke tracer_oltp.etl_runs supaya FE bisa poll GET /api/etl-runs/{id}
 * dan menampilkan loading UI, bukan diam tanpa info seperti sebelumnya.
 *
 * force:true SENGAJA dipakai (bukan default incremental) -- perubahan
 * mapping perlu menata ULANG jawaban yang SUDAH ADA sejak dulu, bukan cuma
 * response baru sejak snapshot terakhir. Lihat EtlOrchestratorService::run()
 * dan OltpExtractRepository::getSubmittedResponsesSince() untuk kenapa
 * $since=null berarti "semua response submitted", bukan hanya yang baru.
 */
class RunEtlJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 1800;

    public function __construct(private readonly int $etlRunId) {}

    public function handle(EtlOrchestratorService $orchestrator): void
    {
        $run = EtlRun::find($this->etlRunId);
        if ($run === null) {
            Log::warning("RunEtlJob: etl_runs id={$this->etlRunId} tidak ditemukan, dilewati.");
            return;
        }

        $run->update(['status' => 'running', 'started_at' => now()]);

        try {
            $summary = $orchestrator->run(force: true);

            $run->update([
                'status'      => 'completed',
                'id_waktu'    => $summary->idWaktu,
                'summary'     => $summary->toArray(),
                'finished_at' => now(),
            ]);

            // Cache dashboard sudah di-flush di dalam EtlOrchestratorService::run()
            // itu sendiri -- berlaku untuk jalur ini MAUPUN jalur CLI etl:run,
            // supaya tidak ada lagi jalur trigger ETL yang lupa membersihkannya.
        } catch (Throwable $e) {
            Log::error("RunEtlJob gagal (etl_runs id={$this->etlRunId}): {$e->getMessage()}", ['exception' => $e]);

            $run->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
                'finished_at'   => now(),
            ]);
        }
    }
}
