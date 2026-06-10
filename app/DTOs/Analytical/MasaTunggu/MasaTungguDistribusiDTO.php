<?php

namespace App\DTOs\Analytical\MasaTunggu;

/**
 * MasaTungguDistribusiDTO
 *
 * Output untuk GET /api/dashboard/masa-tunggu/distribusi
 *
 * Response flat per-baris sesuai spec BE.md:
 * {
 *   "filters": {},
 *   "data": [
 *     {
 *       "nama_prodi", "jenjang", "tahun_lulus",
 *       "count_tunggu_0_3_bulan", "count_tunggu_3_6_bulan", "count_tunggu_lebih_6_bulan",
 *       "avg_masa_tunggu_bekerja", "min_masa_tunggu_bekerja", "max_masa_tunggu_bekerja"
 *     }
 *   ]
 * }
 *
 * Taruh di: app/DTOs/Analytical/MasaTunggu/MasaTungguDistribusiDTO.php
 */
class MasaTungguDistribusiDTO
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