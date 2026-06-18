<?php

namespace App\DTOs\Analytical\Pembiayaan;

class PembiayaanDrillDownDTO
{
    public function __construct(
        private readonly array $data,
        private readonly int   $page,
        private readonly int   $perPage,
        private readonly int   $totalOnPage,
        private readonly array $filters,
    ) {}

    public function toArray(): array
    {
        return [
            'filters'    => $this->filters,
            'pagination' => [
                'page'          => $this->page,
                'per_page'      => $this->perPage,
                'total_on_page' => $this->totalOnPage,
            ],
            'data' => $this->data,
        ];
    }
}