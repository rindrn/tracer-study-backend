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
 * Tujuan: hindari inkonsistensi logic (misal filter terserap, erat keyword, dll)
 * dengan cara reuse persis source of truth yang sama dengan masing-masing KPI page.
 *
 * Mapping card → Repository + method:
 *   keterserapan      → KeterserapanRepository::getDistribusiStatusSnapshot()
 *   masa_tunggu_cepat → MasaTungguRepository::getBarData()
 *   kesesuaian        → KesesuaianRepository::getPieData()   ← bukan BarData
 *   wirausaha         → WirausahaRepository::getBarData() + getBarDataTotal()
 *   avg_pendapatan    → PendapatanRepository::getGajiPerTahun()
 *   level_nasional    → SebaranInstansiRepository::getTingkatData()
 */
class EmploymentSummaryService
{
    use WithCache;

    private const TTL = 3600;

    /**
     * Keyword "terserap" sesuai definisi IKU 2 Kemendikbud:
     * Bekerja + Wirausaha/Wiraswasta + Studi Lanjut (termasuk sambil bekerja/wirausaha).
     * Harus konsisten dengan KeterserapanRepository.
     */
    private const TERSERAP_KEYWORDS = [
        'bekerja',
        'wiraswasta',
        'wirausaha',
        'studi lanjut',
        'melanjutkan pendidikan',
    ];

    /**
     * Keyword "erat" untuk kesesuaian bidang.
     * Harus konsisten dengan KesesuaianService::getPie.
     * Label dari DimKesesuaianBidang: "Sangat Erat", "Erat", "Kurang Erat", "Tidak Erat".
     */
    private const ERAT_KEYWORDS = ['sangat erat', 'erat'];

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
     *     "wirausaha":         { "value": 11.0, "hint": "Dari total alumni" },
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
            // Ambil dari repo yang sama dengan KPI Keterserapan (pie chart snapshot).
            // Method ini sudah filter by minggu_snapshot dan semua global filter.
            $keterserapanRaw = $this->keterserapanRepo->getDistribusiStatusSnapshot(
                jenjang:        $j,
                jurusan:        $ju,
                namaProdi:      $np,
                tahunLulus:     $tl,
                mingguSnapshot: $ms,
            );

            // ── 2. Masa Tunggu ───────────────────────────────────
            // Ambil dari MasaTungguRepository::getBarData() — sumber yang sama
            // dengan MasaTungguService::getBar(). Measure count_masa_tunggu_cepat
            // di Cube.js sudah pre-computed (alumni bekerja ≤ 6 bulan).
            $masaTungguRaw = $this->masaTungguRepo->getBarData(
                jenjang:        $j,
                jurusan:        $ju,
                namaProdi:      $np,
                tahunLulus:     $tl,
                mingguSnapshot: $ms,
            );

            // ── 3. Kesesuaian ────────────────────────────────────
            // Ambil dari KesesuaianRepository::getPieData() — BUKAN getBarData().
            // getPieData() sudah filter status_alumni_sk = 1 (Bekerja) dan
            // menghasilkan distribusi per label DimKesesuaianBidang.
            // Sumber yang sama dengan KesesuaianService::getPie().
            $kesesuaianRaw = $this->kesesuaianRepo->getPieData(
                jenjang:        $j,
                jurusan:        $ju,
                namaProdi:      $np,
                tahunLulus:     $tl,
                mingguSnapshot: $ms,
            );

            // ── 4. Wirausaha ─────────────────────────────────────
            // Ambil dari WirausahaRepository — sumber yang sama dengan
            // WirausahaService::getBar().
            // wirausaha = count alumni status=3, total = semua status (denominator).
            $wirausahaRaw = $this->wirausahaRepo->getBarData(
                jenjang:        $j,
                jurusan:        $ju,
                namaProdi:      $np,
                tahunLulus:     $tl,
                mingguSnapshot: $ms,
            );
            $wirausahaTotalRaw = $this->wirausahaRepo->getBarDataTotal(
                jenjang:        $j,
                jurusan:        $ju,
                namaProdi:      $np,
                tahunLulus:     $tl,
                mingguSnapshot: $ms,
            );

            // ── 5. Pendapatan ────────────────────────────────────
            // Ambil dari PendapatanRepository::getGajiPerTahun() — sumber yang sama
            // dengan PendapatanService::getBar().
            // Hasil per tahun_lulus; card menghitung weighted average lintas tahun
            // (atau nilai tunggal bila tahun_lulus difilter).
            $pendapatanRaw = $this->pendapatanRepo->getGajiPerTahun(
                jenjang:        $j,
                jurusan:        $ju,
                namaProdi:      $np,
                mingguSnapshot: $ms,
                // tahun_lulus TIDAK diteruskan — axis X adalah tahun_lulus,
                // filtering dilakukan di level card builder bila $tl tidak null.
            );

            // ── 6. Sebaran Instansi ──────────────────────────────
            // Ambil dari SebaranInstansiRepository::getTingkatData() — sumber
            // yang sama dengan SebaranInstansiService (halaman Sebaran Instansi).
            $tingkatRaw = $this->sebaranInstansiRepo->getTingkatData(
                jenjang:        $j,
                jurusan:        $ju,
                namaProdi:      $np,
                tahunLulus:     $tl,
                mingguSnapshot: $ms,
            );

            return [
                'keterserapan'      => $this->buildKeterserapanCard($keterserapanRaw),
                'masa_tunggu_cepat' => $this->buildMasaTungguCard($masaTungguRaw),
                'kesesuaian'        => $this->buildKesesuaianCard($kesesuaianRaw),
                'wirausaha'         => $this->buildWirausahaCard($wirausahaRaw, $wirausahaTotalRaw),
                'avg_pendapatan'    => $this->buildPendapatanCard($pendapatanRaw, $tl),
                'level_nasional'    => $this->buildLevelNasionalCard($tingkatRaw),
            ];
        }, self::TTL);

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
     * Denominator : total semua alumni pada snapshot tersebut.
     * Numerator   : alumni dengan status "terserap" = Bekerja + Wiraswasta/Wirausaha
     *               + Melanjutkan pendidikan (semua varian).
     *
     * Keyword matching case-insensitive menggunakan TERSERAP_KEYWORDS —
     * sama persis dengan filter yang dipakai di KeterserapanService.
     */
    private function buildKeterserapanCard(\Illuminate\Support\Collection $rows): array
    {
        $total    = $rows->sum('count');
        $terserap = $rows
            ->filter(fn($r) => $this->labelMatchesAny($r['status'], self::TERSERAP_KEYWORDS))
            ->sum('count');

        return [
            'value' => $total > 0 ? round($terserap / $total * 100, 1) : 0.0,
            'hint'  => 'Bekerja / usaha / lanjut studi',
        ];
    }

    /**
     * Card Masa Tunggu Cepat
     *
     * Sumber: MasaTungguRepository::getBarData()
     * Measure count_masa_tunggu_cepat = alumni Bekerja/Wirausaha yang
     * masa tunggu ≤ 6 bulan (pre-computed di Cube.js).
     * Measure count_terserap = total alumni Bekerja/Wirausaha (denominator).
     *
     * Logika persis sama dengan MasaTungguService::getBar() yang menghitung pct_cepat:
     *   pct_cepat = count_masa_tunggu_cepat / count_terserap × 100
     */
    private function buildMasaTungguCard(\Illuminate\Support\Collection $rows): array
    {
        $totalTerserap = $rows->sum('count_terserap');
        $totalCepat    = $rows->sum('count_masa_tunggu_cepat');

        return [
            'value' => $totalTerserap > 0 ? round($totalCepat / $totalTerserap * 100, 1) : 0.0,
            'hint'  => 'Terserap ≤ 6 bulan',
        ];
    }

    /**
     * Card Kesesuaian
     *
     * Sumber: KesesuaianRepository::getPieData()
     * Data sudah difilter status_alumni_sk = 1 (Bekerja) di level repo.
     * Label dari DimKesesuaianBidang: "Sangat Erat", "Erat", "Kurang Erat", "Tidak Erat".
     *
     * Numerator : label yang mengandung "sangat erat" ATAU "erat" (case-insensitive).
     * Catatan   : "sangat erat" mengandung "erat" — order matching tidak penting
     *             karena keduanya masuk numerator. Namun untuk kejelasan, kita
     *             cek contains saja (str_contains "erat" sudah cukup karena
     *             "Kurang Erat" dan "Tidak Erat" juga mengandung "erat").
     *             Oleh karena itu kita gunakan keyword eksplisit "sangat erat" dan
     *             exact "erat" dengan trim.
     *
     * Logika sama dengan KesesuaianService::buildKesesuaianCard di versi lama,
     * tapi sekarang sumbernya getPieData bukan getDistribusiStatusSnapshot.
     */
    private function buildKesesuaianCard(\Illuminate\Support\Collection $rows): array
    {
        $total = $rows->sum('count');

        // Filter: label == "Sangat Erat" atau label == "Erat" (case-insensitive)
        // Tidak pakai contains karena "Kurang Erat" dan "Tidak Erat" juga mengandung "erat".
        // Pakai exact match setelah lowercase untuk keamanan.
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
     * Sumber: WirausahaRepository::getBarData() + getBarDataTotal()
     * Logika sama persis dengan WirausahaService::getBar():
     *   pct = count_wirausaha / count_alumni_total × 100
     * Denominator = SEMUA alumni (bukan hanya yang bekerja).
     *
     * Hint: "Dari total alumni" — karena card ini menunjukkan
     * seberapa banyak alumni yang berwirausaha dari keseluruhan.
     */
    private function buildWirausahaCard(
        \Illuminate\Support\Collection $wirausahaRows,
        \Illuminate\Support\Collection $totalRows,
    ): array {
        $totalAlumni    = $totalRows->sum('count_alumni');
        $totalWirausaha = $wirausahaRows->sum('count_wirausaha');

        return [
            'value' => $totalAlumni > 0 ? round($totalWirausaha / $totalAlumni * 100, 1) : 0.0,
            'hint'  => 'Dari total alumni',
        ];
    }

    /**
     * Card Avg Pendapatan
     *
     * Sumber: PendapatanRepository::getGajiPerTahun()
     * Data berupa rows per tahun_lulus.
     *
     * Kasus A — filter tahun_lulus diisi (misal "2022"):
     *   getGajiPerTahun tidak punya param tahun_lulus (axis X adalah tahun),
     *   jadi kita filter koleksi di PHP untuk ambil baris tahun ybs.
     *   avg_gaji = nilai tahun tersebut (sudah dihitung Cube.js).
     *
     * Kasus B — tahun_lulus null (semua tahun):
     *   avg_gaji = weighted average lintas tahun,
     *   bobot = total_alumni_ump per tahun (bukan count_alumni —
     *   karena hanya alumni yang punya data UMP yang bisa dibandingkan).
     *
     * Logika ini konsisten dengan PendapatanService::getBar() yang juga
     * pakai getGajiPerTahun() dan menampilkan per-tahun.
     */
    private function buildPendapatanCard(
        \Illuminate\Support\Collection $rows,
        ?string $tahunLulusFilter,
    ): array {
        if ($rows->isEmpty()) {
            return [
                'value'         => 0,
                'label'         => '-',
                'pct_above_ump' => null,
                'hint'          => null,
            ];
        }

        // Kasus A: ada filter tahun_lulus → ambil baris spesifik
        if ($tahunLulusFilter !== null && $tahunLulusFilter !== '') {
            $filtered = $rows->filter(
                fn($r) => (string) $r['tahun_lulus'] === (string) $tahunLulusFilter
            );

            if ($filtered->isEmpty()) {
                return [
                    'value'         => 0,
                    'label'         => '-',
                    'pct_above_ump' => null,
                    'hint'          => null,
                ];
            }

            // Seharusnya hanya 1 baris (1 tahun), tapi sum untuk keamanan
            $totalBobot      = $filtered->sum('total_alumni_ump');
            $weightedSumGaji = $filtered->sum(fn($r) => $r['avg_gaji'] * $r['total_alumni_ump']);
            $avgGaji         = $totalBobot > 0 ? (int) round($weightedSumGaji / $totalBobot) : 0;

            $aboveUmp    = $filtered->sum('count_above_ump');
            $pctAboveUmp = $totalBobot > 0 ? round($aboveUmp / $totalBobot * 100, 1) : null;

            return $this->formatPendapatanCard($avgGaji, $pctAboveUmp);
        }

        // Kasus B: semua tahun → weighted average
        $totalBobot      = $rows->sum('total_alumni_ump');
        $weightedSumGaji = $rows->sum(fn($r) => $r['avg_gaji'] * $r['total_alumni_ump']);
        $avgGaji         = $totalBobot > 0 ? (int) round($weightedSumGaji / $totalBobot) : 0;

        $aboveUmp    = $rows->sum('count_above_ump');
        $pctAboveUmp = $totalBobot > 0 ? round($aboveUmp / $totalBobot * 100, 1) : null;

        return $this->formatPendapatanCard($avgGaji, $pctAboveUmp);
    }

    /**
     * Card Level Nasional
     *
     * Sumber: SebaranInstansiRepository::getTingkatData()
     * Repo sudah filter FILTER_BEKERJA (status_alumni_sk = 1).
     * Pct alumni yang bekerja di instansi tingkat "nasional".
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

    /**
     * Case-insensitive contains matching terhadap salah satu keyword.
     * Dipakai untuk status label (keterserapan) dan tingkat instansi.
     */
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