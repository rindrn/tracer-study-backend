<?php

use Illuminate\Support\Facades\Cache;

function timeit(callable $fn, int $runs = 5): array
{
    $times = [];
    $result = null;
    for ($i = 0; $i < $runs; $i++) {
        $start = microtime(true);
        $result = $fn();
        $times[] = (microtime(true) - $start) * 1000; // ms
    }
    // Percobaan pertama SENGAJA dipisah (bukan dibuang) -- merepresentasikan
    // biaya satu-kali koneksi/compile pertama, bukan kondisi steady-state
    // yang sesungguhnya dialami pengguna dashboard yang servernya sudah
    // berjalan lama. Steady-state dihitung dari MEDIAN percobaan ke-2 dst,
    // lebih tahan outlier dibanding rata-rata.
    $cold = $times[0];
    $steady = array_slice($times, 1);
    sort($steady);
    $n = count($steady);
    $median = $n % 2 === 0
        ? ($steady[$n / 2 - 1] + $steady[$n / 2]) / 2
        : $steady[intdiv($n, 2)];
    return [
        'times' => $times,
        'cold' => $cold,
        'steady_median' => $median,
        'steady_min' => min($steady),
        'result' => $result,
    ];
}

echo str_repeat('=', 70) . PHP_EOL;
echo "BENCHMARK: KPI Keterserapan (count_terserap / count_alumni)" . PHP_EOL;
echo "Grup: jenjang x jurusan x nama_prodi x tahun_lulus x minggu_snapshot" . PHP_EOL;
echo str_repeat('=', 70) . PHP_EOL . PHP_EOL;

// Flush semua cache dulu supaya pengukuran mulai dari kondisi bersih
Cache::store('redis')->flush();

// ─────────────────────────────────────────────────────────────
// 1. RAW SQL langsung ke fact atomic -- TANPA pre-aggregation,
//    resolusi kategori "terserap" dihitung ULANG per baris via
//    correlated subquery point-in-time (persis logika Cube.js
//    measure count_terserap, tapi dijalankan tanpa rollup table).
//    Ini simulasi "kalau kita tidak pernah membangun pre-aggregate".
// ─────────────────────────────────────────────────────────────
$rawSql = "
    SELECT
        dp.jenjang, dp.jurusan, dp.nama_prodi, da.tahun_lulus, dw.minggu_snapshot,
        COUNT(*) AS count_alumni,
        COUNT(*) FILTER (
            WHERE (
                SELECT kcm.kpi_category
                FROM public.kpi_category_mapping kcm
                WHERE kcm.semantic_role = 'status_pekerjaan'
                  AND kcm.digunakan_oleh = 'iku2_keterserapan'
                  AND kcm.option_code = SPLIT_PART(dsa.id_status_alumni, ':', 3)
                  AND kcm.effective_date <= dw.tanggal_refresh
                  AND (kcm.deactivated_at IS NULL OR kcm.deactivated_at::date > dw.tanggal_refresh)
                ORDER BY kcm.effective_date DESC
                LIMIT 1
            ) = 'terserap'
        ) AS count_terserap
    FROM public.fact_tracer_study f
    JOIN public.dim_prodi dp        ON dp.prodi_sk = f.prodi_sk
    JOIN public.dim_alumni da       ON da.id_alumni = f.id_alumni
    JOIN public.dim_status_alumni dsa ON dsa.status_alumni_sk = f.status_alumni_sk
    JOIN public.dim_waktu dw        ON dw.id_waktu = f.id_waktu
    GROUP BY dp.jenjang, dp.jurusan, dp.nama_prodi, da.tahun_lulus, dw.minggu_snapshot
    ORDER BY dp.nama_prodi, da.tahun_lulus
";

$rawResult = timeit(function () use ($rawSql) {
    return DB::connection('olap')->select($rawSql);
}, 5);
echo sprintf("[1] Query langsung fact atomic (join + GROUP BY + subquery per baris, tanpa pre-agg)\n");
echo sprintf("    Percobaan (ms): %s\n", implode(', ', array_map(fn($t) => number_format($t, 1), $rawResult['times'])));
echo sprintf("    Cold (panggilan ke-1)     : %s ms\n", number_format($rawResult['cold'], 1));
echo sprintf("    Steady-state (median 2-5) : %s ms   |   Tercepat: %s ms\n", number_format($rawResult['steady_median'], 1), number_format($rawResult['steady_min'], 1));
echo sprintf("    Baris hasil   : %d\n\n", count($rawResult['result']));

// ─────────────────────────────────────────────────────────────
// 2. Cube.js langsung (CubeJsClient::load()) -- BYPASS cache
//    Laravel (WithCache), tapi TETAP lewat pre-aggregation Cube.js
//    yang sudah ter-build. Ini murni efek pre-aggregation saja,
//    belum ditambah efek cache Redis level aplikasi.
// ─────────────────────────────────────────────────────────────
$cube = app(App\Services\CubeJsClient::class);
$cubeQuery = [
    'measures' => ['FactTracerStudy.count_alumni', 'FactTracerStudy.count_terserap'],
    'dimensions' => [
        'DimProdi.jenjang', 'DimProdi.jurusan', 'DimProdi.nama_prodi',
        'DimAlumni.tahun_lulus', 'DimWaktu.minggu_snapshot',
    ],
];

$cubeResult = timeit(function () use ($cube, $cubeQuery) {
    return $cube->load($cubeQuery);
}, 5);
echo sprintf("[2] Cube.js langsung, baca dari fact pre-aggregate (bypass cache Laravel)\n");
echo sprintf("    Percobaan (ms): %s\n", implode(', ', array_map(fn($t) => number_format($t, 1), $cubeResult['times'])));
echo sprintf("    Cold (panggilan ke-1)     : %s ms\n", number_format($cubeResult['cold'], 1));
echo sprintf("    Steady-state (median 2-5) : %s ms   |   Tercepat: %s ms\n", number_format($cubeResult['steady_median'], 1), number_format($cubeResult['steady_min'], 1));
echo sprintf("    Baris hasil   : %d\n\n", count($cubeResult['result']));

// ─────────────────────────────────────────────────────────────
// 3. Lewat Service asli (KeterserapanService::getBar) -- panggilan
//    PERTAMA = cache miss (tetap hit Cube.js + pre-agg di baliknya),
//    panggilan KEDUA dengan param SAMA = cache hit Redis.
// ─────────────────────────────────────────────────────────────
$service = app(App\Services\Analytical\KeterserapanService::class);
$params = []; // tanpa filter -- skenario "buka dashboard pertama kali"

// Cache MISS butuh pengukuran SEKALI SAJA per key (panggilan kedua otomatis
// jadi cache HIT) -- flush dulu supaya key ini dijamin belum ada di Redis.
Cache::store('redis')->flush();
$missStart = microtime(true);
$service->getBar($params);
$missMs = (microtime(true) - $missStart) * 1000;
echo sprintf("[3a] Endpoint dashboard asli, cache MISS (request pertama, key baru)\n");
echo sprintf("     Waktu: %s ms\n\n", number_format($missMs, 1));

$hitResult = timeit(function () use ($service, $params) {
    return $service->getBar($params);
}, 5);
echo sprintf("[3b] Endpoint dashboard asli, cache HIT Redis (request berikutnya, param sama)\n");
echo sprintf("     Percobaan (ms): %s\n", implode(', ', array_map(fn($t) => number_format($t, 1), $hitResult['times'])));
echo sprintf("     Cold (panggilan ke-1)     : %s ms\n", number_format($hitResult['cold'], 1));
echo sprintf("     Steady-state (median 2-5) : %s ms   |   Tercepat: %s ms\n\n", number_format($hitResult['steady_median'], 1), number_format($hitResult['steady_min'], 1));

// ─────────────────────────────────────────────────────────────
// RINGKASAN
// ─────────────────────────────────────────────────────────────
echo str_repeat('=', 70) . PHP_EOL;
echo "RINGKASAN PERBANDINGAN (steady-state / median, sudah dikecualikan cold-start)\n";
echo str_repeat('=', 70) . PHP_EOL;
printf("%-55s %12s\n", "Skenario", "Waktu (ms)");
echo str_repeat('-', 70) . PHP_EOL;
printf("%-55s %12s\n", "1. Fact atomic langsung (tanpa pre-agg)", number_format($rawResult['steady_median'], 1));
printf("%-55s %12s\n", "2. Fact pre-aggregate via Cube.js (tanpa cache app)", number_format($cubeResult['steady_median'], 1));
printf("%-55s %12s\n", "3a. Endpoint dashboard, cache MISS (key baru)", number_format($missMs, 1));
printf("%-55s %12s\n", "3b. Endpoint dashboard, cache HIT (Redis)", number_format($hitResult['steady_median'], 1));
echo str_repeat('-', 70) . PHP_EOL;

$speedupPreAgg = $rawResult['steady_median'] / max($cubeResult['steady_median'], 0.001);
$speedupCache  = $rawResult['steady_median'] / max($hitResult['steady_median'], 0.001);
printf("Percepatan pre-aggregate vs fact atomic  : %sx\n", number_format($speedupPreAgg, 1));
printf("Percepatan cache Redis (warm) vs atomic   : %sx\n", number_format($speedupCache, 1));
echo str_repeat('=', 70) . PHP_EOL;
