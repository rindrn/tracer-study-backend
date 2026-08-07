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
 *   dimensions: prodi_sk, perusahaan_sk, wirausaha_sk, masa_tunggu_bekerja, ...
 *   (CATATAN: sebelumnya file ini pakai nama id_prodi/id_perusahaan/id_wirausaha
 *   yang TIDAK ADA di schema Cube.js -- setiap query akan gagal 400 "not found
 *   for path". Sudah diperbaiki ke nama kolom fisik yang benar, dikonfirmasi
 *   via query langsung ke Cube.js.)
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
 *   - Bekerja   : perusahaan_sk IS NOT NULL (ada di fact_tracer_study)
 *   - Wirausaha : wirausaha_sk IS NOT NULL
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
                'FactTracerStudy.prodi_sk',
                'DimProdi.nama_prodi',
                'DimProdi.jurusan',
            ],
            'filters' => $this->filterTahun($tahun),
            'order'   => [['DimProdi.nama_prodi', 'asc']],
        ])->map(fn($r) => [
            'id_prodi'   => (int) ($r['FactTracerStudy.prodi_sk'] ?? 0),
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
            'dimensions' => ['FactTracerStudy.prodi_sk'],
            'filters'    => array_merge($this->filterTahun($tahun), [
                // id_perusahaan IS NOT NULL → alumni bekerja di perusahaan
                [
                    'member'   => 'FactTracerStudy.perusahaan_sk',
                    'operator' => 'set',
                ],
            ]),
        ])->map(fn($r) => [
            'id_prodi' => (int) ($r['FactTracerStudy.prodi_sk'] ?? 0),
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
            'dimensions' => ['FactTracerStudy.prodi_sk'],
            'filters'    => array_merge($this->filterTahun($tahun), [
                [
                    'member'   => 'FactTracerStudy.perusahaan_sk',
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
            'id_prodi' => (int) ($r['FactTracerStudy.prodi_sk'] ?? 0),
            'cepat'    => (int) ($r['FactTracerStudy.count_alumni'] ?? 0),
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    //  4. KESESUAIAN BIDANG (dari yang bekerja, label Sangat Erat/Erat)
    // ──────────────────────────────────────────────────────────────

    /**
     * Alumni bekerja dengan kesesuaian bidang = sesuai.
     * DimKesesuaianBidang TIDAK punya kolom boolean flag_kesesuaian_bidang --
     * kategorinya cuma dimensions kesesuaian_bidang_sk/id_kesesuaian_level/label
     * (5 label: Sangat Erat/Erat/Cukup Erat/Kurang Erat/Tidak Sama Sekali,
     * lihat DimKesesuaianBidang.js). "Sesuai" = label Sangat Erat atau Erat --
     * mengikuti definisi yang sama dipakai KesesuaianRepository (dashboard
     * Luaran Pekerjaan yang sudah live & benar).
     *
     * @return Collection<array{id_prodi:int, sesuai:int}>
     */
    public function getKesesuaianPerProdi(?string $tahun): Collection
    {
        return $this->cube->load([
            'measures'   => ['FactTracerStudy.count_alumni'],
            'dimensions' => ['FactTracerStudy.prodi_sk'],
            'filters'    => array_merge($this->filterTahun($tahun), [
                [
                    'member'   => 'FactTracerStudy.perusahaan_sk',
                    'operator' => 'set',
                ],
                [
                    'member'   => 'DimKesesuaianBidang.label',
                    'operator' => 'equals',
                    'values'   => ['Sangat Erat', 'Erat'],
                ],
            ]),
        ])->map(fn($r) => [
            'id_prodi' => (int) ($r['FactTracerStudy.prodi_sk'] ?? 0),
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
            'dimensions' => ['FactTracerStudy.prodi_sk'],
            'filters'    => array_merge($this->filterTahun($tahun), [
                [
                    // id_wirausaha IS NOT NULL → alumni berwirausaha
                    'member'   => 'FactTracerStudy.wirausaha_sk',
                    'operator' => 'set',
                ],
            ]),
        ])->map(fn($r) => [
            'id_prodi'  => (int) ($r['FactTracerStudy.prodi_sk'] ?? 0),
            'wirausaha' => (int) ($r['FactTracerStudy.count_alumni'] ?? 0),
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    //  6. PENDAPATAN — rata-rata take-home pay + % ≥ ambang UMP (dari yang bekerja)
    // ──────────────────────────────────────────────────────────────

    /**
     * Rata-rata take-home pay (konteks mentah) + total alumni dengan data UMP,
     * per prodi. Measure sama dengan yang dipakai PendapatanRepository.
     *
     * @return Collection<array{id_prodi:int, avg_gaji:int, total_dengan_data_ump:int}>
     */
    public function getPendapatanPerProdi(?string $tahun): Collection
    {
        return $this->cube->load([
            'measures'   => [
                'FactTracerStudy.avg_take_home_pay',
                'FactTracerStudy.count_dengan_data_ump',
            ],
            'dimensions' => ['FactTracerStudy.prodi_sk'],
            'filters'    => array_merge($this->filterTahun($tahun), [
                [
                    'member'   => 'FactTracerStudy.perusahaan_sk',
                    'operator' => 'set',
                ],
            ]),
        ])->map(fn($r) => [
            'id_prodi'              => (int) ($r['FactTracerStudy.prodi_sk'] ?? 0),
            'avg_gaji'              => (int) round($r['FactTracerStudy.avg_take_home_pay'] ?? 0),
            'total_dengan_data_ump' => (int) ($r['FactTracerStudy.count_dengan_data_ump'] ?? 0),
        ]);
    }

    /**
     * Jumlah alumni bekerja dengan gaji >= ambang x UMP, per prodi.
     * Dipakai sebagai denominator perbandingan lintas-prodi yang adil — satu
     * ambang yang sama dipakai untuk semua prodi (default 1,2x, sama seperti
     * default di PendapatanService/PendapatanRepository), bukan ambang
     * per-LAM yang bisa beda-beda per prodi (itu akan bikin bar tidak
     * apple-to-apple saat dibandingkan lintas prodi di satu chart).
     *
     * @return Collection<array{id_prodi:int, count_above:int}>
     */
    public function getPendapatanAboveAmbangPerProdi(?string $tahun, float $ambangMultiplier): Collection
    {
        return $this->cube->load([
            'measures'   => ['FactTracerStudy.count_alumni'],
            'dimensions' => ['FactTracerStudy.prodi_sk'],
            'filters'    => array_merge($this->filterTahun($tahun), [
                ['member' => 'FactTracerStudy.salary_ump_multiplier', 'operator' => 'set'],
                ['member' => 'FactTracerStudy.salary_ump_multiplier', 'operator' => 'gte', 'values' => [(string) $ambangMultiplier]],
            ]),
        ])->map(fn($r) => [
            'id_prodi'    => (int) ($r['FactTracerStudy.prodi_sk'] ?? 0),
            'count_above' => (int) ($r['FactTracerStudy.count_alumni'] ?? 0),
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    //  7. TAHUN YANG TERSEDIA (untuk dropdown filter FE)
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
            'dimensions' => ['DimAlumni.tahun_lulus'],
            'order'      => [['DimAlumni.tahun_lulus', 'desc']],
        ])
        ->pluck('DimAlumni.tahun_lulus')
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
            'member'   => 'DimAlumni.tahun_lulus',
            'operator' => 'equals',
            'values'   => [$tahun],
        ]];
    }
}