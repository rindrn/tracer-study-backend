<?php

namespace App\DTOs\Analytical\Keterserapan;

/**
 * KeterserapanPieDTO
 *
 * Response untuk grafik pie distribusi status alumni snapshot terkini.
 *
 * Taruh di: app/DTOs/Analytical/Keterserapan/KeterserapanPieDTO.php
 */
class KeterserapanPieDTO
{
    /**
     * @param array<array{status:string, count:int, pct:float}> $slices
     * @param int    $total    Total alumni snapshot ini
     * @param array  $filters  Filter aktif
     */
    public function __construct(
        public readonly array $slices,
        public readonly int   $total,
        public readonly array $filters,
    ) {}

    public function toArray(): array
    {
        return [
            'chart_type' => 'pie',
            'filters'    => $this->filters,
            'total'      => $this->total,
            'data'       => $this->slices,
        ];
    }
}