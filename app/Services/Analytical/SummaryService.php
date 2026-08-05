<?php

namespace App\Services\Analytical;

use App\DTOs\Analytical\Summary\SummaryDTO;
use App\Repositories\Analytical\SummaryRepository;
use App\Traits\WithCache;

class SummaryService
{
    use WithCache;

    private const TTL                = 3600;
    private const STABLE_THRESHOLD_PP = 1.0;

    public function __construct(
        private readonly SummaryRepository $repo,
    ) {}

    // ──────────────────────────────────────────────────────────────
    //  SUMMARY — 5 card sekaligus
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
     *     "belum_mengisi": { "value": 465, "hint": "Follow-up" }
     *   }
     * }
     */
    public function getSummary(array $params): SummaryDTO
    {
        $key = $this->key('summary:cards', $params);

        $cached = $this->remember($key, function () use ($params) {
            $jenjang   = $params['jenjang']         ?? null;
            $namaProdi = $params['nama_prodi']      ?? null;
            $jurusan   = $params['jurusan']         ?? null;
            $gradYear  = $params['graduation_year'] ?? null;

            $agg = $this->repo->getAggregate(
                jenjang:        $jenjang,
                namaProdi:      $namaProdi,
                jurusan:        $jurusan,
                graduationYear: $gradYear,
            );

            $ratePerYear = $this->repo->getRatePerYear(
                jenjang:        $jenjang,
                namaProdi:      $namaProdi,
                jurusan:        $jurusan,
                graduationYear: $gradYear,
            );

            $total          = $agg['total'];
            $countSubmitted = $agg['count_submitted'];

            $responseRate = $total > 0
                ? round($countSubmitted / $total * 100, 1)
                : 0.0;

            [$trendPp, $trendDirection] = $this->computeYearOverYearTrend($ratePerYear);

            return [
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
            ];
        }, self::TTL, ['analytics-dashboard']);

        return new SummaryDTO(
            cards:   $cached,
            filters: $this->activeFilters($params),
        );
    }

    // ── Private helpers ───────────────────────────────────────────

    private function computeYearOverYearTrend(array $ratePerYear): array
    {
        $n = count($ratePerYear);

        if ($n < 2) {
            return [null, 'flat'];
        }

        $latest   = $ratePerYear[$n - 1];
        $previous = $ratePerYear[$n - 2];
        $diff     = round($latest['rate'] - $previous['rate'], 1);

        $direction = match (true) {
            $diff > self::STABLE_THRESHOLD_PP  => 'up',
            $diff < -self::STABLE_THRESHOLD_PP => 'down',
            default                            => 'flat',
        };

        return [$diff, $direction];
    }

    private function formatFillTimeLabel(?float $hours): string
    {
        if ($hours === null) return '-';
        return number_format(round($hours, 1), 1, ',', '.') . ' jam';
    }

    private function key(string $prefix, array $params): string
    {
        $relevant = array_diff_key($params, array_flip(['page', 'per_page', 'search']));
        ksort($relevant);
        return $prefix . ':' . md5(json_encode($relevant));
    }

    private function activeFilters(array $params): array
    {
        $keys = ['jenjang', 'nama_prodi', 'jurusan', 'graduation_year'];
        return array_filter(
            array_intersect_key($params, array_flip($keys)),
            fn($v) => $v !== null && $v !== '' && $v !== [],
        );
    }
}