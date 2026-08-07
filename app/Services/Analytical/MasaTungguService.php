<?php

namespace App\Services\Analytical;

use App\DTOs\Analytical\MasaTunggu\MasaTungguBarDTO;
use App\DTOs\Analytical\MasaTunggu\MasaTungguDistribusiDTO;
use App\DTOs\Analytical\MasaTunggu\MasaTungguDrillDownDTO;
use App\DTOs\Analytical\MasaTunggu\MasaTungguBandingkanDTO;
use App\DTOs\Analytical\MasaTunggu\PolaPencarianKerjaDTO;
use App\DTOs\Analytical\MasaTunggu\MasaTungguPrediksiDTO;
use App\Repositories\Analytical\MasaTungguRepository;
use App\Traits\WithCache;

class MasaTungguService
{
    use WithCache;

    private const TTL = 3600;
    /** Ambang default (dulu hardcode di FactTracerStudy.js) -- dipakai kalau FE tidak kirim batas_cepat_bulan. */
    private const BATAS_CEPAT_DEFAULT = 6.0;

    public function __construct(
        private readonly MasaTungguRepository $repo,
    ) {}

    private function batasCepat(array $params): float
    {
        return isset($params['batas_cepat_bulan']) && $params['batas_cepat_bulan'] !== ''
            ? (float) $params['batas_cepat_bulan']
            : self::BATAS_CEPAT_DEFAULT;
    }

    public function getBar(array $params): MasaTungguBarDTO
    {
        $key = $this->key('masa_tunggu:bar', $params);

        $data = $this->remember($key, function () use ($params) {
            return $this->repo->getBarData(
                jenjang:        $params['jenjang']         ?? null,
                jurusan:        $params['jurusan']         ?? null,
                namaProdi:      $params['nama_prodi']      ?? null,
                tahunLulus:     $params['tahun_lulus']     ?? null,
                mingguSnapshot: $params['minggu_snapshot'] ?? null,
                batasCepatBulan: $this->batasCepat($params),
            )->map(function ($r) {
                $pctCepat = $r['count_terserap'] > 0
                    ? round($r['count_masa_tunggu_cepat'] / $r['count_terserap'] * 100, 1)
                    : 0.0;

                return [
                    'nama_prodi'              => $r['nama_prodi'],
                    'jenjang'                 => $r['jenjang'],
                    'jurusan'                 => $r['jurusan'],
                    'tahun_lulus'             => $r['tahun_lulus'],
                    'count_alumni'            => $r['count_alumni'],
                    'count_terserap'          => $r['count_terserap'],
                    'count_masa_tunggu_cepat' => $r['count_masa_tunggu_cepat'],
                    'pct_cepat'                  => $pctCepat,
                    'avg_masa_tunggu_bekerja'    => $r['avg_masa_tunggu_bekerja'],
                    'median_masa_tunggu_bekerja' => $r['median_masa_tunggu_bekerja'],
                ];
            })->values()->toArray();
        }, self::TTL, ['analytics-dashboard']);

        return new MasaTungguBarDTO(
            data:    $data,
            filters: $this->activeFilters($params),
        );
    }

    public function getDistribusi(array $params): MasaTungguDistribusiDTO
    {
        $key = $this->key('masa_tunggu:distribusi', $params);

        $data = $this->remember($key, function () use ($params) {
            return $this->repo->getDistribusiData(
                jenjang:        $params['jenjang']         ?? null,
                jurusan:        $params['jurusan']         ?? null,
                namaProdi:      $params['nama_prodi']      ?? null,
                tahunLulus:     $params['tahun_lulus']     ?? null,
                mingguSnapshot: $params['minggu_snapshot'] ?? null,
            )->map(fn($r) => [
                'nama_prodi'                 => $r['nama_prodi'],
                'jenjang'                    => $r['jenjang'],
                'tahun_lulus'                => $r['tahun_lulus'],
                'count_tunggu_0_3_bulan'     => $r['count_tunggu_0_3_bulan'],
                'count_tunggu_3_6_bulan'     => $r['count_tunggu_3_6_bulan'],
                'count_tunggu_lebih_6_bulan' => $r['count_tunggu_lebih_6_bulan'],
                'avg_masa_tunggu_bekerja'    => $r['avg_masa_tunggu_bekerja'],
                'min_masa_tunggu_bekerja'    => $r['min_masa_tunggu_bekerja'],
                'max_masa_tunggu_bekerja'    => $r['max_masa_tunggu_bekerja'],
            ])->values()->toArray();
        }, self::TTL, ['analytics-dashboard']);

        return new MasaTungguDistribusiDTO(
            data:    $data,
            filters: $this->activeFilters($params),
        );
    }

    public function getBandingkan(array $params): MasaTungguBandingkanDTO
    {
        $key = $this->key('masa_tunggu:bandingkan', $params);

        $cached = $this->remember($key, function () use ($params) {
            $prodiFilter = is_string($params['prodi'] ?? null)
                ? [$params['prodi']]
                : ($params['prodi'] ?? []);

            return $this->repo->getDistribusiPerProdi(
                prodiFilter:    $prodiFilter,
                jenjang:        $params['jenjang']         ?? null,
                jurusan:        $params['jurusan']         ?? null,
                tahunLulus:     $params['tahun_lulus']     ?? null,
                mingguSnapshot: $params['minggu_snapshot'] ?? null,
                batasCepatBulan: $this->batasCepat($params),
            );
        }, self::TTL, ['analytics-dashboard']);

        return new MasaTungguBandingkanDTO(
            data:      $cached['data'],
            prodiList: $cached['prodi_list'],
            filters:   $this->activeFilters($params),
        );
    }

    // ── DrillDown tidak di-cache ──────────────────────────────────

    public function getDrillDown(array $params): MasaTungguDrillDownDTO
    {
        $page    = max(1, (int) ($params['page']     ?? 1));
        $perPage = min(100, max(5, (int) ($params['per_page'] ?? 15)));
        $rentang = $params['rentang'];

        $result = $this->repo->getDetailAlumniByRentang(
            rentang:        $rentang,
            jenjang:        $params['jenjang']         ?? null,
            jurusan:        $params['jurusan']         ?? null,
            namaProdi:      $params['nama_prodi']      ?? null,
            tahunLulus:     $params['tahun_lulus']     ?? null,
            mingguSnapshot: $params['minggu_snapshot'] ?? null,
            search:         $params['search']          ?? null,
            page:           $page,
            perPage:        $perPage,
            batasCepatBulan: $this->batasCepat($params),
        );

        return new MasaTungguDrillDownDTO(
            data:        $result['data'],
            rentang:     $rentang,
            page:        $page,
            perPage:     $perPage,
            totalOnPage: $result['total_on_page'],
            filters:     $this->activeFilters($params, [
                'jenjang', 'jurusan', 'nama_prodi', 'tahun_lulus', 'minggu_snapshot',
            ]),
        );
    }

    // ── Helpers ───────────────────────────────────────────────────

    private function key(string $prefix, array $params): string
    {
        $relevant = array_diff_key($params, array_flip(['page', 'per_page', 'search']));
        ksort($relevant);
        return $prefix . ':' . md5(json_encode($relevant));
    }

    // FR-026: pola pencarian kerja — bulan sebelum lulus mulai cari kerja +
    // durasi masa tunggu, per prodi × tahun. Konteks penjelas, bukan chart
    // besar; ditempatkan berdekatan dengan Kpi5WaitingTimeChart di FE.
    public function getPolaPencarianKerja(array $params): PolaPencarianKerjaDTO
    {
        $key = $this->key('masa_tunggu:pola_pencarian', $params);

        $data = $this->remember($key, function () use ($params) {
            return $this->repo->getPolaPencarianKerja(
                jenjang:        $params['jenjang']         ?? null,
                jurusan:        $params['jurusan']         ?? null,
                namaProdi:      $params['nama_prodi']      ?? null,
                tahunLulus:     $params['tahun_lulus']     ?? null,
                mingguSnapshot: $params['minggu_snapshot'] ?? null,
            )->values()->toArray();
        }, self::TTL, ['analytics-dashboard']);

        return new PolaPencarianKerjaDTO(
            data:    $data,
            filters: $this->activeFilters($params),
        );
    }

    // FR-027: prediksi tren median masa tunggu periode berikutnya, berbasis
    // regresi linier sederhana atas data historis median per tahun_lulus.
    // Minimal 3 titik data supaya proyeksi tidak sekadar menyambung 2 titik
    // (rawan noise); di bawah itu prediksi dikembalikan null + alasan.
    private const MIN_TITIK_PREDIKSI = 3;

    public function getPrediksi(array $params): MasaTungguPrediksiDTO
    {
        $key = $this->key('masa_tunggu:prediksi', $params);

        $result = $this->remember($key, function () use ($params) {
            $rows = $this->repo->getMedianTrendPerTahun(
                jenjang:        $params['jenjang']         ?? null,
                jurusan:        $params['jurusan']         ?? null,
                namaProdi:      $params['nama_prodi']      ?? null,
                mingguSnapshot: $params['minggu_snapshot'] ?? null,
            )->values();

            $historis = $rows->map(fn($r) => [
                'tahun_lulus'  => $r['tahun_lulus'],
                'median'       => $r['median_masa_tunggu_bekerja'],
                'count_alumni' => $r['count_alumni'],
                'is_prediksi'  => false,
            ])->toArray();

            if ($rows->count() < self::MIN_TITIK_PREDIKSI) {
                return [
                    'historis'   => $historis,
                    'prediksi'   => null,
                    'metodologi' => [
                        'metode'                => 'regresi_linier',
                        'jumlah_titik_historis'  => $rows->count(),
                        'catatan'               => 'Minimal ' . self::MIN_TITIK_PREDIKSI . ' tahun data historis diperlukan untuk membuat proyeksi.',
                    ],
                ];
            }

            [$slope, $intercept] = $this->linearRegression(
                $rows->map(fn($r) => (int) $r['tahun_lulus'])->all(),
                $rows->map(fn($r) => $r['median_masa_tunggu_bekerja'])->all(),
            );

            $lastYear     = (int) $rows->last()['tahun_lulus'];
            $nextYear     = $lastYear + 1;
            $predictedRaw = $slope * $nextYear + $intercept;
            // Masa tunggu tidak bisa negatif -- clamp ke 0 kalau tren menurun tajam.
            $predicted    = round(max(0, $predictedRaw), 1);

            return [
                'historis'   => $historis,
                'prediksi'   => [
                    'tahun_lulus' => (string) $nextYear,
                    'median'      => $predicted,
                    'is_prediksi' => true,
                ],
                'metodologi' => [
                    'metode'                => 'regresi_linier',
                    'jumlah_titik_historis' => $rows->count(),
                    'slope'                 => round($slope, 3),
                    'intercept'             => round($intercept, 3),
                    'catatan'               => 'Proyeksi berbasis tren linier data historis; bukan jaminan, hanya estimasi arah tren.',
                ],
            ];
        }, self::TTL, ['analytics-dashboard']);

        return new MasaTungguPrediksiDTO(
            historis:   $result['historis'],
            prediksi:   $result['prediksi'],
            metodologi: $result['metodologi'],
            filters:    $this->activeFilters($params, ['jenjang', 'jurusan', 'nama_prodi', 'minggu_snapshot']),
        );
    }

    /**
     * Regresi linier least-squares sederhana: y = slope*x + intercept.
     * @return array{0: float, 1: float} [slope, intercept]
     */
    private function linearRegression(array $xs, array $ys): array
    {
        $n = count($xs);
        $sumX  = array_sum($xs);
        $sumY  = array_sum($ys);
        $sumXY = 0.0;
        $sumX2 = 0.0;
        foreach ($xs as $i => $x) {
            $sumXY += $x * $ys[$i];
            $sumX2 += $x * $x;
        }

        $denominator = $n * $sumX2 - $sumX * $sumX;
        if (abs($denominator) < 1e-9) {
            return [0.0, $n > 0 ? $sumY / $n : 0.0];
        }

        $slope     = ($n * $sumXY - $sumX * $sumY) / $denominator;
        $intercept = ($sumY - $slope * $sumX) / $n;

        return [$slope, $intercept];
    }

    private function activeFilters(array $params, array $keys = []): array
    {
        $allKeys = ['jenjang', 'jurusan', 'nama_prodi', 'tahun_lulus', 'minggu_snapshot'];
        $keys    = empty($keys) ? $allKeys : $keys;
        return array_filter(
            array_intersect_key($params, array_flip($keys)),
            fn($v) => $v !== null && $v !== '' && $v !== [],
        );
    }
}