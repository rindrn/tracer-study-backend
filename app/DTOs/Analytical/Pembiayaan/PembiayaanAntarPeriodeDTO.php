<?php

namespace App\DTOs\Analytical\Pembiayaan;

class PembiayaanAntarPeriodeDTO
{
    public function __construct(
        private readonly array $data,
        private readonly array $availableTahun,
        private readonly array $filters,
    ) {}

    public function toArray(): array
    {
        return [
            'filters'         => $this->filters,
            'available_tahun' => $this->availableTahun,
            'data'            => $this->data,
        ];
    }
}