<?php

namespace App\DTOs\Analytical\Keterserapan;

/**
 * KeterserapanDrillDownDTO
 *
 * Response untuk modal drill-down list alumni per status dan per tahun lulus.
 *
 * Taruh di: app/DTOs/Analytical/Keterserapan/KeterserapanDrillDownDTO.php
 */
class KeterserapanDrillDownDTO
{
    /**
     * @param array<array{nama:string, nim:string, nama_prodi:string, jenjang:string, tahun_lulus:string}> $data
     */
    public function __construct(
        public readonly array  $data,
        public readonly string $status,       // label status yang diklik
        public readonly int    $page,
        public readonly int    $perPage,
        public readonly int    $totalOnPage,
        public readonly array  $filters,
    ) {}

    public function toArray(): array
    {
        return [
            'status'        => $this->status,
            'filters'       => $this->filters,
            'pagination'    => [
                'page'          => $this->page,
                'per_page'      => $this->perPage,
                'total_on_page' => $this->totalOnPage,
            ],
            'data'          => $this->data,
        ];
    }
}