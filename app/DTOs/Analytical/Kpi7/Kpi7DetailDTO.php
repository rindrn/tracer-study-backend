<?php

namespace App\DTOs\Analytical\Kpi7;

class Kpi7DetailDTO
{
    public function __construct(
        public readonly array  $selectedFilter,
        public readonly array  $summary,
        public readonly array  $data,
        public readonly ?array $pagination = null,
    ) {}

    public function toArray(): array
    {
        $result = [
            'kpi' => [
                'id'   => 7,
                'name' => 'Persentase Lulusan Berwirausaha',
            ],
            'selected_filter' => $this->selectedFilter,
            'summary'         => $this->summary,
        ];

        if ($this->pagination) {
            $result['pagination'] = $this->pagination;
        }

        $result['data'] = $this->data;

        return $result;
    }
}