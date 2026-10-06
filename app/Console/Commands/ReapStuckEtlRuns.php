<?php

namespace App\Console\Commands;

use App\Models\Transactional\EtlRun;
use Illuminate\Console\Command;

/**
 * Tandai baris etl_runs yang nyangkut status='running' selamanya sebagai
 * gagal.
 *
 * Ditemukan lewat QA Safety (TC-SAF-11): kalau worker/proses ETL mati paksa
 * (kill -9, OOM, restart server) di tengah jalan, tidak ada apa pun yang
 * pernah mengubah status baris itu -- RunEtlJob hanya menulis 'completed'
 * atau 'failed' di jalur normal (lihat try/catch di sana), dan job timeout
 * Laravel (1800 detik) cuma berlaku kalau worker-nya sendiri masih hidup
 * untuk menangkap exception timeout itu. Baris yang nyangkut ini menyesatkan
 * siapa pun yang mengecek "apakah ETL sedang berjalan" (Log Anomali ETL,
 * halaman detail EtlRun) tanpa batas waktu.
 *
 * Jadwalkan tiap beberapa menit di routes/console.php. Ambang 60 menit
 * sengaja jauh di atas timeout job (30 menit) supaya run yang masih benar-
 * benar berjalan lama (bukan macet) tidak ikut ditandai gagal.
 */
class ReapStuckEtlRuns extends Command
{
    protected $signature = 'etl:reap-stuck-runs {--minutes=60 : Ambang umur (menit) sebelum run "running" dianggap macet}';

    protected $description = 'Tandai baris etl_runs yang macet di status running (worker mati paksa) sebagai gagal';

    public function handle(): int
    {
        $thresholdMinutes = (int) $this->option('minutes');

        $stuck = EtlRun::where('status', 'running')
            ->where('started_at', '<', now()->subMinutes($thresholdMinutes))
            ->get();

        foreach ($stuck as $run) {
            $run->update([
                'status'        => 'failed',
                'finished_at'   => now(),
                'error_message' => "Proses tidak pernah melapor selesai dalam {$thresholdMinutes} menit -- kemungkinan worker mati paksa (kill/OOM/restart server). Ditandai gagal otomatis oleh etl:reap-stuck-runs.",
            ]);

            $this->warn("ETL run #{$run->id} (mulai {$run->started_at}) ditandai gagal -- macet lebih dari {$thresholdMinutes} menit.");
        }

        if ($stuck->isEmpty()) {
            $this->info('Tidak ada ETL run yang macet.');
        }

        return self::SUCCESS;
    }
}
