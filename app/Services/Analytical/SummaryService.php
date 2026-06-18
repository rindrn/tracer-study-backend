<?php

namespace App\Services\Analytical;

use App\DTOs\Analytical\Summary\SummaryDTO;
use App\Repositories\Analytical\SummaryRepository;

/**
 * SummaryService
 *
 * Orkestrasi data dari SummaryRepository untuk 6 Summary Card di Overview Page:
 *   1. Total Kuesioner   — total alumni (= total kuesioner yang "dikirim")
 *   2. Sudah Mengisi     — count submitted_at IS NOT NULL
 *   3. Response Rate     — (sudah mengisi / total) × 100%, + badge trend
 *   4. Rata-rata Waktu   — AVG(submitted_at - started_at), skip started_at null
 *   5. Belum Mengisi     — total - sudah mengisi
 *   6. Tren 5 Thn        — label "Naik konsisten" / "Menurun" / "Stabil"
 *
 * Definisi penting (hasil klarifikasi):
 *   - "Sudah Mengisi" / response rate di card ini = submitted_at IS NOT NULL SAJA
 *     (BERBEDA dari KPI Response Rate /bar yang menghitung on_going+selesai
 *      sebagai "responded". Di card ini, "Sudah Mengisi" = SELESAI submit).
 *   - Rata-rata waktu pengisian HANYA dari row yang started_at terisi (skip null).
 *   - Badge trend "+5,2%" dan label "Tren 5 Thn" SAMA-SAMA dihitung dari
 *     selisih response rate antara graduation_year terbesar vs terbesar-1
 *     (tidak ada tabel snapshot, jadi pakai perbandingan antar tahun lulus).
 *
 */
class SummaryService
{
    /** Ambang batas (persentase poin) untuk label "Stabil" vs "Naik"/"Menurun". */
    private const STABLE_THRESHOLD_PP = 1.0;

    public function __construct(
        private readonly SummaryRepository $repo,
    ) {}

    // ──────────────────────────────────────────────────────────────
    //  SUMMARY — 6 card sekaligus
    // ──────────────────────────────────────────────────────────────

    /**
     * Response:
     * {
     *   "filters": {},
     *   "cards": {
     *     "total_kuesioner": { "value": 1692, "hint": "Dikirim" },
     *     "sudah_mengisi":   { "value": 1227, "hint": "Response masuk" },
     *     "response_rate":   {
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
     */
    public function getSummary(array $params): SummaryDTO
    {
        $jenjang    = $params['jenjang']         ?? null;
        $namaProdi  = $params['nama_prodi']      ?? null;
        $gradYear   = $params['graduation_year'] ?? null;

        $agg = $this->repo->getAggregate(
            jenjang:        $jenjang,
            namaProdi:      $namaProdi,
            graduationYear: $gradYear,
        );

        $ratePerYear = $this->repo->getRatePerYear(
            jenjang:        $jenjang,
            namaProdi:      $namaProdi,
            graduationYear: $gradYear,
        );

        $total          = $agg['total'];
        $countSubmitted = $agg['count_submitted'];

        $responseRate = $total > 0
            ? round($countSubmitted / $total * 100, 1)
            : 0.0;

        [$trendPp, $trendDirection] = $this->computeYearOverYearTrend($ratePerYear);

        $trenLabel = $this->resolveTrenLabel($trendDirection);

        $cards = [
            'total_kuesioner' => [
                'value' => $total,
                'hint'  => 'Dikirim',
            ],
            'sudah_mengisi' => [
                'value' => $countSubmitted,
                'hint'  => 'Response masuk',
            ],
            'response_rate' => [
                'value'           => $responseRate,
                'hint'            => 'Tingkat respons',
                'trend_pp'        => $trendPp,
                'trend_direction' => $trendDirection,
            ],
            'rata_rata_waktu' => [
                'value_hours'         => $agg['avg_fill_hours'] !== null ? round($agg['avg_fill_hours'], 1) : null,
                'label'               => $this->formatFillTimeLabel($agg['avg_fill_hours']),
                'hint'                => 'Pengisian',
                'count_with_duration' => $agg['count_with_duration'],
            ],
            'belum_mengisi' => [
                'value' => $agg['count_not_submitted'],
                'hint'  => 'Follow-up',
            ],
            'tren_5_tahun' => [
                'label'     => $trenLabel,
                'direction' => $trendDirection,
                'hint'      => $trenLabel,
            ],
        ];

        return new SummaryDTO(
            cards:   $cards,
            filters: $this->activeFilters($params),
        );
    }

    // ──────────────────────────────────────────────────────────────
    //  PRIVATE HELPERS
    // ──────────────────────────────────────────────────────────────

    /**
     * Hitung selisih response rate (persentase poin) antara graduation_year
     * terbesar vs terbesar-1 dari array ratePerYear (hasil getRatePerYear,
     * sudah terurut ascending by tahun).
     *
     * @param  array<int, array{graduation_year:string,total:int,count_submitted:int,rate:float}>  $ratePerYear
     * @return array{0: float|null, 1: string} [trend_pp, direction] — direction: up|down|flat
     */
    private function computeYearOverYearTrend(array $ratePerYear): array
    {
        $n = count($ratePerYear);

        if ($n < 2) {
            // Tidak cukup data 2 tahun untuk dibandingkan
            return [null, 'flat'];
        }

        $latest   = $ratePerYear[$n - 1];
        $previous = $ratePerYear[$n - 2];

        $diff = round($latest['rate'] - $previous['rate'], 1);

        $direction = match (true) {
            $diff > self::STABLE_THRESHOLD_PP  => 'up',
            $diff < -self::STABLE_THRESHOLD_PP => 'down',
            default                            => 'flat',
        };

        return [$diff, $direction];
    }

    private function resolveTrenLabel(string $direction): string
    {
        return match ($direction) {
            'up'   => 'Naik konsisten',
            'down' => 'Menurun',
            default => 'Stabil',
        };
    }

    private function formatFillTimeLabel(?float $hours): string
    {
        if ($hours === null) {
            return '-';
        }

        $rounded = round($hours, 1);
        $formatted = number_format($rounded, 1, ',', '.');

        return "{$formatted} jam";
    }

    private function activeFilters(array $params): array
    {
        $keys = ['jenjang', 'nama_prodi', 'graduation_year'];

        return array_filter(
            array_intersect_key($params, array_flip($keys)),
            fn($v) => $v !== null && $v !== '' && $v !== [],
        );
    }
}