<?php

namespace App\DTOs\Analytical\Kesesuaian;

/*
 * Output 
 * {
 *   "filters": {},
 *   "data": [
 *     { "kode_field": "f1601", "label": "Belum ada lowongan sesuai", "count": 87 }
 *   ]
 * }
 */
class KesesuaianAlasanDTO
{
    public function __construct(
        private readonly array $data,
        private readonly array $filters,
    ) {}

    public function toArray(): array
    {
        return [
            'filters' => $this->filters,
            'data'    => $this->data,
        ];
    }
}