<?php

namespace App\DTOs\Analytical\Kpi13;

/**
 * Kpi13ChartDTO
 *
 * Kontrak response untuk KPI 13 — Perbandingan KPI Lintas Program Studi.
 * Threshold TIDAK dimasukkan (belum ada di OLAP, akan ditambah nanti).
 *
 * Taruh di: app/DTOs/Analytical/Kpi13/Kpi13ChartDTO.php
 */
class Kpi13ChartDTO
{
    public function __construct(
        /**
         * Data per prodi, sudah dihitung persentasenya.
         *
         * @var array<array{
         *   id_prodi: int,
         *   prodi: string,
         *   jurusan: string,
         *   total_alumni: int,
         *   keterserapan: float,
         *   masa_tunggu: float,
         *   kesesuaian: float,
         *   wirausaha: float,
         *   raw: array{bekerja:int, cepat:int, sesuai:int, wirausaha:int}
         * }>
         */
        public readonly array $prodiRows,

        /** Tahun snapshot yang tersedia di DW, untuk dropdown FE */
        public readonly array $availableYears,

        /** Filter yang sedang aktif */
        public readonly array $filters,
    ) {}

    public function toArray(): array
    {
        return [
            'kpi' => [
                'id'   => 13,
                'name' => 'Perbandingan KPI Lintas Program Studi',
            ],
            'filters'         => $this->filters,
            'available_years' => $this->availableYears,
            'data'            => $this->prodiRows,
        ];
    }
}