<?php

namespace App\DTOs\Analytical\Wirausaha;

class WirausahaPieDTO
{
    public function __construct(
        private readonly array $posisi,
        private readonly array $sebaranKota,
        private readonly int   $total,
        private readonly array $filters,
    ) {}

    public function toArray(): array
    {
        return [
            'chart_type'  => 'pie',
            'filters'     => $this->filters,
            'total'       => $this->total,
            'posisi'      => $this->posisi,
            'sebaran_kota'=> $this->sebaranKota,
        ];
    }
}