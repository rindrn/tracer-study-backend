<?php
// app/Console/Commands/RecalculateTracerResponseThresholds.php
namespace App\Console\Commands;

use App\Services\Transactional\TracerResponseCalculationService;
use Illuminate\Console\Command;

class RecalculateTracerResponseThresholds extends Command
{
    protected $signature = 'tracer:recalc-response-threshold';
    protected $description = 'Recalculate threshold tracer_response untuk semua LAM & tahun angkatan yang stale';

    public function handle(TracerResponseCalculationService $calc): int
    {
        $updated = $calc->recalculateAllStale();
        $this->info("Threshold tracer_response diperbarui untuk {$updated} kombinasi LAM+tahun.");
        return self::SUCCESS;
    }
}