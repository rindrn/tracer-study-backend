<?php

namespace App\DTOs\Analytical\Kesesuaian;

class KesesuaianBandingkanDTO
{
    public function __construct(
        private readonly array $data,
        private readonly array $prodiList,
        private readonly array $filters,
    ) {}

    public function toArray(): array
    {
        return [
            'filters'    => $this->filters,
            'prodi_list' => $this->prodiList,
            'data'       => $this->data,
        ];
    }
}