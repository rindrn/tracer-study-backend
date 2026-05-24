<?php

namespace App\Services\Analytical;

use App\DTOs\Analytical\Kpi13\Kpi13ChartDTO;
use App\Repositories\Analytical\Kpi13Repository;
use Illuminate\Support\Collection;

/**
 * Kpi13Service — Perbandingan KPI Lintas Program Studi
 *
 * Alur:
 *  1. Fetch 5 query dari Cube.js via Kpi13Repository
 *  2. Join result per id_prodi menggunakan Collection
 *  3. Hitung persentase dengan denominator yang tepat
 *  4. Rakit Kpi13ChartDTO
 *
 * Tidak ada koneksi ke OLTP — murni dari OLAP (Cube.js).
 *
 * DENOMINATOR:
 *  - keterserapan  = bekerja / total_alumni × 100
 *  - wirausaha     = wirausaha / total_alumni × 100
 *  - masa_tunggu   = cepat / bekerja × 100   (% dari yang bekerja, lebih valid)
 *  - kesesuaian    = sesuai / bekerja × 100  (% dari yang bekerja)
 *
 * Taruh di: app/Services/Analytical/Kpi13Service.php
 */
class Kpi13Service
{
    public function __construct(
        private readonly Kpi13Repository $repo,
    ) {}

    // ──────────────────────────────────────────────────────────────
    //  PUBLIC
    // ──────────────────────────────────────────────────────────────

    public function getChart(array $filters): Kpi13ChartDTO
    {
        $tahun = isset($filters['tahun']) ? (string) $filters['tahun'] : null;

        // 1. Fetch semua data dari Cube.js (sequential — Cube pre-agg sangat cepat)
        $total     = $this->repo->getTotalAlumniPerProdi($tahun);
        $bekerja   = $this->repo->getBekerjaPerProdi($tahun);
        $cepat     = $this->repo->getMasaTungguCepatPerProdi($tahun);
        $sesuai    = $this->repo->getKesesuaianPerProdi($tahun);
        $wirausaha = $this->repo->getWirausahaPerProdi($tahun);
        $years     = $this->repo->getAvailableYears();

        // 2. Build lookup maps indexed by id_prodi untuk O(1) lookup
        $bekerjaMap   = $bekerja->keyBy('id_prodi');
        $cepatMap     = $cepat->keyBy('id_prodi');
        $sesuaiMap    = $sesuai->keyBy('id_prodi');
        $wirausahaMap = $wirausaha->keyBy('id_prodi');

        // 3. Hitung persentase per prodi
        $prodiRows = $total->map(function (array $row) use (
            $bekerjaMap,
            $cepatMap,
            $sesuaiMap,
            $wirausahaMap
        ): array {
            $id  = $row['id_prodi'];
            $n   = max($row['total'], 1); // guard div/0

            $jmlBekerja   = (int) ($bekerjaMap[$id]['bekerja']   ?? 0);
            $jmlCepat     = (int) ($cepatMap[$id]['cepat']       ?? 0);
            $jmlSesuai    = (int) ($sesuaiMap[$id]['sesuai']     ?? 0);
            $jmlWirausaha = (int) ($wirausahaMap[$id]['wirausaha'] ?? 0);

            // Denominator masa tunggu & kesesuaian = jumlah yang bekerja
            $nBekerja = max($jmlBekerja, 1);

            return [
                'id_prodi'     => $id,
                'prodi'        => $row['nama_prodi'],
                'jurusan'      => $row['jurusan'],
                'total_alumni' => $row['total'],

                // Persentase — 2 desimal
                'keterserapan' => $this->pct($jmlBekerja,   $n),
                'wirausaha'    => $this->pct($jmlWirausaha, $n),
                'masa_tunggu'  => $this->pct($jmlCepat,     $nBekerja),
                'kesesuaian'   => $this->pct($jmlSesuai,    $nBekerja),

                // Angka mentah — untuk tooltip detail di FE
                'raw' => [
                    'bekerja'   => $jmlBekerja,
                    'cepat'     => $jmlCepat,
                    'sesuai'    => $jmlSesuai,
                    'wirausaha' => $jmlWirausaha,
                ],
            ];
        })->values()->toArray();

        return new Kpi13ChartDTO(
            prodiRows:      $prodiRows,
            availableYears: $years->toArray(),
            filters:        array_filter(['tahun' => $tahun]),
        );
    }

    // ──────────────────────────────────────────────────────────────
    //  Data for export — reuse getChart, strip ke flat array
    // ──────────────────────────────────────────────────────────────

    public function getExportRows(array $filters): array
    {
        return $this->getChart($filters)->prodiRows;
    }

    // ──────────────────────────────────────────────────────────────
    //  PRIVATE
    // ──────────────────────────────────────────────────────────────

    private function pct(int $numerator, int $denominator): float
    {
        if ($denominator <= 0) return 0.0;
        return round(($numerator / $denominator) * 100, 2);
    }
}