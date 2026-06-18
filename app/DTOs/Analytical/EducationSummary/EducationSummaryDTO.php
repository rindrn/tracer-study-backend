<?php

namespace App\DTOs\Analytical\EducationSummary;

class EducationSummaryDTO
{
    public function __construct(
        private readonly array $cards,
        private readonly array $filters,
    ) {}

    public function toArray(): array
    {
        return [
            'filters' => $this->filters,
            'cards'   => $this->cards,
        ];
    }
}