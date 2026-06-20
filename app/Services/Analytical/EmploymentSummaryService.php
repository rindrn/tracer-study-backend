<?php

namespace App\Services\Analytical;

use App\DTOs\Analytical\EmploymentSummary\EmploymentSummaryDTO;
use App\Repositories\Analytical\EmploymentSummaryRepository;

/**
 * EmploymentSummaryService
 *
 * Orkestrasi data dari EmploymentSummaryRepository untuk 6 Summary Card
 * di Employment Page (EmploymentPageContent.tsx).
 *
 * EmploymentSummaryRepository sendiri adalah coordinator yang inject
 * Repository KPI 4/5/6/7/8/12 yang sudah established — tidak ada
 * query Cube.js baru di layer ini.
 *
 *   1. Keterserapan    — pct alumni Bekerja+Wirausaha dari total
 *   2. Kerja ≤ 6 bln   — pct alumni terserap dengan masa tunggu ≤6bln
 *   3. Kesesuaian      — pct "Sangat Erat" + "Erat" dari distribusi kesesuaian
 *   4. Wirausaha       — pct alumni berstatus wirausaha dari total
 *   5. Avg Pendapatan  — rata-rata gaji + pct ≥ 1,2× UMP (aggregate semua tahun)
 *   6. Level Nasional  — pct alumni bekerja di instansi level "Nasional"
 *
 * Label kesesuaian "erat" yang dihitung: "Sangat Erat" dan "Erat"
 * (partial match case-insensitive supaya aman kalau ada typo kecil di DB).
 *
 */
class EmploymentSummaryService
{
    /** Label kesesuaian yang dianggap "erat" untuk card Kesesuaian */
    private const ERAT_KEYWORDS = ['sangat erat', 'erat'];

    public function __construct(
        private readonly EmploymentSummaryRepository $repo,
    ) {}

    // ──────────────────────────────────────────────────────────────
    //  SUMMARY — 6 card sekaligus
    // ──────────────────────────────────────────────────────────────

    /**
     * Response:
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
     */
    public function getSummary(array $params): EmploymentSummaryDTO
    {
        $j  = $params['jenjang']         ?? null;
        $ju = $params['jurusan']         ?? null;
        $np = $params['nama_prodi']      ?? null;
        $tl = $params['tahun_lulus']     ?? null;
        $ms = $params['minggu_snapshot'] ?? null;

        $keterserapanRaw = $this->repo->getKeterserapanData($j, $ju, $np, $tl, $ms);
        $masaTungguRaw   = $this->repo->getMasaTungguData($j, $ju, $np, $tl, $ms);
        $kesesuaianRaw   = $this->repo->getKesesuaianData($j, $ju, $np, $tl, $ms);
        $wirausahaRaw    = $this->repo->getWirausahaData($j, $ju, $np, $tl, $ms);
        $pendapatanRaw   = $this->repo->getPendapatanData($j, $ju, $np, $ms);
        $tingkatRaw      = $this->repo->getTingkatData($j, $ju, $np, $tl, $ms);

        $cards = [
            'keterserapan'       => $this->buildKeterserapanCard($keterserapanRaw),
            'masa_tunggu_cepat'  => $this->buildMasaTungguCard($masaTungguRaw),
            'kesesuaian'         => $this->buildKesesuaianCard($kesesuaianRaw),
            'wirausaha'          => $this->buildWirausahaCard($wirausahaRaw),
            'avg_pendapatan'     => $this->buildPendapatanCard($pendapatanRaw),
            'level_nasional'     => $this->buildLevelNasionalCard($tingkatRaw),
        ];

        return new EmploymentSummaryDTO(
            cards:   $cards,
            filters: $this->activeFilters($params),
        );
    }

    // ──────────────────────────────────────────────────────────────
    //  PRIVATE — builder per card
    // ──────────────────────────────────────────────────────────────

    /**
     * Keterserapan = (Bekerja + Wirausaha) / total × 100
     * Label status dari DimStatusAlumni.label — match partial case-insensitive.
     */
    private function buildKeterserapanCard(\Illuminate\Support\Collection $rows): array
    {
        $total       = $rows->sum('count');
        $terserap    = $rows->filter(fn($r) =>
            $this->labelMatches($r['status'], ['bekerja', 'wirausaha'])
        )->sum('count');

        $pct = $total > 0 ? round($terserap / $total * 100, 1) : 0.0;

        return [
            'value' => $pct,
            'hint'  => 'Bekerja/usaha',
        ];
    }

    /**
     * Masa tunggu cepat = count_masa_tunggu_cepat / count_terserap × 100
     * Aggregate semua baris (bisa multi-prodi × tahun dari getBarData).
     */
    private function buildMasaTungguCard(\Illuminate\Support\Collection $rows): array
    {
        $totalTerserap = $rows->sum('count_terserap');
        $totalCepat    = $rows->sum('count_masa_tunggu_cepat');

        $pct = $totalTerserap > 0 ? round($totalCepat / $totalTerserap * 100, 1) : 0.0;

        return [
            'value' => $pct,
            'hint'  => 'Cepat terserap',
        ];
    }

    /**
     * Kesesuaian = pct label "Sangat Erat" + "Erat" dari seluruh distribusi.
     * Data dari KesesuaianRepository::getPieData() yang sudah filter status=1 (Bekerja).
     */
    private function buildKesesuaianCard(\Illuminate\Support\Collection $rows): array
    {
        $total = $rows->sum('count');
        $erat  = $rows->filter(fn($r) =>
            $this->labelMatches($r['label'], self::ERAT_KEYWORDS)
        )->sum('count');

        $pct = $total > 0 ? round($erat / $total * 100, 1) : 0.0;

        return [
            'value' => $pct,
            'hint'  => 'Sangat erat + erat',
        ];
    }

    /**
     * Wirausaha = sum(count_wirausaha) / sum(count_alumni_total) × 100
     * count_wirausaha dari getBarData() (filter status=3),
     * count_alumni dari getBarDataTotal() (semua status, denominator).
     */
    private function buildWirausahaCard(array $d): array
    {
        $totalAlumni   = $d['total']->sum('count_alumni');
        $totalWirausaha = $d['wirausaha']->sum('count_wirausaha');

        $pct = $totalAlumni > 0 ? round($totalWirausaha / $totalAlumni * 100, 1) : 0.0;

        return [
            'value' => $pct,
            'hint'  => 'Owner/co-founder',
        ];
    }

    /**
     * Pendapatan = weighted avg dari avg_gaji per tahun (bobot: count_dengan_data_ump)
     * + pct_above_ump aggregate semua tahun.
     * Data dari PendapatanRepository::getGajiPerTahun().
     */
    private function buildPendapatanCard(\Illuminate\Support\Collection $rows): array
    {
        if ($rows->isEmpty()) {
            return [
                'value'          => 0,
                'label'          => '-',
                'pct_above_ump'  => null,
                'hint'           => null,
            ];
        }

        // Weighted average avg_gaji — bobot: total_alumni_ump per tahun
        $totalBobot      = $rows->sum('total_alumni_ump');
        $weightedSumGaji = $rows->sum(fn($r) => $r['avg_gaji'] * $r['total_alumni_ump']);
        $avgGaji = $totalBobot > 0 ? (int) round($weightedSumGaji / $totalBobot) : 0;

        // Pct above UMP aggregate semua tahun
        $totalUmpData  = $rows->sum('total_alumni_ump');
        $totalAboveUmp = $rows->sum('count_above_ump');
        $pctAboveUmp   = $totalUmpData > 0 ? round($totalAboveUmp / $totalUmpData * 100, 1) : null;

        return [
            'value'         => $avgGaji,
            'label'         => $this->formatRupiahJuta($avgGaji),
            'pct_above_ump' => $pctAboveUmp,
            'hint'          => $pctAboveUmp !== null
                ? number_format($pctAboveUmp, 1, ',', '.') . '% ≥ 1,2× UMP'
                : null,
        ];
    }

    /**
     * Level Nasional = count label "Nasional" / total seluruh tingkat × 100
     * Data dari SebaranInstansiRepository::getTingkatData() (sudah filter Bekerja).
     */
    private function buildLevelNasionalCard(\Illuminate\Support\Collection $rows): array
    {
        $total    = $rows->sum('count');
        $nasional = $rows->filter(fn($r) =>
            $this->labelMatches($r['label_tingkat'], ['nasional'])
        )->sum('count');

        $pct = $total > 0 ? round($nasional / $total * 100, 1) : 0.0;

        return [
            'value' => $pct,
            'hint'  => 'Sebaran perusahaan',
        ];
    }

    // ──────────────────────────────────────────────────────────────
    //  PRIVATE HELPERS
    // ──────────────────────────────────────────────────────────────

    /**
     * Case-insensitive partial match terhadap array keywords.
     * Dipakai untuk matching label status, kesesuaian, dan tingkat instansi.
     */
    private function labelMatches(string $label, array $keywords): bool
    {
        $lower = mb_strtolower($label);
        foreach ($keywords as $kw) {
            if (str_contains($lower, mb_strtolower($kw))) {
                return true;
            }
        }
        return false;
    }

    /**
     * Format rupiah ke "Rp X,X jt" (dibagi juta, 1 desimal, locale Indonesia).
     */
    private function formatRupiahJuta(int $value): string
    {
        if ($value === 0) return '-';
        $juta = $value / 1_000_000;
        return 'Rp ' . number_format(round($juta, 1), 1, ',', '.') . ' jt';
    }

    private function activeFilters(array $params): array
    {
        $keys = ['jenjang', 'jurusan', 'nama_prodi', 'tahun_lulus', 'minggu_snapshot'];

        return array_filter(
            array_intersect_key($params, array_flip($keys)),
            fn($v) => $v !== null && $v !== '' && $v !== [],
        );
    }
}