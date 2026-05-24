<?php

namespace App\Repositories\Analytical;

use App\Services\CubeJsClient;
use Illuminate\Support\Collection;

/**
 * Kpi13Repository
 *
 * Semua nama measure/dimension mengacu PERSIS ke schema Cube.js yang ada:
 *
 * FactTracerStudy
 *   measures : count_alumni, avg_masa_tunggu_bekerja, ...
 *   dimensions: id_prodi, id_status_alumni, id_kesesuaian_bidang,
 *               id_wirausaha, masa_tunggu_bekerja, ...
 *
 * DimProdi        → nama_prodi, kode_prodi, jurusan
 * DimWaktu        → tahun_snapshot
 * DimStatusAlumni → label, flag_status
 * DimKesesuaianBidang → flag_kesesuaian_bidang, label
 * DimWirausaha    → jabatan, flag_wirausaha
 *
 * STRATEGI FILTER STATUS ALUMNI:
 * Karena kita tidak tahu nilai aktual `DimStatusAlumni.label` di DW,
 * kita pakai `flag_status = true` sebagai proxy "aktif/valid".
 * Untuk membedakan bekerja vs wirausaha, kita filter via:
 *   - Bekerja   : id_perusahaan IS NOT NULL (ada di fact_tracer_study)
 *   - Wirausaha : id_wirausaha IS NOT NULL
 * Ini lebih robust daripada mengandalkan teks label yang bisa berubah.
 *
 * Di Cube.js, "is set" → operator `set`.
 *
 * Taruh di: app/Repositories/Analytical/Kpi13Repository.php
 */
class Kpi13Repository
{
    public function __construct(
        private readonly CubeJsClient $cube,
    ) {}

    // ──────────────────────────────────────────────────────────────
    //  1. TOTAL ALUMNI PER PRODI (denominator semua %)
    // ──────────────────────────────────────────────────────────────

    /**
     * Count semua alumni yang mengisi tracer study, group by prodi.
     *
     * @return Collection<array{id_prodi:int, nama_prodi:string, jurusan:string, total:int}>
     */
    public function getTotalAlumniPerProdi(?string $tahun): Collection
    {
        return $this->cube->load([
            'measures'   => ['FactTracerStudy.count_alumni'],
            'dimensions' => [
                'FactTracerStudy.id_prodi',
                'DimProdi.nama_prodi',
                'DimProdi.jurusan',
            ],
            'filters' => $this->filterTahun($tahun),
            'order'   => [['DimProdi.nama_prodi', 'asc']],
        ])->map(fn($r) => [
            'id_prodi'   => (int) ($r['FactTracerStudy.id_prodi'] ?? 0),
            'nama_prodi' => $r['DimProdi.nama_prodi'] ?? 'N/A',
            'jurusan'    => $r['DimProdi.jurusan']    ?? '',
            'total'      => (int) ($r['FactTracerStudy.count_alumni'] ?? 0),
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    //  2. BEKERJA — alumni dengan id_perusahaan terisi (set)
    // ──────────────────────────────────────────────────────────────

    /**
     * Alumni yang bekerja di perusahaan (bukan wirausaha, bukan studi lanjut).
     * Proxy: id_perusahaan IS NOT NULL → operator 'set' di Cube.js
     *
     * @return Collection<array{id_prodi:int, bekerja:int}>
     */
    public function getBekerjaPerProdi(?string $tahun): Collection
    {
        return $this->cube->load([
            'measures'   => ['FactTracerStudy.count_alumni'],
            'dimensions' => ['FactTracerStudy.id_prodi'],
            'filters'    => array_merge($this->filterTahun($tahun), [
                // id_perusahaan IS NOT NULL → alumni bekerja di perusahaan
                [
                    'member'   => 'FactTracerStudy.id_perusahaan',
                    'operator' => 'set',
                ],
            ]),
        ])->map(fn($r) => [
            'id_prodi' => (int) ($r['FactTracerStudy.id_prodi'] ?? 0),
            'bekerja'  => (int) ($r['FactTracerStudy.count_alumni'] ?? 0),
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    //  3. MASA TUNGGU ≤ 6 BULAN (dari yang bekerja)
    // ──────────────────────────────────────────────────────────────

    /**
     * Alumni bekerja dengan masa tunggu ≤ 6 bulan.
     * masa_tunggu_bekerja di DW: satuan bulan (integer).
     *
     * @return Collection<array{id_prodi:int, cepat:int}>
     */
    public function getMasaTungguCepatPerProdi(?string $tahun): Collection
    {
        return $this->cube->load([
            'measures'   => ['FactTracerStudy.count_alumni'],
            'dimensions' => ['FactTracerStudy.id_prodi'],
            'filters'    => array_merge($this->filterTahun($tahun), [
                [
                    'member'   => 'FactTracerStudy.id_perusahaan',
                    'operator' => 'set',
                ],
                [
                    // masa_tunggu_bekerja ≤ 6 bulan
                    'member'   => 'FactTracerStudy.masa_tunggu_bekerja',
                    'operator' => 'lte',
                    'values'   => ['6'],
                ],
            ]),
        ])->map(fn($r) => [
            'id_prodi' => (int) ($r['FactTracerStudy.id_prodi'] ?? 0),
            'cepat'    => (int) ($r['FactTracerStudy.count_alumni'] ?? 0),
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    //  4. KESESUAIAN BIDANG (dari yang bekerja, flag = true)
    // ──────────────────────────────────────────────────────────────

    /**
     * Alumni bekerja dengan kesesuaian bidang = sesuai.
     * Menggunakan DimKesesuaianBidang.flag_kesesuaian_bidang = true
     * (dari schema: boolean di dim_kesesuaian_bidang)
     *
     * @return Collection<array{id_prodi:int, sesuai:int}>
     */
    public function getKesesuaianPerProdi(?string $tahun): Collection
    {
        return $this->cube->load([
            'measures'   => ['FactTracerStudy.count_alumni'],
            'dimensions' => ['FactTracerStudy.id_prodi'],
            'filters'    => array_merge($this->filterTahun($tahun), [
                [
                    'member'   => 'FactTracerStudy.id_perusahaan',
                    'operator' => 'set',
                ],
                [
                    // flag_kesesuaian_bidang = true (dari DimKesesuaianBidang.js)
                    'member'   => 'DimKesesuaianBidang.flag_kesesuaian_bidang',
                    'operator' => 'equals',
                    'values'   => ['true'],
                ],
            ]),
        ])->map(fn($r) => [
            'id_prodi' => (int) ($r['FactTracerStudy.id_prodi'] ?? 0),
            'sesuai'   => (int) ($r['FactTracerStudy.count_alumni'] ?? 0),
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    //  5. WIRAUSAHA — alumni dengan id_wirausaha terisi (set)
    // ──────────────────────────────────────────────────────────────

    /**
     * Alumni yang berwirausaha.
     * Proxy: id_wirausaha IS NOT NULL → operator 'set' di Cube.js.
     * DimWirausaha.flag_wirausaha bisa dijadikan filter tambahan
     * tapi 'set' sudah cukup karena foreign key hanya ada jika wirausaha.
     *
     * @return Collection<array{id_prodi:int, wirausaha:int}>
     */
    public function getWirausahaPerProdi(?string $tahun): Collection
    {
        return $this->cube->load([
            'measures'   => ['FactTracerStudy.count_alumni'],
            'dimensions' => ['FactTracerStudy.id_prodi'],
            'filters'    => array_merge($this->filterTahun($tahun), [
                [
                    // id_wirausaha IS NOT NULL → alumni berwirausaha
                    'member'   => 'FactTracerStudy.id_wirausaha',
                    'operator' => 'set',
                ],
            ]),
        ])->map(fn($r) => [
            'id_prodi'  => (int) ($r['FactTracerStudy.id_prodi'] ?? 0),
            'wirausaha' => (int) ($r['FactTracerStudy.count_alumni'] ?? 0),
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    //  6. TAHUN YANG TERSEDIA (untuk dropdown filter FE)
    // ──────────────────────────────────────────────────────────────

    /**
     * Ambil semua nilai tahun_snapshot yang ada di DW.
     * DimWaktu.tahun_snapshot → VARCHAR(5) di schema.
     *
     * @return Collection<string>
     */
    public function getAvailableYears(): Collection
    {
        return $this->cube->load([
            'dimensions' => ['DimWaktu.tahun_snapshot'],
            'order'      => [['DimWaktu.tahun_snapshot', 'desc']],
        ])
        ->pluck('DimWaktu.tahun_snapshot')
        ->filter()       // buang null
        ->unique()
        ->values();
    }

    // ──────────────────────────────────────────────────────────────
    //  PRIVATE
    // ──────────────────────────────────────────────────────────────

    /**
     * Build filter tahun_snapshot ke DimWaktu.
     * DimWaktu.tahun_snapshot adalah type:string di Cube.js schema.
     */
    private function filterTahun(?string $tahun): array
    {
        if (! $tahun) {
            return [];
        }

        return [[
            'member'   => 'DimWaktu.tahun_snapshot',
            'operator' => 'equals',
            'values'   => [$tahun],
        ]];
    }
}