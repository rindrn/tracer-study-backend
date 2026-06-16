<?php

namespace App\Console\Commands;

use App\Services\ETL\EtlOrchestratorService;
use Illuminate\Console\Command;

/**
 * Entry point ETL. Jalankan via: php artisan etl:run
 *
 * Untuk TESTING (per beberapa menit), daftarkan di routes/console.php:
 *   Schedule::command('etl:run')->everyFiveMinutes();
 *
 * Untuk PRODUCTION (mingguan):
 *   Schedule::command('etl:run')->weeklyOn(1, '01:00');
 */
class RunEtlSnapshot extends Command
{
    protected $signature = 'etl:run {--force : Jalankan walau belum ada response baru sejak snapshot terakhir}';

    protected $description = 'Jalankan ETL snapshot dari tracer_oltp ke OLAP (schema public)';

    public function handle(EtlOrchestratorService $orchestrator): int
    {
        $this->info('=== SmartTracer ETL Snapshot dimulai ===');
        $startedAt = now();

        $result = $orchestrator->run(force: (bool) $this->option('force'));

        $this->table(
            ['Tahap', 'Diproses', 'Insert', 'Skip'],
            $result->toTableRows()
        );

        $this->info(sprintf(
            '=== ETL selesai dalam %.2f detik. id_waktu snapshot: %d ===',
            now()->diffInSeconds($startedAt, true),
            $result->idWaktu
        ));

        return self::SUCCESS;
    }
}