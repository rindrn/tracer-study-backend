<?php

namespace App\DTOs\Analytical\MasaTunggu;

/**
 * MasaTungguBarDTO
 *
 * Output untuk GET /api/dashboard/masa-tunggu/bar
 *
 * Response:
 * {
 *   "filters": {},
 *   "data": [
 *     {
 *       "nama_prodi", "jenjang", "jurusan", "tahun_lulus",
 *       "count_alumni", "count_terserap", "count_masa_tunggu_cepat",
 *       "pct_cepat", "avg_masa_tunggu_bekerja"
 *     }
 *   ]
 * }
 *
 * Taruh di: app/DTOs/Analytical/MasaTunggu/MasaTungguBarDTO.php
 */
class MasaTungguBarDTO
{
    public function __construct(
        private readonly array $data,
        private readonly array $filters,
    ) {}

    public function toArray(): array
    {
        return [
            'filters' => $this->filters,
            'data'    => $this->data,
        ];
    }
}