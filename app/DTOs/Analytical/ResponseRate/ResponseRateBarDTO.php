<?php

namespace App\DTOs\Analytical\ResponseRate;

class ResponseRateBarDTO
{
    public function __construct(
        private readonly array  $data,
        private readonly string $sort,
        private readonly array  $filters,
    ) {}

    public function toArray(): array
    {
        return [
            'filters' => $this->filters,
            'sort'    => $this->sort,
            'data'    => $this->data,
        ];
    }
}