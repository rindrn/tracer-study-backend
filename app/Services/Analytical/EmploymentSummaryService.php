<?php

namespace App\Services\Analytical;

use App\DTOs\Analytical\EmploymentSummary\EmploymentSummaryDTO;
use App\Repositories\Analytical\KeterserapanRepository;
use App\Repositories\Analytical\MasaTungguRepository;
use App\Repositories\Analytical\KesesuaianRepository;
use App\Repositories\Analytical\WirausahaRepository;
use App\Repositories\Analytical\PendapatanRepository;
use App\Repositories\Analytical\SebaranInstansiRepository;
use App\Traits\WithCache;

/**
 * EmploymentSummaryService
 *
 * Inject langsung ke 6 Repository KPI yang sudah established —
 * TIDAK melalui EmploymentSummaryRepository wrapper.
 *
 * Mapping card → Repository + method:
 *   keterserapan      → KeterserapanRepository::getDistribusiStatusSnapshot()
 *   masa_tunggu_cepat → MasaTungguRepository::getBarData()
 *   kesesuaian        → KesesuaianRepository::getPieData()
 *   wirausaha         → WirausahaRepository::getBarData() + getBarDataTotal()
 *                       + WirausahaRepository::getPiePosisi()   ← untuk hint jabatan
 *   avg_pendapatan    → PendapatanRepository::getGajiPerTahun()
 *   level_nasional    → SebaranInstansiRepository::getTingkatData()
 */
class EmploymentSummaryService
{
    use WithCache;

    private const TTL = 3600;

    /**
     * Keyword "terserap" sesuai definisi IKU 2 Kemendikbud.
     * Konsisten dengan KeterserapanRepository::buildRentangFilters().
     */
    private const TERSERAP_KEYWORDS = [
        'bekerja',
        'wiraswasta',
        'wirausaha',
        'studi lanjut',
        'melanjutkan pendidikan',
    ];

    public function __construct(
        private readonly KeterserapanRepository    $keterserapanRepo,
        private readonly MasaTungguRepository      $masaTungguRepo,
        private readonly KesesuaianRepository      $kesesuaianRepo,
        private readonly WirausahaRepository       $wirausahaRepo,
        private readonly PendapatanRepository      $pendapatanRepo,
        private readonly SebaranInstansiRepository $sebaranInstansiRepo,
    ) {}

    // ──────────────────────────────────────────────────────────────
    //  SUMMARY — 6 card sekaligus
    // ──────────────────────────────────────────────────────────────

    /**
     * Response shape:
     * {
     *   "filters": { ... },
     *   "cards": {
     *     "keterserapan":      { "value": 84.0, "hint": "Bekerja / usaha / lanjut studi" },
     *     "masa_tunggu_cepat": { "value": 85.0, "hint": "Terserap ≤ 6 bulan" },
     *     "kesesuaian":        { "value": 79.0, "hint": "Sangat erat + erat" },
     *     "wirausaha": {
     *       "value": 75.0,          ← % jabatan terbesar dari 100% wirausaha
     *       "hint": "Owner",        ← label jabatan terbesar
     *       "top_jabatan": "Owner",
     *       "pct_top_jabatan": 75.0
     *     },
     *     "avg_pendapatan": {
     *       "value": 9100000,
     *       "label": "Rp 9,1 jt",
     *       "pct_above_ump": 71.0,
     *       "hint": "71,0% ≥ 1,2× UMP"
     *     },
     *     "level_nasional": { "value": 47.0, "hint": "Sebaran perusahaan nasional" }
     *   }
     * }
     */
    public function getSummary(array $params): EmploymentSummaryDTO
    {
        $key = $this->cacheKey('employment_summary:cards', $params);

        $cached = $this->remember($key, function () use ($params) {
            $j  = $params['jenjang']         ?? null;
            $ju = $params['jurusan']         ?? null;
            $np = $params['nama_prodi']      ?? null;
            $tl = $params['tahun_lulus']     ?? null;
            $ms = $params['minggu_snapshot'] ?? null;

            // ── 1. Keterserapan ──────────────────────────────────
            $keterserapanRaw = $this->keterserapanRepo->getDistribusiStatusSnapshot(
                jenjang: $j, jurusan: $ju, namaProdi: $np,
                tahunLulus: $tl, mingguSnapshot: $ms,
            );

            // ── 2. Masa Tunggu ───────────────────────────────────
            // Sumber sama dengan MasaTungguService::getBar().
            // Measure count_masa_tunggu_cepat dan count_terserap sudah
            // pre-computed di Cube.js (tanpa filter status manual di PHP).
            $masaTungguRaw = $this->masaTungguRepo->getBarData(
                jenjang: $j, jurusan: $ju, namaProdi: $np,
                tahunLulus: $tl, mingguSnapshot: $ms,
            );

            // ── 3. Kesesuaian ────────────────────────────────────
            // getPieData() sudah filter status_alumni_sk = 1 (Bekerja)
            // dan menghasilkan distribusi per DimKesesuaianBidang.label.
            $kesesuaianRaw = $this->kesesuaianRepo->getPieData(
                jenjang: $j, jurusan: $ju, namaProdi: $np,
                tahunLulus: $tl, mingguSnapshot: $ms,
            );

            // ── 4. Wirausaha ─────────────────────────────────────
            // Hanya butuh getPiePosisi() untuk card summary.
            // Sudah filter status=3 di repo → sum count = total wirausaha.
            // Value card = jabatan terbesar sebagai % dari total wirausaha.
            $wirausahaPosisiRaw = $this->wirausahaRepo->getPiePosisi(
                jenjang: $j, jurusan: $ju, namaProdi: $np,
                tahunLulus: $tl, mingguSnapshot: $ms,
            );

            // ── 5. Pendapatan ────────────────────────────────────
            // getGajiPerTahun() menghasilkan rows per tahun_lulus.
            // tahun_lulus filter diselesaikan di buildPendapatanCard().
            $pendapatanRaw = $this->pendapatanRepo->getGajiPerTahun(
                jenjang: $j, jurusan: $ju, namaProdi: $np,
                mingguSnapshot: $ms,
            );

            // ── 6. Sebaran Instansi ──────────────────────────────
            // getTingkatData() sudah filter FILTER_BEKERJA (status_alumni_sk=1).
            $tingkatRaw = $this->sebaranInstansiRepo->getTingkatData(
                jenjang: $j, jurusan: $ju, namaProdi: $np,
                tahunLulus: $tl, mingguSnapshot: $ms,
            );

            return [
                'keterserapan'      => $this->buildKeterserapanCard($keterserapanRaw),
                'masa_tunggu_cepat' => $this->buildMasaTungguCard($masaTungguRaw),
                'kesesuaian'        => $this->buildKesesuaianCard($kesesuaianRaw),
                'wirausaha'         => $this->buildWirausahaCard($wirausahaPosisiRaw),
                'avg_pendapatan'    => $this->buildPendapatanCard($pendapatanRaw, $tl),
                'level_nasional'    => $this->buildLevelNasionalCard($tingkatRaw),
            ];
        }, self::TTL, ['analytics-dashboard']);

        return new EmploymentSummaryDTO(
            cards:   $cached,
            filters: $this->activeFilters($params),
        );
    }

    // ──────────────────────────────────────────────────────────────
    //  Private — card builders
    // ──────────────────────────────────────────────────────────────

    /**
     * Card Keterserapan
     *
     * Denominator : semua alumni pada snapshot (sum seluruh status).
     * Numerator   : alumni dengan status "terserap" = Bekerja + Wiraswasta/Wirausaha
     *               + semua varian Melanjutkan Pendidikan sambil bekerja/wirausaha.
     *
     * Matching case-insensitive contains → TERSERAP_KEYWORDS.
     * Konsisten dengan filter di KeterserapanRepository::buildRentangFilters().
     */
    private function buildKeterserapanCard(\Illuminate\Support\Collection $rows): array
    {
        $total    = $rows->sum('count');
        $terserap = $rows->filter(fn($r) =>
            $this->labelMatchesAny($r['status'], ['bekerja', 'wirausaha', 'wiraswasta', 'melanjutkan'])
             )->sum('count');

        return [
            'value' => $total > 0 ? round($terserap / $total * 100, 1) : 0.0,
            'hint'  => 'Bekerja / usaha / lanjut studi',
        ];
    }

    /**
     * Card Masa Tunggu Cepat
     *
     * Ikut logic MasaTungguService::getBar() persis:
     *   pct_cepat = count_masa_tunggu_cepat / count_terserap × 100
     *
     * Measure count_masa_tunggu_cepat = alumni Bekerja/Wirausaha masa tunggu ≤ 6 bln
     *                                   (pre-computed di Cube.js, bukan filter PHP).
     * Measure count_terserap          = total alumni yang sudah bekerja/wirausaha
     *                                   (denominator — bukan total semua alumni).
     *
     * Sum di PHP karena getBarData() mengembalikan baris per prodi × tahun_lulus.
     * Dengan menjumlahkan seluruh baris, kita dapat agregat keseluruhan
     * yang sesuai dengan filter global yang sudah diteruskan ke Cube.js.
     */
    private function buildMasaTungguCard(\Illuminate\Support\Collection $rows): array
    {
        $totalAlumni = $rows->sum('count_alumni');
        $totalCepat  = $rows->sum('count_masa_tunggu_cepat');
 
        $pct = $totalAlumni > 0 ? round($totalCepat / $totalAlumni * 100, 1) : 0.0;
 
        return [
            'value' => $pct,
            'hint'  => 'Cepat terserap',
        ];
    }

    /**
     * Card Kesesuaian
     *
     * Sumber: KesesuaianRepository::getPieData()
     * Repo sudah filter status_alumni_sk = 1 (Bekerja).
     * Label dari DimKesesuaianBidang: "Sangat Erat", "Erat", "Kurang Erat", "Tidak Erat".
     *
     * Numerator  : label "Sangat Erat" + "Erat" (exact match setelah lowercase).
     * Denominator: semua alumni Bekerja yang punya data kesesuaian.
     *
     * KENAPA exact match, bukan str_contains("erat")?
     * Karena "Kurang Erat" dan "Tidak Erat" juga mengandung substring "erat" —
     * jika pakai contains maka semua label ikut terhitung.
     */
    private function buildKesesuaianCard(\Illuminate\Support\Collection $rows): array
    {
        $total = $rows->sum('count');

        $erat = $rows->filter(function ($r) {
            $lower = mb_strtolower(trim($r['label']));
            return $lower === 'sangat erat' || $lower === 'erat';
        })->sum('count');

        return [
            'value' => $total > 0 ? round($erat / $total * 100, 1) : 0.0,
            'hint'  => 'Sangat erat + erat',
        ];
    }

    /**
     * Card Wirausaha
     *
     * Denominator seluruh card ini = total alumni WIRAUSAHA (bukan total semua alumni).
     * posisiRows sudah difilter status=3 di getPiePosisi() → sum = total wirausaha.
     *
     * VALUE = jabatan terbesar sebagai % dari total wirausaha.
     *   Contoh: total wirausaha 100 orang, Owner 75 → value = 75.0
     *
     * HINT = label jabatan terbesar tersebut.
     *   Contoh: "dari total wirausaha" atau "Owner"
     *
     * Konsisten dengan WirausahaService::getPie() di mana pct per jabatan
     * dihitung sebagai count / $total, dan $total = $posisiRaw->sum('count')
     * = total wirausaha (bukan total semua alumni).
     */
    private function buildWirausahaCard(
        \Illuminate\Support\Collection $posisiRows,
    ): array {
        // Total wirausaha = sum count dari getPiePosisi() yang sudah filter status=3
        $totalWirausaha = $posisiRows->sum('count');
        $topJabatan     = $posisiRows->sortByDesc('count')->first();

        if (!$topJabatan || $totalWirausaha === 0) {
            return [
                'value'           => 0.0,
                'hint'            => '-',
                'top_jabatan'     => null,
                'pct_top_jabatan' => null,
            ];
        }

        $topLabel      = $topJabatan['label'];
        // value = % jabatan terbesar dari 100% wirausaha
        $pctTopJabatan = round($topJabatan['count'] / $totalWirausaha * 100, 1);

        return [
            'value'           => $pctTopJabatan,
            'hint'            => $topLabel,
            'top_jabatan'     => $topLabel,
            'pct_top_jabatan' => $pctTopJabatan,
        ];
    }

    /**
     * Card Avg Pendapatan
     *
     * Sumber: PendapatanRepository::getGajiPerTahun() → rows per tahun_lulus.
     *
     * Kasus A — ada filter tahun_lulus:
     *   Filter baris di PHP untuk tahun ybs, ambil weighted avg tahun tersebut.
     *   (getGajiPerTahun tidak punya param tahun_lulus karena axis X = tahun_lulus.)
     *
     * Kasus B — tanpa filter (semua tahun):
     *   Weighted average lintas tahun, bobot = total_alumni_ump
     *   (alumni yang punya data gaji + ref UMP — bukan count_alumni semua alumni).
     *
     * Konsisten dengan PendapatanService::getBar() yang pakai sumber yang sama.
     */
    private function buildPendapatanCard(
        \Illuminate\Support\Collection $rows,
        ?string $tahunLulusFilter,
    ): array {
        if ($rows->isEmpty()) {
            return ['value' => 0, 'label' => '-', 'pct_above_ump' => null, 'hint' => null];
        }

        // Kasus A: filter tahun_lulus spesifik
        if ($tahunLulusFilter !== null && $tahunLulusFilter !== '') {
            $filtered = $rows->filter(
                fn($r) => (string) $r['tahun_lulus'] === (string) $tahunLulusFilter
            );

            if ($filtered->isEmpty()) {
                return ['value' => 0, 'label' => '-', 'pct_above_ump' => null, 'hint' => null];
            }

            $totalBobot      = $filtered->sum('total_alumni_ump');
            $weightedSumGaji = $filtered->sum(fn($r) => $r['avg_gaji'] * $r['total_alumni_ump']);
            $avgGaji         = $totalBobot > 0 ? (int) round($weightedSumGaji / $totalBobot) : 0;
            $aboveUmp        = $filtered->sum('count_above_ump');
            $pctAboveUmp     = $totalBobot > 0 ? round($aboveUmp / $totalBobot * 100, 1) : null;

            return $this->formatPendapatanCard($avgGaji, $pctAboveUmp);
        }

        // Kasus B: semua tahun → weighted average
        $totalBobot      = $rows->sum('total_alumni_ump');
        $weightedSumGaji = $rows->sum(fn($r) => $r['avg_gaji'] * $r['total_alumni_ump']);
        $avgGaji         = $totalBobot > 0 ? (int) round($weightedSumGaji / $totalBobot) : 0;
        $aboveUmp        = $rows->sum('count_above_ump');
        $pctAboveUmp     = $totalBobot > 0 ? round($aboveUmp / $totalBobot * 100, 1) : null;

        return $this->formatPendapatanCard($avgGaji, $pctAboveUmp);
    }

    /**
     * Card Level Nasional
     *
     * Sumber: SebaranInstansiRepository::getTingkatData()
     * Repo sudah filter FILTER_BEKERJA (status_alumni_sk=1).
     * % alumni Bekerja yang bekerja di instansi tingkat "nasional".
     */
    private function buildLevelNasionalCard(\Illuminate\Support\Collection $rows): array
    {
        $total    = $rows->sum('count');
        $nasional = $rows
            ->filter(fn($r) => $this->labelMatchesAny($r['label_tingkat'], ['nasional']))
            ->sum('count');

        return [
            'value' => $total > 0 ? round($nasional / $total * 100, 1) : 0.0,
            'hint'  => 'Sebaran perusahaan nasional',
        ];
    }

    // ──────────────────────────────────────────────────────────────
    //  Private Helpers
    // ──────────────────────────────────────────────────────────────

    private function formatPendapatanCard(int $avgGaji, ?float $pctAboveUmp): array
    {
        return [
            'value'         => $avgGaji,
            'label'         => $avgGaji > 0 ? $this->formatRupiahJuta($avgGaji) : '-',
            'pct_above_ump' => $pctAboveUmp,
            'hint'          => $pctAboveUmp !== null
                ? number_format($pctAboveUmp, 1, ',', '.') . '% ≥ 1,2× UMP'
                : null,
        ];
    }

    private function labelMatchesAny(string $label, array $keywords): bool
    {
        $lower = mb_strtolower($label);
        foreach ($keywords as $kw) {
            if (str_contains($lower, mb_strtolower($kw))) {
                return true;
            }
        }
        return false;
    }

    private function formatRupiahJuta(int $value): string
    {
        return 'Rp ' . number_format(round($value / 1_000_000, 1), 1, ',', '.') . ' jt';
    }

    private function cacheKey(string $prefix, array $params): string
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