<?php

namespace App\Services\Analytical;

use App\DTOs\Analytical\Kpi13\Kpi13ChartDTO;
use App\Repositories\Analytical\KesesuaianRepository;
use App\Repositories\Analytical\Kpi13Repository;
use App\Repositories\Analytical\MasaTungguRepository;
use App\Repositories\Analytical\PendapatanRepository;
use App\Repositories\Analytical\WirausahaRepository;
use App\Services\Transactional\ThresholdService;
use Illuminate\Support\Collection;

/**
 * Kpi13Service — Perbandingan KPI Lintas Program Studi
 *
 * Satu baris per prodi berisi 5 metrik tab Employment Outcome beserta status
 * threshold LAM prodi itu sendiri (unggul/baik/kurang, null = belum ada).
 * Dipakai halaman "Bandingkan per Prodi": tiap tab menampilkan satu metrik.
 *
 * Setiap metrik dihitung dari repository dan penyebut yang SAMA dengan grafik
 * per angkatan di tabnya, dijumlahkan lintas tahun lulus per prodi:
 *  - keterserapan = count_terserap / count_alumni        (MasaTungguRepository::getBarData)
 *  - masa_tunggu  = cepat ≤ N bulan / count_bekerja      (MasaTungguRepository::getBarData)
 *  - kesesuaian   = sesuai / (sesuai + tidak sesuai)     (KesesuaianRepository::getBarData)
 *  - wirausaha    = wirausaha / count_alumni             (WirausahaRepository::getBarData + getBarDataTotal)
 *  - pendapatan   = gaji ≥ x UMP / punya data UMP        (PendapatanRepository::getUmpPerProdi)
 *
 * N bulan dan x UMP mengikuti param LAM aktif prodi (default 6 bulan dan
 * 1,2x UMP), sama seperti grafik tab yang membaca param dari useLamFilter.
 *
 * INDICATOR KEY (tabel threshold_indicators):
 *  keterserapan → graduate_absorption, masa_tunggu → employment_time,
 *  kesesuaian → field_relevance, wirausaha → entrepreneurship,
 *  pendapatan → salary_above_ump
 */
class Kpi13Service
{
    private const METRIC_INDICATOR_KEY = [
        'keterserapan' => 'graduate_absorption',
        'masa_tunggu'  => 'employment_time',
        'kesesuaian'   => 'field_relevance',
        'wirausaha'    => 'entrepreneurship',
        'pendapatan'   => 'salary_above_ump',
    ];

    private const BATAS_CEPAT_DEFAULT = 6.0;
    private const AMBANG_UMP_DEFAULT  = 1.2;

    public function __construct(
        private readonly Kpi13Repository $repo,
        private readonly MasaTungguRepository $masaTungguRepo,
        private readonly KesesuaianRepository $kesesuaianRepo,
        private readonly WirausahaRepository $wirausahaRepo,
        private readonly PendapatanRepository $pendapatanRepo,
        private readonly ThresholdService $thresholdService,
    ) {}

    // ──────────────────────────────────────────────────────────────
    //  PUBLIC
    // ──────────────────────────────────────────────────────────────

    /**
     * @param array $params query params yang sudah lewat EnforcesProdiScope
     *                      (jenjang, jurusan, nama_prodi, id_prodi_in,
     *                      tahun_lulus / tahun, minggu_snapshot)
     */
    public function getChart(array $params): Kpi13ChartDTO
    {
        $scope = $this->resolveScope($params);

        $prodis = $this->repo->getProdiIdentity($scope);

        // Threshold aktif per prodi per metrik -- dibaca dulu karena param
        // (batas bulan, kelipatan UMP) menentukan cara menghitung metriknya.
        $thresholds = $prodis->mapWithKeys(fn (array $p) => [
            self::key($p['nama_prodi'], $p['jenjang']) => $this->resolveThresholds($p['id_prodi']),
        ]);

        $masaTunggu = $this->masaTungguPerProdi($scope, $prodis, $thresholds);
        $sesuai     = $this->kesesuaianPerProdi($scope);
        $wirausaha  = $this->wirausahaPerProdi($scope);
        $pendapatan = $this->pendapatanPerProdi($scope, $prodis, $thresholds);

        $prodiRows = $prodis->map(function (array $p) use ($thresholds, $masaTunggu, $sesuai, $wirausaha, $pendapatan): array {
            $key = self::key($p['nama_prodi'], $p['jenjang']);
            $mt  = $masaTunggu[$key] ?? ['alumni' => 0, 'terserap' => 0, 'bekerja' => 0, 'cepat' => 0];
            $ks  = $sesuai[$key]     ?? ['sesuai' => 0, 'tidak' => 0];
            $ws  = $wirausaha[$key]  ?? ['wirausaha' => 0, 'alumni' => 0];
            $pd  = $pendapatan[$key] ?? ['above' => 0, 'total' => 0];

            $metrics = [
                'keterserapan' => $this->pct($mt['terserap'], $mt['alumni']),
                'masa_tunggu'  => $this->pct($mt['cepat'], $mt['bekerja']),
                'kesesuaian'   => $this->pct($ks['sesuai'], $ks['sesuai'] + $ks['tidak']),
                'wirausaha'    => $this->pct($ws['wirausaha'], $ws['alumni']),
                'pendapatan'   => $this->pct($pd['above'], $pd['total']),
            ];

            $prodiThresholds = $thresholds[$key];

            return [
                'id_prodi'     => $p['id_prodi'],
                'prodi'        => $p['nama_prodi'],
                'jenjang'      => $p['jenjang'],
                'jurusan'      => $p['jurusan'],
                'total_alumni' => $p['total'],
                ...$metrics,
                'threshold_status' => collect($metrics)
                    ->map(fn (float $value, string $metric) => $this->statusOf($value, $prodiThresholds[$metric]))
                    ->all(),
                // LAM + nilai batas per metrik, untuk tooltip FE
                // ("LAM Teknik D3 — Unggul ≥ 80%").
                'thresholds' => $prodiThresholds,
                'raw' => [
                    'alumni'          => $mt['alumni'],
                    'terserap'        => $mt['terserap'],
                    'bekerja'         => $mt['bekerja'],
                    'cepat'           => $mt['cepat'],
                    'sesuai'          => $ks['sesuai'],
                    'tidak_sesuai'    => $ks['tidak'],
                    'wirausaha'       => $ws['wirausaha'],
                    'gaji_above_ump'  => $pd['above'],
                    'gaji_dengan_ump' => $pd['total'],
                ],
            ];
        })->values()->toArray();

        return new Kpi13ChartDTO(
            prodiRows:      $prodiRows,
            availableYears: $this->repo->getAvailableYears()->toArray(),
            filters:        array_filter([
                'tahun_lulus'     => $scope['tahun_lulus'],
                'minggu_snapshot' => $scope['minggu_snapshot'],
            ]),
        );
    }

    public function getExportRows(array $params): array
    {
        return $this->getChart($params)->prodiRows;
    }

    // ──────────────────────────────────────────────────────────────
    //  Metrik per prodi
    // ──────────────────────────────────────────────────────────────

    /**
     * Keterserapan dan masa tunggu dari MasaTungguRepository::getBarData.
     * Batas "cepat" berbeda antar LAM, jadi query diulang per batas yang
     * dipakai (biasanya hanya satu atau dua nilai).
     *
     * @return array<string, array{alumni:int, terserap:int, bekerja:int, cepat:int}>
     */
    private function masaTungguPerProdi(array $scope, Collection $prodis, Collection $thresholds): array
    {
        $batasPerProdi = $prodis->mapWithKeys(function (array $p) use ($thresholds) {
            $key = self::key($p['nama_prodi'], $p['jenjang']);
            return [$key => $thresholds[$key]['masa_tunggu']['param'] ?? self::BATAS_CEPAT_DEFAULT];
        });

        $result = [];
        foreach ($batasPerProdi->unique()->push(self::BATAS_CEPAT_DEFAULT)->unique() as $batas) {
            $rows = $this->masaTungguRepo->getBarData(
                jenjang:         $scope['jenjang'],
                jurusan:         $scope['jurusan'],
                idProdiIn:       $scope['id_prodi_in'],
                namaProdi:       $scope['nama_prodi'],
                tahunLulus:      $scope['tahun_lulus'],
                mingguSnapshot:  $scope['minggu_snapshot'],
                batasCepatBulan: (float) $batas,
            );

            foreach ($this->sumPerProdi($rows, ['count_alumni', 'count_terserap', 'count_bekerja', 'count_masa_tunggu_cepat']) as $key => $sum) {
                if ((float) ($batasPerProdi[$key] ?? self::BATAS_CEPAT_DEFAULT) !== (float) $batas) {
                    continue;
                }
                $result[$key] = [
                    'alumni'   => $sum['count_alumni'],
                    'terserap' => $sum['count_terserap'],
                    'bekerja'  => $sum['count_bekerja'],
                    'cepat'    => $sum['count_masa_tunggu_cepat'],
                ];
            }
        }

        return $result;
    }

    /** @return array<string, array{sesuai:int, tidak:int}> */
    private function kesesuaianPerProdi(array $scope): array
    {
        $rows = $this->kesesuaianRepo->getBarData(
            jenjang:        $scope['jenjang'],
            jurusan:        $scope['jurusan'],
            idProdiIn:      $scope['id_prodi_in'],
            namaProdi:      $scope['nama_prodi'],
            tahunLulus:     $scope['tahun_lulus'],
            mingguSnapshot: $scope['minggu_snapshot'],
        );

        return array_map(fn (array $s) => [
            'sesuai' => $s['count_sesuai_bidang'],
            'tidak'  => $s['count_tidak_sesuai_bidang'],
        ], $this->sumPerProdi($rows, ['count_sesuai_bidang', 'count_tidak_sesuai_bidang']));
    }

    /**
     * Penyebut = seluruh alumni prodi (getBarDataTotal), termasuk prodi yang
     * tidak punya alumni wirausaha sama sekali -- sama dengan grafik tab.
     *
     * @return array<string, array{wirausaha:int, alumni:int}>
     */
    private function wirausahaPerProdi(array $scope): array
    {
        $args = [
            'jenjang'        => $scope['jenjang'],
            'jurusan'        => $scope['jurusan'],
            'idProdiIn'      => $scope['id_prodi_in'],
            'namaProdi'      => $scope['nama_prodi'],
            'tahunLulus'     => $scope['tahun_lulus'],
            'mingguSnapshot' => $scope['minggu_snapshot'],
        ];

        $wirausaha = $this->sumPerProdi($this->wirausahaRepo->getBarData(...$args), ['count_wirausaha']);
        $total     = $this->sumPerProdi($this->wirausahaRepo->getBarDataTotal(...$args), ['count_alumni']);

        return collect($total)->map(fn (array $t, string $key) => [
            'wirausaha' => $wirausaha[$key]['count_wirausaha'] ?? 0,
            'alumni'    => $t['count_alumni'],
        ])->all();
    }

    /**
     * Kelipatan UMP berbeda antar LAM, jadi query diulang per ambang.
     *
     * @return array<string, array{above:int, total:int}>
     */
    private function pendapatanPerProdi(array $scope, Collection $prodis, Collection $thresholds): array
    {
        $ambangPerProdi = $prodis->mapWithKeys(function (array $p) use ($thresholds) {
            $key = self::key($p['nama_prodi'], $p['jenjang']);
            return [$key => $thresholds[$key]['pendapatan']['param'] ?? self::AMBANG_UMP_DEFAULT];
        });

        // getUmpPerProdi tidak menerima nama_prodi sebagai filter tunggal;
        // scope kaprodi diteruskan lewat $prodiFilter + jenjang.
        $prodiFilter = $scope['nama_prodi'] !== null ? [$scope['nama_prodi']] : [];

        $result = [];
        foreach ($ambangPerProdi->unique()->push(self::AMBANG_UMP_DEFAULT)->unique() as $ambang) {
            $res = $this->pendapatanRepo->getUmpPerProdi(
                prodiFilter:      $prodiFilter,
                jenjang:          $scope['jenjang'],
                jurusan:          $scope['jurusan'],
                idProdiIn:        $scope['id_prodi_in'],
                tahunLulus:       $scope['tahun_lulus'],
                mingguSnapshot:   $scope['minggu_snapshot'],
                ambangMultiplier: (float) $ambang,
            );

            foreach ($res['chart'] as $row) {
                $key = self::key($row['nama_prodi'], $row['jenjang']);
                if ((float) ($ambangPerProdi[$key] ?? self::AMBANG_UMP_DEFAULT) !== (float) $ambang) {
                    continue;
                }
                $result[$key] = [
                    'above' => (int) ($row['statuses'][0]['count'] ?? 0),
                    'total' => (int) $row['total'],
                ];
            }
        }

        return $result;
    }

    // ──────────────────────────────────────────────────────────────
    //  Threshold
    // ──────────────────────────────────────────────────────────────

    /**
     * Versi standar aktif per metrik untuk satu prodi (id_prodi OLTP).
     *
     * @return array<string, array{lam:?string, baik:?float, unggul:?float, param:?float}>
     */
    private function resolveThresholds(int $prodiId): array
    {
        $out = [];

        foreach (self::METRIC_INDICATOR_KEY as $metricKey => $indicatorKey) {
            $chart   = $this->thresholdService->forChart($prodiId, $indicatorKey);
            $version = collect($chart['versions'] ?? [])->firstWhere('is_active', true);

            $out[$metricKey] = [
                'lam'    => $chart['lam']['name'] ?? null,
                'baik'   => isset($version['thresholds']['baik']['value'])   ? (float) $version['thresholds']['baik']['value']   : null,
                'unggul' => isset($version['thresholds']['unggul']['value']) ? (float) $version['thresholds']['unggul']['value'] : null,
                'param'  => isset($version['dynamic_param']['value'])        ? (float) $version['dynamic_param']['value']        : null,
            ];
        }

        return $out;
    }

    /** @return 'unggul'|'baik'|'kurang'|null */
    private function statusOf(float $value, array $threshold): ?string
    {
        return match (true) {
            $threshold['baik'] === null && $threshold['unggul'] === null => null,
            $threshold['unggul'] !== null && $value >= $threshold['unggul'] => 'unggul',
            $threshold['baik'] !== null && $value >= $threshold['baik'] => 'baik',
            default => 'kurang',
        };
    }

    // ──────────────────────────────────────────────────────────────
    //  PRIVATE
    // ──────────────────────────────────────────────────────────────

    /**
     * Normalisasi params. minggu_snapshot WAJIB terisi: tanpanya setiap
     * snapshot ETL ikut terjumlah dan semua hitungan berlipat.
     *
     * @return array{jenjang:?string, jurusan:?string, nama_prodi:?string, id_prodi_in:?array, tahun_lulus:?string, minggu_snapshot:?string}
     */
    private function resolveScope(array $params): array
    {
        $str = fn (string $k) => isset($params[$k]) && $params[$k] !== '' ? (string) $params[$k] : null;

        $tahun = $str('tahun_lulus') ?? $str('tahun');

        return [
            'jenjang'         => $str('jenjang'),
            'jurusan'         => $str('jurusan'),
            'nama_prodi'      => $str('nama_prodi'),
            'id_prodi_in'     => $params['id_prodi_in'] ?? null,
            'tahun_lulus'     => $tahun === 'all' ? null : $tahun,
            'minggu_snapshot' => $str('minggu_snapshot') ?? $this->repo->latestSnapshotId(),
        ];
    }

    /**
     * Jumlahkan kolom count per prodi (lintas tahun lulus).
     *
     * @param  string[] $columns
     * @return array<string, array<string, int>>
     */
    private function sumPerProdi(Collection $rows, array $columns): array
    {
        $out = [];
        foreach ($rows as $r) {
            if (($r['nama_prodi'] ?? '') === '') {
                continue;
            }
            $key = self::key($r['nama_prodi'], $r['jenjang'] ?? '');
            foreach ($columns as $c) {
                $out[$key][$c] = ($out[$key][$c] ?? 0) + (int) ($r[$c] ?? 0);
            }
        }
        return $out;
    }

    /** Nama prodi tidak unik antar jenjang (lihat EnforcesProdiScope), jadi kunci = nama + jenjang. */
    private static function key(string $namaProdi, string $jenjang): string
    {
        return $namaProdi . '|' . $jenjang;
    }

    private function pct(int $numerator, int $denominator): float
    {
        if ($denominator <= 0) return 0.0;
        return round(($numerator / $denominator) * 100, 1);
    }
}
