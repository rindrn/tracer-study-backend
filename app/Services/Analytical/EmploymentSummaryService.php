<?php

namespace App\Services\Analytical;

use App\DTOs\Analytical\EmploymentSummary\EmploymentSummaryDTO;
use App\Repositories\Analytical\EmploymentSummaryRepository;
use App\Traits\WithCache;

class EmploymentSummaryService
{
    use WithCache;

    private const TTL           = 3600;
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
        $key = $this->key('employment_summary:cards', $params);

        $cached = $this->remember($key, function () use ($params) {
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

            return [
                'keterserapan'      => $this->buildKeterserapanCard($keterserapanRaw),
                'masa_tunggu_cepat' => $this->buildMasaTungguCard($masaTungguRaw),
                'kesesuaian'        => $this->buildKesesuaianCard($kesesuaianRaw),
                'wirausaha'         => $this->buildWirausahaCard($wirausahaRaw),
                'avg_pendapatan'    => $this->buildPendapatanCard($pendapatanRaw),
                'level_nasional'    => $this->buildLevelNasionalCard($tingkatRaw),
            ];
        }, self::TTL);

        return new EmploymentSummaryDTO(
            cards:   $cached,
            filters: $this->activeFilters($params),
        );
    }

    // ── Private — card builders ───────────────────────────────────

    private function buildKeterserapanCard(\Illuminate\Support\Collection $rows): array
    {
        $total    = $rows->sum('count');
        $terserap = $rows->filter(fn($r) =>
            $this->labelMatches($r['status'], ['bekerja', 'wirausaha'])
        )->sum('count');

        return [
            'value' => $total > 0 ? round($terserap / $total * 100, 1) : 0.0,
            'hint'  => 'Bekerja/usaha',
        ];
    }

    private function buildMasaTungguCard(\Illuminate\Support\Collection $rows): array
    {
        $totalTerserap = $rows->sum('count_terserap');
        $totalCepat    = $rows->sum('count_masa_tunggu_cepat');

        return [
            'value' => $totalTerserap > 0 ? round($totalCepat / $totalTerserap * 100, 1) : 0.0,
            'hint'  => 'Cepat terserap',
        ];
    }

    private function buildKesesuaianCard(\Illuminate\Support\Collection $rows): array
    {
        $total = $rows->sum('count');
        $erat  = $rows->filter(fn($r) =>
            $this->labelMatches($r['label'], self::ERAT_KEYWORDS)
        )->sum('count');

        return [
            'value' => $total > 0 ? round($erat / $total * 100, 1) : 0.0,
            'hint'  => 'Sangat erat + erat',
        ];
    }

    private function buildWirausahaCard(array $d): array
    {
        $totalAlumni    = $d['total']->sum('count_alumni');
        $totalWirausaha = $d['wirausaha']->sum('count_wirausaha');

        return [
            'value' => $totalAlumni > 0 ? round($totalWirausaha / $totalAlumni * 100, 1) : 0.0,
            'hint'  => 'Owner/co-founder',
        ];
    }

    private function buildPendapatanCard(\Illuminate\Support\Collection $rows): array
    {
        if ($rows->isEmpty()) {
            return ['value' => 0, 'label' => '-', 'pct_above_ump' => null, 'hint' => null];
        }

        $totalBobot      = $rows->sum('total_alumni_ump');
        $weightedSumGaji = $rows->sum(fn($r) => $r['avg_gaji'] * $r['total_alumni_ump']);
        $avgGaji         = $totalBobot > 0 ? (int) round($weightedSumGaji / $totalBobot) : 0;

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

    private function buildLevelNasionalCard(\Illuminate\Support\Collection $rows): array
    {
        $total    = $rows->sum('count');
        $nasional = $rows->filter(fn($r) =>
            $this->labelMatches($r['label_tingkat'], ['nasional'])
        )->sum('count');

        return [
            'value' => $total > 0 ? round($nasional / $total * 100, 1) : 0.0,
            'hint'  => 'Sebaran perusahaan',
        ];
    }

    // ── Helpers ───────────────────────────────────────────────────

    private function labelMatches(string $label, array $keywords): bool
    {
        $lower = mb_strtolower($label);
        foreach ($keywords as $kw) {
            if (str_contains($lower, mb_strtolower($kw))) return true;
        }
        return false;
    }

    private function formatRupiahJuta(int $value): string
    {
        if ($value === 0) return '-';
        return 'Rp ' . number_format(round($value / 1_000_000, 1), 1, ',', '.') . ' jt';
    }

    private function key(string $prefix, array $params): string
    {
        $relevant = array_diff_key($params, array_flip(['page', 'per_page', 'search']));
        ksort($relevant);
        return $prefix . ':' . md5(json_encode($relevant));
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