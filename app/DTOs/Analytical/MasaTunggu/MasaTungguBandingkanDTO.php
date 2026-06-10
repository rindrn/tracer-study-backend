<?php

namespace App\DTOs\Analytical\MasaTunggu;

/**
 * MasaTungguBandingkanDTO
 *
 * Output untuk GET /api/dashboard/masa-tunggu/bandingkan
 *
 * Response sesuai spec BE.md:
 * {
 *   "filters": {},
 *   "prodi_list": ["Teknik Informatika", ...],
 *   "data": [
 *     {
 *       "nama_prodi", "jenjang", "tahun_lulus",
 *       "count_tunggu_0_3_bulan", "count_tunggu_3_6_bulan", "count_tunggu_lebih_6_bulan",
 *       "avg_masa_tunggu_bekerja", "min_masa_tunggu_bekerja", "max_masa_tunggu_bekerja",
 *       "pct_cepat"
 *     }
 *   ]
 * }
 *
 * Taruh di: app/DTOs/Analytical/MasaTunggu/MasaTungguBandingkanDTO.php
 */
class MasaTungguBandingkanDTO
{
    public function __construct(
        private readonly array $data,
        private readonly array $prodiList,
        private readonly array $filters,
    ) {}

    public function toArray(): array
    {
        return [
            'filters'    => $this->filters,
            'prodi_list' => $this->prodiList,
            'data'       => $this->data,
        ];
    }
}