<?php

namespace App\Services\Analytical;

use App\DTOs\Analytical\EducationSummary\EducationSummaryDTO;
use App\Repositories\Analytical\KompetensiGapRepository;
use App\Repositories\Analytical\MetodePembelajaranRepository;
use App\Repositories\Analytical\PembiayaanRepository;

/**
 * EducationSummaryService
 *
 * Orkestrasi data untuk 6 Summary Card di Education Page
 * (EducationPageContent.tsx). TIDAK punya Repository sendiri — langsung
 * inject Repository KPI 9/10/11 yang SUDAH ADA dan reuse method yang
 * sudah established, supaya tidak ada duplikasi query Cube.js:
 *
 *   - KompetensiRepository::getGapData()   (KPI 9)  → card 1 & 2
 *   - MetodeRepository::getMetodeData()    (KPI 10) → card 3 & 4
 *   - PembiayaanRepository::getPieData()   (KPI 11) → card 5 & 6
 *
 * Keuntungan: kalau filter/measure Cube di KPI 9/10/11 berubah, Summary
 * ini otomatis ikut konsisten — tidak perlu update 2 tempat.
 *
 * ⚠️ REVISI: label gap_terbesar sekarang diambil dari dimension BARU
 * 'DimIndikatorEvaluasi.grup_gap' (bukan dari label_pertanyaan) — sudah
 * ditambahkan di Cube schema dan KompetensiRepository::getGapData() oleh
 * kamu. Field 'label' di hasil getGapData() tetap dipakai sebagai fallback
 * kalau grup_gap kosong/null untuk baris tertentu.
 *
 *   1. Skor Kompetensi  — AVG semua skor_lulus (Kompetensi_A) di seluruh indikator
 *   2. Gap Terbesar     — indikator dengan gap (B - A) TERBESAR (paling perlu perbaikan)
 *   3. Metode Terbaik   — metode pembelajaran dengan avg_skor TERTINGGI
 *   4. Avg Persepsi     — AVG avg_skor di SEMUA metode pembelajaran
 *   5. Mandiri/Keluarga — pct sumber biaya "mandiri/keluarga sendiri"
 *   6. Beasiswa         — pct gabungan SEMUA jenis beasiswa (pemerintah + swasta/institusi)
 *
 * ⚠️ KOREKSI LABEL PENTING:
 * KPI 11 (Pembiayaan) yang sudah kita buat sebelumnya pakai label "Beasiswa",
 * "Orang Tua", "Biaya Sendiri" (3 kategori, dari contoh response BE.md KPI 11).
 * TAPI FE EducationPageContent.tsx menyebut card "Mandiri/Keluarga" (58%) dan
 * "Beasiswa" (36% = Pem.+Swasta) — yang justru match dengan FE Kpi11FundingSourceChart
 * versi grouped bar (Mandiri/Keluarga, Beasiswa Pemerintah, Beasiswa Institusi/Swasta,
 * Lainnya — 4 kategori).
 *
 * Constant MATCHER di bawah ini coba akomodasi KEDUA kemungkinan penamaan
 * label di database (3-kategori ATAU 4-kategori) dengan partial matching
 * case-insensitive. CEK ULANG label asli di DimAlumni.label_sumber_biaya_dipolban
 * dan SESUAIKAN constant ini kalau hasilnya 0% / tidak terdeteksi.
 *
 * Taruh di: app/Services/Analytical/EducationSummaryService.php
 */
class EducationSummaryService
{
    /**
     * Kata kunci (lowercase, partial match) untuk mengenali sumber biaya
     * "mandiri/keluarga" dari label yang ada di database — apapun istilah
     * persisnya (mandiri, keluarga, orang tua, biaya sendiri, dst).
     */
    private const MANDIRI_KEYWORDS = ['mandiri', 'keluarga', 'orang tua', 'biaya sendiri', 'sendiri'];

    /**
     * Kata kunci untuk mengenali SEMUA jenis beasiswa (pemerintah, swasta,
     * institusi) — digabung jadi satu angka "Beasiswa" di card.
     */
    private const BEASISWA_KEYWORDS = ['beasiswa'];

    public function __construct(
        private readonly KompetensiGapRepository $kompetensiRepo,
        private readonly MetodePembelajaranRepository      $metodeRepo,
        private readonly PembiayaanRepository  $pembiayaanRepo,
    ) {}

    /**
     * Response:
     * {
     *   "filters": {},
     *   "cards": {
     *     "skor_kompetensi": { "value": 4.1, "hint": "Avg Likert" },
     *     "gap_terbesar": {
     *       "label": "Bahasa Inggris",
     *       "gap": -1.1,
     *       "hint": "-1,1 poin"
     *     },
     *     "metode_terbaik": {
     *       "label": "Magang/PKL",
     *       "skor": 4.5,
     *       "hint": "Skor 4,5"
     *     },
     *     "avg_persepsi": { "value": 4.0, "hint": "Semua metode" },
     *     "mandiri_keluarga": { "pct": 58.0, "hint": "Sumber utama" },
     *     "beasiswa": { "pct": 36.0, "hint": "Pem. + Swasta" }
     *   }
     * }
     */
    public function getSummary(array $params): EducationSummaryDTO
    {
        // KPI 9 — reuse KompetensiRepository::getGapData() (method yang
        // sama dipakai oleh KompetensiService untuk endpoint /kompetensi/gap)
        $kompetensiRaw = $this->kompetensiRepo->getGapData(
            jenjang:        $params['jenjang']         ?? null,
            jurusan:        $params['jurusan']         ?? null,
            namaProdi:      $params['nama_prodi']      ?? null,
            tahunLulus:     $params['tahun_lulus']     ?? null,
            mingguSnapshot: $params['minggu_snapshot'] ?? null,
        );

        // KPI 10 — reuse MetodeRepository::getMetodeData() (method yang
        // sama dipakai oleh MetodeService untuk endpoint /kompetensi/metode)
        $metodeRaw = $this->metodeRepo->getMetodeData(
            jenjang:        $params['jenjang']         ?? null,
            jurusan:        $params['jurusan']         ?? null,
            namaProdi:      $params['nama_prodi']      ?? null,
            tahunLulus:     $params['tahun_lulus']     ?? null,
            mingguSnapshot: $params['minggu_snapshot'] ?? null,
        );

        // KPI 11 — reuse PembiayaanRepository::getPieData() (method yang
        // sama dipakai oleh PembiayaanService untuk endpoint /pembiayaan/pie)
        $pembiayaanRaw = $this->pembiayaanRepo->getPieData(
            jenjang:        $params['jenjang']         ?? null,
            jurusan:        $params['jurusan']         ?? null,
            namaProdi:      $params['nama_prodi']      ?? null,
            tahunLulus:     $params['tahun_lulus']     ?? null,
            mingguSnapshot: $params['minggu_snapshot'] ?? null,
        );

        $kompetensiCards = $this->buildKompetensiCards($kompetensiRaw);
        $metodeCards     = $this->buildMetodeCards($metodeRaw);
        $pembiayaanCards = $this->buildPembiayaanCards($pembiayaanRaw);

        $cards = array_merge($kompetensiCards, $metodeCards, $pembiayaanCards);

        return new EducationSummaryDTO(
            cards:   $cards,
            filters: $this->activeFilters($params),
        );
    }

    // ──────────────────────────────────────────────────────────────
    //  PRIVATE — KOMPETENSI (card 1 & 2)
    // ──────────────────────────────────────────────────────────────

    /**
     * Join Kompetensi_A + Kompetensi_B per kode_field (logic sama dengan
     * KompetensiService::joinKategori()), lalu derive 2 card:
     *   - skor_kompetensi = AVG semua skor_lulus
     *   - gap_terbesar     = indikator dengan gap MAX (paling butuh perbaikan)
     *
     * Label untuk gap_terbesar diambil dari 'grup_gap' (DimIndikatorEvaluasi.grup_gap)
     * — BUKAN dari 'label' (DimIndikatorEvaluasi.label_pertanyaan). grup_gap adalah
     * dimension baru yang sengaja dibuat untuk pengelompokan gap yang lebih ringkas/
     * pas untuk ditampilkan di card (misal "Bahasa Inggris" alih-alih teks pertanyaan
     * lengkap). Fallback ke 'label' kalau grup_gap kosong, supaya tidak pecah kalau
     * ada baris yang belum terisi grup_gap-nya.
     */
    private function buildKompetensiCards(\Illuminate\Support\Collection $raw): array
    {
        // Ketika filter aktif (jenjang/prodi/tahun), Cube mengembalikan baris
        // per kombinasi indikator × filter-dimension. Aggregate dulu ke level
        // indikator × kategori sebelum join A+B, supaya weighted average benar.
        $aggregated = $raw
            ->groupBy(fn($r) => $r['kategori'] . '||' . $r['grup_gap'])
            ->map(function ($rows) {
                $first      = $rows->first();
                $totalCount = $rows->sum('count');
                $weightedAvg = $totalCount > 0
                    ? $rows->sum(fn($r) => $r['avg_skor'] * $r['count']) / $totalCount
                    : 0.0;

                return [
                    'grup_gap' => $first['grup_gap'],
                    'label'    => $first['label'],
                    'kategori' => $first['kategori'],
                    'avg_skor' => $weightedAvg,
                    'count'    => $totalCount,
                ];
            })
            ->values();

        // Join Kompetensi_A + Kompetensi_B per grup_gap
        $byGrupGap = $aggregated->groupBy('grup_gap');

        $joined = $byGrupGap->map(function ($items) {
            $rowA = $items->firstWhere('kategori', 'Kompetensi_A');
            $rowB = $items->firstWhere('kategori', 'Kompetensi_B');

            $grupGap = $rowA['grup_gap'] ?? $rowB['grup_gap'] ?? '';
            $label   = ($grupGap !== null && $grupGap !== '')
                ? $grupGap
                : ($rowA['label'] ?? $rowB['label'] ?? '');

            $skorLulus      = isset($rowA) ? $rowA['avg_skor'] : null;
            $skorDibutuhkan = isset($rowB) ? $rowB['avg_skor'] : null;
            $gap            = (isset($rowA) && isset($rowB))
                ? round($skorDibutuhkan - $skorLulus, 2)
                : null;
            $count          = isset($rowA) ? ($rowA['count'] ?? 0) : 0;

            return [
                'label'      => $label,
                'skor_lulus' => $skorLulus,
                'gap'        => $gap,
                'count'      => $count,
            ];
        })->filter(fn($r) => $r['skor_lulus'] !== null);

        if ($joined->isEmpty()) {
            return [
                'skor_kompetensi' => ['value' => 0.0, 'hint' => 'Avg Likert'],
                'gap_terbesar'    => ['label' => '-', 'gap' => 0.0, 'hint' => '-'],
            ];
        }

        // Weighted average skor kompetensi (Kompetensi_A saja)
        $totalCount        = $joined->sum('count');
        $weightedSumSkor   = $joined->sum(fn($r) => $r['skor_lulus'] * $r['count']);
        $avgSkorKompetensi = $totalCount > 0
            ? round($weightedSumSkor / $totalCount, 2)
            : 0.0;

        // Gap terbesar = gap paling POSITIF (kompetensi paling kurang vs kebutuhan industri)
        $maxGapRow = $joined->filter(fn($r) => $r['gap'] !== null)->sortByDesc('gap')->first();

        $gapValue = $maxGapRow['gap'] ?? 0.0;
        $gapHint  = $this->formatSignedDecimal($gapValue) . ' poin';

        return [
            'skor_kompetensi' => [
                'value' => $avgSkorKompetensi,
                'hint'  => 'Avg Likert',
            ],
            'gap_terbesar' => [
                'label' => $maxGapRow['label'] ?? '-',
                'gap'   => $gapValue,
                'hint'  => $gapHint,
            ],
        ];
    }

    // ──────────────────────────────────────────────────────────────
    //  PRIVATE — METODE PEMBELAJARAN (card 3 & 4)
    // ──────────────────────────────────────────────────────────────

    /**
     * Derive 2 card dari semua metode pembelajaran:
     *   - metode_terbaik = metode dengan avg_skor TERTINGGI
     *   - avg_persepsi   = AVG avg_skor SEMUA metode
     */
    private function buildMetodeCards(\Illuminate\Support\Collection $raw): array
    {
        if ($raw->isEmpty()) {
            return [
                'metode_terbaik' => ['label' => '-', 'skor' => 0.0, 'hint' => '-'],
                'avg_persepsi'   => ['value' => 0.0, 'hint' => 'Semua metode'],
            ];
        }

        $best = $raw->sortByDesc('avg_skor')->first();
        $avg  = round($raw->avg('avg_skor'), 1);

        return [
            'metode_terbaik' => [
                'label' => $best['label'],
                'skor'  => round($best['avg_skor'], 1),
                'hint'  => 'Skor ' . $this->formatDecimal(round($best['avg_skor'], 1)),
            ],
            'avg_persepsi' => [
                'value' => $avg,
                'hint'  => 'Semua metode',
            ],
        ];
    }

    // ──────────────────────────────────────────────────────────────
    //  PRIVATE — PEMBIAYAAN (card 5 & 6)
    // ──────────────────────────────────────────────────────────────

    /**
     * Derive 2 card dari distribusi sumber biaya:
     *   - mandiri_keluarga = pct sumber yang match MANDIRI_KEYWORDS
     *   - beasiswa         = pct GABUNGAN semua sumber yang match BEASISWA_KEYWORDS
     *
     * ⚠️ Pakai partial keyword match (case-insensitive) supaya tetap jalan
     * baik label di DB pakai istilah "Orang Tua"/"Biaya Sendiri" (versi 3-kategori
     * KPI 11 lama) ATAU "Mandiri/Keluarga" (versi FE 4-kategori baru).
     */
    private function buildPembiayaanCards(\Illuminate\Support\Collection $raw): array
    {
        $total = $raw->sum('count');

        if ($total === 0) {
            return [
                'mandiri_keluarga' => ['pct' => 0.0, 'hint' => 'Sumber utama'],
                'beasiswa'         => ['pct' => 0.0, 'hint' => 'Pem. + Swasta'],
            ];
        }

        $mandiriCount = $raw
            ->filter(fn($r) => $this->matchesKeywords($r['sumber_biaya'], self::MANDIRI_KEYWORDS))
            ->sum('count');

        $beasiswaCount = $raw
            ->filter(fn($r) => $this->matchesKeywords($r['sumber_biaya'], self::BEASISWA_KEYWORDS))
            ->sum('count');

        $mandiriPct  = round($mandiriCount / $total * 100, 1);
        $beasiswaPct = round($beasiswaCount / $total * 100, 1);

        return [
            'mandiri_keluarga' => [
                'pct'  => $mandiriPct,
                'hint' => 'Sumber utama',
            ],
            'beasiswa' => [
                'pct'  => $beasiswaPct,
                'hint' => 'Pem. + Swasta',
            ],
        ];
    }

    /**
     * Cek apakah $label mengandung salah satu dari $keywords (case-insensitive,
     * partial match).
     */
    private function matchesKeywords(string $label, array $keywords): bool
    {
        $labelLower = mb_strtolower($label);

        foreach ($keywords as $keyword) {
            if (str_contains($labelLower, $keyword)) {
                return true;
            }
        }

        return false;
    }

    // ──────────────────────────────────────────────────────────────
    //  PRIVATE — FORMATTING & FILTERS
    // ──────────────────────────────────────────────────────────────

    private function formatDecimal(float $value): string
    {
        return number_format($value, 1, ',', '.');
    }

    private function formatSignedDecimal(float $value): string
    {
        $sign = $value > 0 ? '+' : '';
        return $sign . $this->formatDecimal($value);
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