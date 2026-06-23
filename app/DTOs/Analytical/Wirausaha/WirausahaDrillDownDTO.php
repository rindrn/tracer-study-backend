<?php

namespace App\DTOs\Analytical\Wirausaha;

class WirausahaDrillDownDTO
{
    public function __construct(
        private readonly array   $data,
        private readonly ?string $tingkat,  // null = semua tingkat (tidak difilter)
        private readonly int     $page,
        private readonly int     $perPage,
        private readonly int     $totalOnPage,
        private readonly array   $filters,
    ) {}

    public function toArray(): array
    {
        return [
            'tingkat'    => $this->tingkat, 
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