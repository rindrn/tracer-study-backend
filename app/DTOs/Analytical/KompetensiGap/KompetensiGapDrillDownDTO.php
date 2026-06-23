<?php

namespace App\DTOs\Analytical\KompetensiGap;

/**
 * KompetensiGapDrillDownDTO
 *
 * Output untuk GET /api/dashboard/kompetensi/gap/drill-down
 *
 * {
 *   "filters": { "kode_field": "f9_01" },
 *   "pagination": { "page": 1, "per_page": 15, "total_on_page": 12 },
 *   "data": [
 *     {
 *       "nama": "Siti Nurhaliza",
 *       "nim": "4200003",
 *       "nama_prodi": "Teknik Informatika",
 *       "jenjang": "D4",
 *       "tahun_lulus": "2020",
 *       "skor_lulus": 3.5,
 *       "skor_dibutuhkan": 4.0
 *     }
 *   ]
 * }
 *
 */
class KompetensiGapDrillDownDTO
{
    public function __construct(
        private readonly array  $data,
        private readonly int    $page,
        private readonly int    $perPage,
        private readonly int    $totalOnPage,
        private readonly array  $filters,
    ) {}

    public function toArray(): array
    {
        return [
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