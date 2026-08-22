<?php

namespace App\Services\Analytical;

use App\DTOs\Analytical\EducationSummary\EducationSummaryDTO;
use App\Repositories\Analytical\KompetensiGapRepository;
use App\Repositories\Analytical\MetodePembelajaranRepository;
use App\Repositories\Analytical\PembiayaanRepository;
use App\Traits\WithCache;

class EducationSummaryService
{
    use WithCache;

    private const TTL = 3600;

    private const MANDIRI_KEYWORDS  = ['mandiri', 'keluarga', 'orang tua', 'biaya sendiri', 'sendiri'];
    private const BEASISWA_KEYWORDS = ['beasiswa'];

    public function __construct(
        private readonly KompetensiGapRepository      $kompetensiRepo,
        private readonly MetodePembelajaranRepository $metodeRepo,
        private readonly PembiayaanRepository         $pembiayaanRepo,
    ) {}

    public function getSummary(array $params): EducationSummaryDTO
    {
        $key = $this->key('education_summary:cards', $params);

        $cached = $this->remember($key, function () use ($params) {
            $kompetensiRaw = $this->kompetensiRepo->getGapData(
                jenjang:        $params['jenjang']         ?? null,
                jurusan:        $params['jurusan']         ?? null,
                idProdiIn:      $params['id_prodi_in']     ?? null,
                namaProdi:      $params['nama_prodi']      ?? null,
                tahunLulus:     $params['tahun_lulus']     ?? null,
                mingguSnapshot: $params['minggu_snapshot'] ?? null,
            );

            $metodeRaw = $this->metodeRepo->getMetodeData(
                jenjang:        $params['jenjang']         ?? null,
                jurusan:        $params['jurusan']         ?? null,
                idProdiIn:      $params['id_prodi_in']     ?? null,
                namaProdi:      $params['nama_prodi']      ?? null,
                tahunLulus:     $params['tahun_lulus']     ?? null,
                mingguSnapshot: $params['minggu_snapshot'] ?? null,
            );

            $pembiayaanRaw = $this->pembiayaanRepo->getPieData(
                jenjang:        $params['jenjang']         ?? null,
                jurusan:        $params['jurusan']         ?? null,
                idProdiIn:      $params['id_prodi_in']     ?? null,
                namaProdi:      $params['nama_prodi']      ?? null,
                tahunLulus:     $params['tahun_lulus']     ?? null,
                mingguSnapshot: $params['minggu_snapshot'] ?? null,
            );

            return array_merge(
                $this->buildKompetensiCards($kompetensiRaw),
                $this->buildMetodeCards($metodeRaw),
                $this->buildPembiayaanCards($pembiayaanRaw),
            );
        }, self::TTL, ['analytics-dashboard']);

        return new EducationSummaryDTO(
            cards:   $cached,
            filters: $this->activeFilters($params),
        );
    }

    // ── Private — Kompetensi (card 1 & 2) ────────────────────────

    private function buildKompetensiCards(\Illuminate\Support\Collection $raw): array
    {
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

        $joined = $aggregated->groupBy('grup_gap')->map(function ($items) {
            $rowA    = $items->firstWhere('kategori', 'Kompetensi_A');
            $rowB    = $items->firstWhere('kategori', 'Kompetensi_B');
            $grupGap = $rowA['grup_gap'] ?? $rowB['grup_gap'] ?? '';
            $label   = ($grupGap !== null && $grupGap !== '')
                ? $grupGap
                : ($rowA['label'] ?? $rowB['label'] ?? '');

            $skorLulus      = isset($rowA) ? $rowA['avg_skor'] : null;
            $skorDibutuhkan = isset($rowB) ? $rowB['avg_skor'] : null;
            $gap            = (isset($rowA) && isset($rowB))
                ? round($skorDibutuhkan - $skorLulus, 2)
                : null;

            return [
                'label'      => $label,
                'skor_lulus' => $skorLulus,
                'gap'        => $gap,
                'count'      => isset($rowA) ? ($rowA['count'] ?? 0) : 0,
            ];
        })->filter(fn($r) => $r['skor_lulus'] !== null);

        if ($joined->isEmpty()) {
            return [
                'skor_kompetensi' => ['label' => '-', 'value' => 0.0, 'hint' => '-'],
                'gap_terbesar'    => ['label' => '-', 'gap' => 0.0, 'hint' => '-'],
            ];
        }

        // FR-047: kompetensi dengan SKOR RATA-RATA TERTINGGI saat lulus —
        // bukan rata-rata dari semua kompetensi (itu tidak menunjukkan
        // kompetensi mana yang unggul, cuma angka gabungan tanpa makna).
        $maxSkorRow      = $joined->sortByDesc('skor_lulus')->first();
        $skorKompetensi  = $maxSkorRow['skor_lulus'] ?? 0.0;

        $maxGapRow = $joined->filter(fn($r) => $r['gap'] !== null)->sortByDesc('gap')->first();
        $gapValue  = $maxGapRow['gap'] ?? 0.0;

        return [
            'skor_kompetensi' => [
                'label' => $maxSkorRow['label'] ?? '-',
                'value' => round($skorKompetensi, 2),
                'hint'  => 'Skor ' . number_format($skorKompetensi, 1, ',', '.'),
            ],
            'gap_terbesar'    => [
                'label' => $maxGapRow['label'] ?? '-',
                'gap'   => $gapValue,
                'hint'  => $this->formatSignedDecimal($gapValue) . ' poin',
            ],
        ];
    }

    // ── Private — Metode Pembelajaran (card 3 & 4) ────────────────

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
            'avg_persepsi' => ['value' => $avg, 'hint' => 'Semua metode'],
        ];
    }

    // ── Private — Pembiayaan (card 5 & 6) ────────────────────────

    private function buildPembiayaanCards(\Illuminate\Support\Collection $raw): array
    {
        $total = $raw->sum('count');

        if ($total === 0) {
            return [
                'mandiri_keluarga' => ['pct' => 0.0, 'hint' => 'Sumber utama'],
                'beasiswa'         => ['pct' => 0.0, 'hint' => 'Pem. + Swasta'],
            ];
        }

        $mandiriCount  = $raw->filter(fn($r) => $this->matchesKeywords($r['sumber_biaya'], self::MANDIRI_KEYWORDS))->sum('count');
        $beasiswaCount = $raw->filter(fn($r) => $this->matchesKeywords($r['sumber_biaya'], self::BEASISWA_KEYWORDS))->sum('count');

        return [
            'mandiri_keluarga' => ['pct' => round($mandiriCount  / $total * 100, 1), 'hint' => 'Sumber utama'],
            'beasiswa'         => ['pct' => round($beasiswaCount / $total * 100, 1), 'hint' => 'Pem. + Swasta'],
        ];
    }

    // ── Helpers ───────────────────────────────────────────────────

    private function matchesKeywords(string $label, array $keywords): bool
    {
        $lower = mb_strtolower($label);
        foreach ($keywords as $kw) {
            if (str_contains($lower, $kw)) return true;
        }
        return false;
    }

    private function formatDecimal(float $value): string
    {
        return number_format($value, 1, ',', '.');
    }

    private function formatSignedDecimal(float $value): string
    {
        return ($value > 0 ? '+' : '') . $this->formatDecimal($value);
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