<?php
// app/Services/Transactional/ThresholdIndicatorService.php
namespace App\Services\Transactional;

use App\DTOs\Transactional\ThresholdIndicatorDTO;
use App\Repositories\Transactional\ThresholdIndicatorRepository;
use App\Traits\WithCache;

class ThresholdIndicatorService
{
    use WithCache;

    // Data indikator sangat jarang berubah → TTL panjang
    private const TTL = 3600; // 1 jam

    public function __construct(
        private readonly ThresholdIndicatorRepository $repo,
    ) {}

    public function list(): array
    {
        return $this->remember('threshold_indicators:all', function () {
            return $this->repo->all()
                ->map(fn($row) => ThresholdIndicatorDTO::fromRow($row)->toArray())
                ->toArray();
        }, self::TTL);
    }
}