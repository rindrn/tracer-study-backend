<?php

namespace App\DTOs\Analytical\SebaranInstansi;

class SebaranInstansiLokasiDTO
{
    public function __construct(
        private readonly array $topKota,
        private readonly array $topProvinsi,
        private readonly array $filters,
    ) {}

    public function toArray(): array
    {
        return [
            'filters'      => $this->filters,
            'top_kota'     => $this->topKota,
            'top_provinsi' => $this->topProvinsi,
        ];
    }
}