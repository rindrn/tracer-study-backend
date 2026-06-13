<?php

namespace App\DTOs\Analytical\Wirausaha;

class WirausahaBandingkanDTO
{
    public function __construct(
        private readonly array $chart,
        private readonly array $prodiList,
        private readonly array $filters,
    ) {}

    public function toArray(): array
    {
        return [
            'filters'    => $this->filters,
            'prodi_list' => $this->prodiList,
            'chart'      => $this->chart,
        ];
    }
}