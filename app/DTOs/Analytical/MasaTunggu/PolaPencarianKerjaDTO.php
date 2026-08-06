<?php

namespace App\DTOs\Analytical\MasaTunggu;

class PolaPencarianKerjaDTO
{
    public function __construct(
        private readonly array $data,
        private readonly array $filters,
    ) {}

    public function toArray(): array
    {
        return [
            'filters' => $this->filters,
            'data'    => $this->data,
        ];
    }
}
