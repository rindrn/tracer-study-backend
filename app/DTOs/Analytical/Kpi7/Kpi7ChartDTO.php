<?php

namespace App\DTOs\Analytical\Kpi7;

class Kpi7ChartDTO
{
    public function __construct(
        public readonly array   $trendChart,
        public readonly array   $pieDistribution,
        public readonly ?array  $threshold,
        public readonly array   $filters,
    ) {}

    public function toArray(): array
    {
        return [
            'kpi' => [
                'id'   => 7,
                'code' => 'KPI_7',
                'name' => 'Persentase Lulusan Berwirausaha',
                'unit' => '%',
            ],
            'filters'          => $this->filters,
            'threshold'        => $this->threshold,
            'trend_chart'      => $this->trendChart,
            'pie_distribution' => $this->pieDistribution,
            'clickable'        => true,
            'drilldown_enabled'=> true,
        ];
    }
}