<?php

namespace App\DTOs\Analytical\Summary;

/**
 * SummaryDTO
 *
 * Output untuk GET /api/dashboard/overview/summary
 *
 * {
 *   "filters": {},
 *   "cards": {
 *     "total_kuesioner": { "value": 1692, "hint": "Dikirim" },
 *     "sudah_mengisi":   { "value": 1227, "hint": "Response masuk" },
 *     "response_rate": {
 *       "value": 72.5,
 *       "hint": "Tingkat respons",
 *       "trend_pp": 5.2,
 *       "trend_direction": "up"
 *     },
 *     "rata_rata_waktu": {
 *       "value_hours": 4.2,
 *       "label": "4,2 jam",
 *       "hint": "Pengisian",
 *       "count_with_duration": 980
 *     },
 *     "belum_mengisi": { "value": 465, "hint": "Follow-up" },
 *     "tren_5_tahun": {
 *       "label": "Naik konsisten",
 *       "direction": "up",
 *       "hint": "Naik konsisten"
 *     }
 *   }
 * }
 *
 * Mapping ke FE SummaryCardItem (OverviewPageContent.tsx):
 *   total_kuesioner.value   → "Total Kuesioner"  value
 *   sudah_mengisi.value     → "Sudah Mengisi"    value
 *   response_rate.value     → "Response Rate"    value (FE format ke "72,5%")
 *   response_rate.trend_pp  → "Response Rate"    trend ("+5,2%")
 *   rata_rata_waktu.label   → "Rata-rata Waktu"  value ("4,2 hr")
 *   belum_mengisi.value     → "Belum Mengisi"    value
 *   tren_5_tahun.label      → "Tren 5 Thn"       value ("↑ Stabil" dst, FE tambah ikon)
 *
 */
class SummaryDTO
{
    public function __construct(
        private readonly array $cards,
        private readonly array $filters,
    ) {}

    public function toArray(): array
    {
        return [
            'filters' => $this->filters,
            'cards'   => $this->cards,
        ];
    }
}