<?php
// app/Listeners/RecalculateTracerResponseThreshold.php
namespace App\Listeners;

use App\Events\AlumniDataChanged;
use App\Services\Transactional\TracerResponseCalculationService;

class RecalculateTracerResponseThreshold
{
    public function __construct(
        private readonly TracerResponseCalculationService $calc,
    ) {}

    public function handle(AlumniDataChanged $event): void
    {
        $this->calc->recalculate($event->programId, $event->graduatedYear);
    }
}