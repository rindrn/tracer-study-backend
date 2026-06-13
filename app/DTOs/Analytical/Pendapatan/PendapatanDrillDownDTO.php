<?php

namespace App\DTOs\Analytical\Pendapatan;

/**
 * PendapatanDrillDownDTO
 *
 * Response untuk modal drill-down list alumni pendapatan.
 * Kolom tambahan vs Keterserapan: perusahaan + gaji (take_home_pay).
 */
class PendapatanDrillDownDTO
{
    /**
     * @param array<array{
     *   nama: string,
     *   nim: string,
     *   nama_prodi: string,
     *   tahun_lulus: string,
     *   perusahaan: string,
     *   take_home_pay: int|null,
     *   flag_above_ump: int|null
     * }> $data
     * @param string      $segmen   'above_ump' | 'below_ump' | tahun_lulus tertentu
     */
    public function __construct(
        public readonly array  $data,
        public readonly string $segmen,
        public readonly int    $page,
        public readonly int    $perPage,
        public readonly int    $totalOnPage,
        public readonly array  $filters,
    ) {}

    public function toArray(): array
    {
        return [
            'segmen'     => $this->segmen,
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