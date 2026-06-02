<?php

namespace App\DTOs\Analytical\Keterserapan;

/**
 * KeterserapanBarDTO
 *
 * Response untuk grafik bar keterserapan per tahun lulus.
 *
 * Tidak menyertakan available_snapshots karena:
 *   - Sumbu X chart ini adalah tahun_lulus, bukan minggu_snapshot
 *   - Dropdown minggu_snapshot dikelola di level global filter dashboard,
 *     bukan per-endpoint chart
 *   - Menyertakannya hanya menambah payload tanpa manfaat untuk chart ini
 *
 * Taruh di: app/DTOs/Analytical/Keterserapan/KeterserapanBarDTO.php
 */
class KeterserapanBarDTO
{
    /**
     * @param array<array{
     *   tahun_lulus: string,
     *   total: int,
     *   count_terserap: int,
     *   count_tidak: int,
     *   pct_terserap: float,
     *   pct_tidak: float,
     *   breakdown: array<array{status:string, count:int, pct:float, kategori:string}>
     * }> $rows
     * @param array<string>  $availableTahun    Untuk inisialisasi sumbu X / range chart
     * @param array          $filters           Filter yang sedang aktif
     * @param array<string>  $statusTerserap    Label-label yang dihitung sebagai "terserap"
     */
    public function __construct(
        public readonly array $rows,
        public readonly array $availableTahun,
        public readonly array $filters,
        public readonly array $statusTerserap,
    ) {}

    public function toArray(): array
    {
        return [
            'chart_type'      => 'bar_keterserapan',
            'filters'         => $this->filters,
            'available_tahun' => $this->availableTahun,
            'status_terserap' => $this->statusTerserap,
            'data'            => $this->rows,
        ];
    }
}