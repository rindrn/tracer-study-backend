<?php

namespace App\DTOs\Analytical\MasaTunggu;

class MasaTungguDrillDownDTO
{
    public function __construct(
        private readonly array  $data,
        private readonly string $rentang,
        private readonly int    $page,
        private readonly int    $perPage,
        private readonly int    $totalOnPage,
        private readonly array  $filters,
    ) {}

    public function toArray(): array
    {
        return [
            'rentang'    => $this->rentang,
            'filters'    => $this->filters,
            'pagination' => [
                'page'         => $this->page,
                'per_page'     => $this->perPage,
                'total_on_page'=> $this->totalOnPage,
            ],
            'data' => $this->data,
        ];
    }
}