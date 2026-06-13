<?php

namespace App\DTOs\Analytical\SebaranInstansi;

class SebaranInstansiDrillDownDTO
{
    public function __construct(
        private readonly array   $data,
        private readonly ?string $jenisInstansi,
        private readonly ?string $tingkatInstansi,
        private readonly int     $page,
        private readonly int     $perPage,
        private readonly int     $totalOnPage,
        private readonly array   $filters,
    ) {}

    public function toArray(): array
    {
        return [
            'jenis_instansi'   => $this->jenisInstansi,
            'tingkat_instansi' => $this->tingkatInstansi,
            'filters'          => $this->filters,
            'pagination'       => [
                'page'          => $this->page,
                'per_page'      => $this->perPage,
                'total_on_page' => $this->totalOnPage,
            ],
            'data' => $this->data,
        ];
    }
}