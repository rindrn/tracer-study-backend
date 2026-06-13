<?php

namespace App\DTOs\Analytical\Pendapatan;

/**
 * PendapatanBarDTO
 *
 * Response untuk grafik dual-axis pendapatan per tahun lulus.
 * Sumbu kiri (bar) : rata-rata gaji (Rp)
 * Sumbu kanan (line): % alumni ≥ 1,2× UMP
 *
 */
class PendapatanBarDTO
{
    /**
     * @param array<array{
     *   tahun_lulus: string,
     *   avg_gaji: float,
     *   min_gaji: float,
     *   max_gaji: float,
     *   total_alumni_ump: int,
     *   count_above_ump: int,
     *   pct_above_ump: float
     * }> $rows
     * @param array<string> $availableTahun  Untuk inisialisasi sumbu X chart
     * @param array         $filters         Filter yang sedang aktif
     */
    public function __construct(
        public readonly array $rows,
        public readonly array $availableTahun,
        public readonly array $filters,
    ) {}

    public function toArray(): array
    {
        return [
            'chart_type'      => 'dual_axis_pendapatan',
            'filters'         => $this->filters,
            'available_tahun' => $this->availableTahun,
            'data'            => $this->rows,
        ];
    }
}