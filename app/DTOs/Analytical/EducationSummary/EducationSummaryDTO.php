<?php

namespace App\DTOs\Analytical\EducationSummary;

/**
 * EducationSummaryDTO
 *
 * Output untuk GET /api/dashboard/education/summary
 *
 * {
 *   "filters": {},
 *   "cards": {
 *     "skor_kompetensi":  { "label": "Berpikir Kritis", "value": 4.6, "hint": "Skor 4,6" },
 *     "gap_terbesar":     { "label": "Bahasa Inggris", "gap": -1.1, "hint": "-1,1 poin" },
 *     "metode_terbaik":   { "label": "Magang/PKL", "skor": 4.5, "hint": "Skor 4,5" },
 *     "avg_persepsi":     { "value": 4.0, "hint": "Semua metode" },
 *     "mandiri_keluarga": { "pct": 58.0, "hint": "Sumber utama" },
 *     "beasiswa":         { "pct": 36.0, "hint": "Pem. + Swasta" }
 *   }
 * }
 *
 * Mapping ke FE SummaryCardItem (EducationPageContent.tsx):
 *   skor_kompetensi.label   → "Skor Kompetensi"   value (FE: "Berpikir Kritis")
 *   skor_kompetensi.hint    → "Skor Kompetensi"   hint  (FE: "Skor 4,6")
 *   gap_terbesar.label      → "Gap Terbesar"      value (FE: "B. Inggris")
 *   gap_terbesar.hint       → "Gap Terbesar"      hint  (FE: "-1,1 poin")
 *   metode_terbaik.label    → "Metode Terbaik"    value (FE: "Magang")
 *   metode_terbaik.hint     → "Metode Terbaik"    hint  (FE: "Skor 4,5")
 *   avg_persepsi.value      → "Avg Persepsi"      value (FE: "4,0")
 *   mandiri_keluarga.pct    → "Mandiri/Keluarga"  value (FE: "58%")
 *   beasiswa.pct            → "Beasiswa"          value (FE: "36%")
 *
 */
class EducationSummaryDTO
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