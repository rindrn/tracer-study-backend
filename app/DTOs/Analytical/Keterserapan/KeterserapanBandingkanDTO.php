<?php

namespace App\DTOs\Analytical\Keterserapan;

/**
 * KeterserapanBandingkanDTO
 *
 * Response untuk halaman Bandingkan Prodi.
 *
 * Struktur `chart` dan `table` identik per baris prodi:
 *   [{nama_prodi, jenjang, jurusan, total, statuses:[{label,count,pct}]}]
 *
 * `statuses` bersifat dinamis — FE render kolom dari label yang ada.
 * Hanya STATUS_TERSERAP di Service yang hardcode (keputusan bisnis IKU 2).
 *
 * Taruh di: app/DTOs/Analytical/Keterserapan/KeterserapanBandingkanDTO.php
 */
class KeterserapanBandingkanDTO
{
    /**
     * @param array  $chart       [{nama_prodi, jenjang, jurusan, total, statuses:[{label,count,pct}]}]
     * @param array  $table       Struktur sama dengan chart — FE render kolom dari statuses[]
     * @param array<string> $prodiList  Nama prodi yang ada di hasil (untuk chip FE)
     * @param array  $filters     Filter aktif
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