<?php

namespace App\DTOs\Analytical\Wirausaha;

class WirausahaPieDTO
{
    public function __construct(
        private readonly array $tingkat,
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
            'tingkat'     => $this->tingkat,
            'sebaran_kota'=> $this->sebaranKota,
        ];
    }
}