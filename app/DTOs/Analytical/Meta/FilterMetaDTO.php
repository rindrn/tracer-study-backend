<?php

namespace App\DTOs\Analytical\Meta;

/**
 * FilterMetaDTO
 *
 * Kontrak response untuk endpoint GET /api/dashboard/meta/filter-options.
 * Berisi semua opsi dropdown global filter yang dibutuhkan dashboard.
 *
 * Taruh di: app/DTOs/Analytical/Meta/FilterMetaDTO.php
 */
class FilterMetaDTO
{
    /**
     * @param array<string>  $tahunLulus       ["2024","2023",...]
     * @param array<array{minggu_snapshot:string, tahun_snapshot:string, label:string}> $snapshot
     * @param array<string>  $jenjang          ["D3","D4"]
     * @param array<array{jurusan:string, jenjang:string}> $jurusan
     * @param array<array{id:int, nama_prodi:string, jurusan:string, jenjang:string, kode_prodi:string}> $prodi
     */
    public function __construct(
        public readonly array $tahunLulus,
        public readonly array $snapshot,
        public readonly array $jenjang,
        public readonly array $jurusan,
        public readonly array $prodi,
    ) {}

    public function toArray(): array
    {
        return [
            'tahun_lulus'     => $this->tahunLulus,
            'snapshot' => $this->snapshot,
            'jenjang'         => $this->jenjang,
            'jurusan'         => $this->jurusan,
            'prodi'           => $this->prodi,
        ];
    }
}