<?php

namespace App\DTOs\Analytical\Pendapatan;

/**
 * PendapatanDistribusiDTO
 *
 * Response untuk grouped bar "Proporsi Lulusan Berdasar UMP".
 * Dua kelompok per tahun: count_below_ump vs count_above_ump.
 * 
 */
class PendapatanDistribusiDTO
{
    /**
     * @param array<array{
     *   tahun_lulus: string,
     *   total_alumni_ump: int,
     *   count_below_ump: int,
     *   count_above_ump: int,
     *   pct_below_ump: float,
     *   pct_above_ump: float
     * }> $rows
     * @param array<string> $availableTahun
     * @param array         $filters
     */
    public function __construct(
        public readonly array $rows,
        public readonly array $availableTahun,
        public readonly array $filters,
    ) {}

    public function toArray(): array
    {
        return [
            'chart_type'      => 'grouped_bar_ump',
            'filters'         => $this->filters,
            'available_tahun' => $this->availableTahun,
            'data'            => $this->rows,
        ];
    }
}