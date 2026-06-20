<?php

namespace App\DTOs\Analytical\EmploymentSummary;

/**
 * EmploymentSummaryDTO
 *
 * Output untuk GET /api/dashboard/employment/summary
 *
 * {
 *   "filters": {},
 *   "cards": {
 *     "keterserapan":      { "value": 84.0, "hint": "Bekerja/usaha" },
 *     "masa_tunggu_cepat": { "value": 85.0, "hint": "Cepat terserap" },
 *     "kesesuaian":        { "value": 79.0, "hint": "Sangat erat + erat" },
 *     "wirausaha":         { "value": 11.0, "hint": "Owner/co-founder" },
 *     "avg_pendapatan": {
 *       "value": 9100000,
 *       "label": "Rp 9,1 jt",
 *       "pct_above_ump": 71.0,
 *       "hint": "71,0% ≥ 1,2× UMP"
 *     },
 *     "level_nasional": { "value": 47.0, "hint": "Sebaran perusahaan" }
 *   }
 * }
 *
 * Mapping ke FE SummaryCardItem (EmploymentPageContent.tsx):
 *   keterserapan.value         → "Keterserapan"   value (FE: "84%")
 *   masa_tunggu_cepat.value    → "Kerja ≤ 6 bln"  value (FE: "85%")
 *   kesesuaian.value           → "Kesesuaian"     value (FE: "79%")
 *   wirausaha.value            → "Wirausaha"      value (FE: "11%")
 *   avg_pendapatan.label       → "Avg Pendapatan" value (FE: "Rp 9,1 jt")
 *   avg_pendapatan.hint        → "Avg Pendapatan" hint  (FE: "71% ≥ 1,2× UMP")
 *   level_nasional.value       → "Level Nasional" value (FE: "47%")
 *
 */
class EmploymentSummaryDTO
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