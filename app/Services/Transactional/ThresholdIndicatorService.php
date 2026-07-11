<?php
// app/Services/Transactional/ThresholdIndicatorService.php
namespace App\Services\Transactional;

use App\DTOs\Transactional\ThresholdIndicatorDTO;
use App\Exceptions\BusinessException; 
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

    public function update(int $id, array $data): array
    {
        $existing = $this->repo->findById($id);
        if (! $existing) {
            throw new BusinessException("Threshold Indicator ID {$id} tidak ditemukan.", 404);
        }

        $row = $this->repo->update($id, $data);

        $this->forget('threshold_indicators:all', "threshold_indicators:meta:{$existing->key}");
        $this->forgetTag('thresholds', 'lams');

        return ThresholdIndicatorDTO::fromRow($row)->toArray();
    }
}