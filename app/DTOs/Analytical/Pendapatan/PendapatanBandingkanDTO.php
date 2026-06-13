<?php

namespace App\DTOs\Analytical\Pendapatan;

/**
 * PendapatanBandingkanDTO
 *
 * Response untuk halaman Bandingkan Prodi — Pendapatan.
 * Struktur statuses hanya 2 label: "≥ 1,2× UMP" dan "< 1,2× UMP".
 */
class PendapatanBandingkanDTO
{
    /**
     * @param array<array{
     *   nama_prodi: string,
     *   jenjang: string,
     *   jurusan: string,
     *   total: int,
     *   avg_gaji: float,
     *   statuses: array<array{label:string, count:int, pct:float}>
     * }> $chart
     * @param array         $table       Struktur sama dengan chart (FE render kolom dari statuses[])
     * @param array<string> $prodiList
     * @param array         $filters
     */
    public function __construct(
        public readonly array $chart,
        public readonly array $table,
        public readonly array $prodiList,
        public readonly array $filters,
    ) {}

    public function toArray(): array
    {
        return [
            'filters'    => $this->filters,
            'prodi_list' => $this->prodiList,
            'chart'      => $this->chart,
            'table'      => $this->table,
        ];
    }
}