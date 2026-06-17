<?php

namespace App\DTOs\Analytical\ResponseRate;

class ResponseRateDrillDownDTO
{
    public function __construct(
        private readonly array  $data,
        private readonly string $status,
        private readonly int    $page,
        private readonly int    $perPage,
        private readonly int    $totalOnPage,
        private readonly int    $totalCount,
        private readonly array  $filters,
    ) {}

    public function toArray(): array
    {
        return [
            'status'     => $this->status,
            'filters'    => $this->filters,
            'pagination' => [
                'page'          => $this->page,
                'per_page'      => $this->perPage,
                'total_on_page' => $this->totalOnPage,
                'total_count'   => $this->totalCount,
            ],
            'data' => $this->data,
        ];
    }
}