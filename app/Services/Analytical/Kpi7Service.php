<?php

namespace App\Services\Analytical;

use App\DTOs\Analytical\Kpi7\Kpi7ChartDTO;
use App\DTOs\Analytical\Kpi7\Kpi7DetailDTO;
use App\Repositories\Analytical\Kpi7Repository;

class Kpi7Service
{
    public function __construct(
        private readonly Kpi7Repository $repo,
    ) {}

    // ── CHART ─────────────────────────────────────────────────

    public function getChart(array $filters): Kpi7ChartDTO
    {
        $year      = isset($filters['year'])       ? (int) $filters['year']       : null;
        $programId = isset($filters['program_id']) ? (int) $filters['program_id'] : null;
        $lamId     = isset($filters['lam_id'])     ? (int) $filters['lam_id']     : null;

        $trend = $this->repo->getTrend($programId)
            ->map(fn($row) => [
                'year'  => (int) $row->year,
                'value' => (int) $row->value,
            ])
            ->toArray();

        $pie = $this->repo->getPieDistribution()
            ->map(fn($row) => [
                'label' => $row->label ?? 'Lainnya',
                'value' => (int) $row->value,
            ])
            ->toArray();

        $thresholdRow = $this->repo->getThreshold($lamId, $year);
        $threshold    = $thresholdRow ? [
            'value'    => (float) $thresholdRow->threshold_value,
            'operator' => $thresholdRow->threshold_operator,
            'unit'     => $thresholdRow->threshold_unit,
            'label'    => "{$thresholdRow->lam_name} {$thresholdRow->threshold_value}{$thresholdRow->threshold_unit}",
        ] : null;

        return new Kpi7ChartDTO(
            trendChart:      $trend,
            pieDistribution: $pie,
            threshold:       $threshold,
            filters: [
                'year'       => $year,
                'program_id' => $programId,
                'lam_id'     => $lamId,
            ],
        );
    }

    // ── DETAIL ────────────────────────────────────────────────

    public function getDetails(array $filters): Kpi7DetailDTO
    {
        $year     = isset($filters['year'])     ? (int)    $filters['year']     : null;
        $position = isset($filters['position']) ? (string) $filters['position'] : null;
        $perPage  = isset($filters['per_page']) ? (int)    $filters['per_page'] : 10;
        $page     = isset($filters['page'])     ? (int)    $filters['page']     : 1;

        $result = $this->repo->getDetails($year, $position, $perPage, $page);

        $selectedFilter = array_filter([
            'year'     => $year,
            'position' => $position,
        ]);

        $data = collect($result['rows'])->map(fn($row) => $this->formatDetailRow($row))->toArray();

        $summary = [
            'total_data' => $result['total'],
        ];

        // Hitung persentase hanya jika filter by year
        if ($year && ! $position) {
            $summary['percentage'] = $result['total'] > 0
                ? round(($result['total'] / max($this->repo->getTrend()->sum('value'), 1)) * 100, 2)
                : 0;
        }

        $pagination = [
            'current_page' => $result['page'],
            'per_page'     => $result['per_page'],
            'total_page'   => $result['last_page'],
            'total_data'   => $result['total'],
        ];

        return new Kpi7DetailDTO(
            selectedFilter: $selectedFilter,
            summary:        $summary,
            data:           $data,
            pagination:     $pagination,
        );
    }

    // ── EXPORT ────────────────────────────────────────────────

    public function getExportData(array $filters): array
    {
        $year     = isset($filters['year'])     ? (int)    $filters['year']     : null;
        $position = isset($filters['position']) ? (string) $filters['position'] : null;

        return $this->repo->getAllForExport($year, $position)
            ->map(fn($row) => $this->formatDetailRow($row))
            ->toArray();
    }

    // ── Private ───────────────────────────────────────────────

    private function formatDetailRow(object $row): array
    {
        return [
            'id'              => $row->id,
            'name'            => $row->alumni_name,
            'nim'             => $row->nim,
            'program_studi'   => $row->program_name,
            'degree'          => $row->degree,
            'graduation_year' => $row->graduation_year,
            'status'          => $row->employment_status,
            'position'        => $row->entrepreneurship_role,
            'salary'          => $row->salary ? (float) $row->salary : null,
            'salary_formatted'=> $row->salary_formatted ?? null,
        ];
    }
}