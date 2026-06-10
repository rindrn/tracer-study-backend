<?php

namespace App\DTOs\Analytical\Kesesuaian;

class KesesuaianPieDTO
{
    public function __construct(
        private readonly array $data,
        private readonly int   $total,
        private readonly array $filters,
    ) {}

    public function toArray(): array
    {
        return [
            'chart_type' => 'pie',
            'filters'    => $this->filters,
            'total'      => $this->total,
            'data'       => $this->data,
        ];
    }
}