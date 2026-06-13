<?php

namespace App\DTOs\Analytical\SebaranInstansi;

class SebaranInstansiTingkatDTO
{
    public function __construct(
        private readonly array $data,
        private readonly array $groupedBar,
        private readonly array $filters,
    ) {}

    public function toArray(): array
    {
        return [
            'filters'     => $this->filters,
            'data'        => $this->data,
            'grouped_bar' => $this->groupedBar,
        ];
    }
}