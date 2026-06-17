<?php

namespace App\DTOs\Analytical\ResponseRate;

class ResponseRatePieDTO
{
    public function __construct(
        private readonly array $data,
        private readonly int   $total,
        private readonly array $filters,
    ) {}

    public function toArray(): array
    {
        return [
            'filters' => $this->filters,
            'total'   => $this->total,
            'data'    => $this->data,
        ];
    }
}