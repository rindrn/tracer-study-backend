<?php
namespace App\Services\Transactional;

use App\DTOs\Transactional\ThresholdIndicatorDTO;
use App\Repositories\Transactional\ThresholdIndicatorRepository;

class ThresholdIndicatorService
{
    public function __construct(
        private readonly ThresholdIndicatorRepository $repo,
    ) {}

    public function list(): array
    {
        return $this->repo->all()
            ->map(fn($row) => ThresholdIndicatorDTO::fromRow($row)->toArray())
            ->toArray();
    }
}