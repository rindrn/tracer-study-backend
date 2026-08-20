<?php

/**
 * Pengukur waktu tanggap endpoint dasbor SmartTracer.
 *
 * Menjawab satu klaim yang tertulis di proposal BAB 3.6 dan sampai sekarang
 * belum pernah diukur: "response time dashboard < 3 detik". Tanpa angka,
 * klaim itu hanya harapan.
 *
 * CARA PAKAI
 * ----------
 *     php scripts/ukur-response-time.php --email=head.tracer@test.com --password=password123
 *
 * Pilihan:
 *     --base=http://localhost:8000/api   alamat API (bawaan sesuai contoh ini)
 *     --email=, --password=              akun staf; WAJIB
 *     --ulang=10                         berapa kali tiap endpoint dipanggil
 *     --tahun=2021                       tahun lulus untuk penyaring
 *     --dingin                           ukur tanpa pemanasan (lihat catatan)
 *     --csv=hasil.csv                    tulis hasil mentah ke CSV
 *
 * KENAPA ADA PEMANASAN, DAN KAPAN HARUS DIMATIKAN
 * -----------------------------------------------
 * Panggilan pertama ke tiap endpoint dibuang dari perhitungan secara bawaan.
 * Cube.js membangun pre-aggregation saat pertama diminta, dan Laravel
 * menyimpan hasil kueri di cache; panggilan pertama karenanya mengukur
 * pembangunan cache, bukan pelayanan permintaan. Angka yang wajar
 * dibandingkan dengan janji "<3 detik" adalah yang dialami pengguna kedua
 * dan seterusnya — dan di lingkungan operasional, cache-nya sudah panas.
 *
 * TAPI angka dingin juga berarti: itulah yang dialami pengguna pertama tiap
 * pagi, dan sesudah ETL mingguan mengosongkan cache. Jalankan sekali dengan
 * --dingin dan laporkan KEDUANYA. Melaporkan yang panas saja adalah cara
 * halus untuk menyembunyikan pengalaman terburuk pengguna.
 *
 * YANG DIUKUR DAN YANG TIDAK
 * --------------------------
 * Yang diukur: waktu dari permintaan HTTP terkirim sampai badan tanggapan
 * diterima seluruhnya. Yang TIDAK diukur: waktu render di peramban. Artinya
 * angka di sini adalah batas BAWAH dari yang dirasakan pengguna, bukan
 * seluruhnya. Sebutkan itu saat melaporkan; mengaku mengukur "waktu muat
 * dasbor" padahal hanya mengukur API adalah klaim yang lebih besar daripada
 * buktinya.
 */

// ── Bacaan argumen ────────────────────────────────────────────────────────
$opsi = getopt('', [
    'base::', 'email:', 'password:', 'ulang::', 'tahun::', 'csv::', 'dingin',
]);

if (empty($opsi['email']) || empty($opsi['password'])) {
    fwrite(STDERR, "Wajib: --email= dan --password=\n");
    fwrite(STDERR, "Contoh: php scripts/ukur-response-time.php --email=head.tracer@test.com --password=password123\n");
    exit(1);
}

$base     = rtrim($opsi['base'] ?? 'http://localhost:8000/api', '/');
$ulang    = max(1, (int) ($opsi['ulang'] ?? 10));
$tahun    = (int) ($opsi['tahun'] ?? 2021);
$panaskan = !isset($opsi['dingin']);

/** Ambang dari proposal BAB 3.6. */
const AMBANG_DETIK = 3.0;

// ── Endpoint yang diukur ──────────────────────────────────────────────────
//
// Dipilih mewakili tiap BENTUK beban, bukan seluruh 60-an rute analitik:
// ringkasan (agregat seluruh institusi), grafik batang (kelompok per prodi),
// drill-down (baris perorangan, paling berat), pembanding lintas tahun, dan
// satu rute transaksional sebagai pembanding dasar yang tidak menyentuh OLAP.
$endpoint = [
    'Overview — ringkasan'        => "/dashboard/overview/summary?tahun_lulus={$tahun}",
    'Employment — ringkasan'      => "/dashboard/employment/summary?tahun_lulus={$tahun}",
    'Education — ringkasan'       => "/dashboard/education/summary?tahun_lulus={$tahun}",
    'Keterserapan — batang'       => "/dashboard/keterserapan/bar?tahun_lulus={$tahun}",
    'Keterserapan — drill-down'   => "/dashboard/keterserapan/drill-down?tahun_lulus={$tahun}",
    'Masa tunggu — distribusi'    => "/dashboard/masa-tunggu/distribusi?tahun_lulus={$tahun}",
    'Kesesuaian — pie'            => "/dashboard/kesesuaian/pie?tahun_lulus={$tahun}",
    'Pendapatan — batang'         => "/dashboard/pendapatan/bar?tahun_lulus={$tahun}",
    'Response rate — tren'        => "/dashboard/response-rate/trend",
    'Sebaran instansi — lokasi'   => "/dashboard/sebaran-instansi/lokasi?tahun_lulus={$tahun}",
    'Kompetensi — gap'            => "/dashboard/kompetensi/gap?tahun_lulus={$tahun}",
    'Filter meta'                 => "/dashboard/meta/filter-options",
    'Daftar alumni (transaksi)'   => "/alumni?per_page=15",
];

// ── Masuk ─────────────────────────────────────────────────────────────────
echo "SmartTracer — pengukuran waktu tanggap\n";
echo str_repeat('=', 74), "\n";
echo "Sasaran   : {$base}\n";
echo "Ulangan   : {$ulang}x per endpoint", $panaskan ? " (+1 pemanasan, dibuang)" : " (mode DINGIN, tanpa pemanasan)", "\n";
echo "Tahun     : {$tahun}\n";
echo "Ambang    : ", AMBANG_DETIK, " detik (proposal BAB 3.6)\n\n";

$token = masuk($base, $opsi['email'], $opsi['password']);
if ($token === null) {
    fwrite(STDERR, "Gagal masuk. Periksa alamat, surel, dan kata sandi.\n");
    exit(1);
}

// ── Pengukuran ────────────────────────────────────────────────────────────
$hasil  = [];
$mentah = [];

foreach ($endpoint as $nama => $jalur) {
    if ($panaskan) {
        panggil($base . $jalur, $token);
    }

    $durasi = [];
    $galat  = 0;

    for ($i = 0; $i < $ulang; $i++) {
        [$detik, $status] = panggil($base . $jalur, $token);

        // Panggilan yang gagal TIDAK ikut dirata-rata. Galat biasanya kembali
        // sangat cepat, sehingga memasukkannya justru MEMPERBAIKI angka —
        // endpoint yang rusak akan tampak paling gesit. Dihitung terpisah.
        if ($status >= 200 && $status < 300) {
            $durasi[] = $detik;
        } else {
            $galat++;
        }

        $mentah[] = [$nama, $jalur, $i + 1, round($detik * 1000), $status];
    }

    $hasil[$nama] = [
        'jalur' => $jalur,
        'n'     => count($durasi),
        'galat' => $galat,
        'p50'   => persentil($durasi, 50),
        'p95'   => persentil($durasi, 95),
        'maks'  => $durasi ? max($durasi) : null,
    ];
}

// ── Laporan ───────────────────────────────────────────────────────────────
printf("%-28s %9s %9s %9s %7s  %s\n", 'ENDPOINT', 'p50 (ms)', 'p95 (ms)', 'maks (ms)', 'GALAT', 'HASIL');
echo str_repeat('-', 88), "\n";

$lulusSemua = true;
$terburuk   = 0.0;

foreach ($hasil as $nama => $h) {
    if ($h['n'] === 0) {
        printf("%-28s %9s %9s %9s %7d  %s\n", potong($nama, 28), '-', '-', '-', $h['galat'], 'TIDAK TERUKUR');
        $lulusSemua = false;
        continue;
    }

    // p95 yang dipakai memutuskan, bukan rata-rata. Rata-rata menyembunyikan
    // ekor: sepuluh permintaan yang sembilan di antaranya 0,2 detik dan satu
    // 8 detik tetap tampak "0,98 detik rata-rata", padahal satu dari sepuluh
    // pengguna menunggu delapan detik.
    $lulus      = $h['p95'] <= AMBANG_DETIK;
    $lulusSemua = $lulusSemua && $lulus && $h['galat'] === 0;
    $terburuk   = max($terburuk, $h['p95']);

    printf(
        "%-28s %9.0f %9.0f %9.0f %7d  %s\n",
        potong($nama, 28),
        $h['p50'] * 1000,
        $h['p95'] * 1000,
        $h['maks'] * 1000,
        $h['galat'],
        $lulus ? 'LULUS' : 'LEWAT AMBANG',
    );
}

echo str_repeat('-', 88), "\n";
printf("p95 terburuk: %.0f ms dari ambang %.0f ms\n", $terburuk * 1000, AMBANG_DETIK * 1000);
echo $lulusSemua
    ? "KESIMPULAN: seluruh endpoint di bawah ambang.\n"
    : "KESIMPULAN: ada endpoint yang melewati ambang atau gagal. Lihat baris bertanda di atas.\n";

if (!empty($opsi['csv'])) {
    $fp = fopen($opsi['csv'], 'w');
    fputcsv($fp, ['endpoint', 'jalur', 'ulangan_ke', 'durasi_ms', 'status_http']);
    foreach ($mentah as $baris) {
        fputcsv($fp, $baris);
    }
    fclose($fp);
    echo "Data mentah ditulis ke {$opsi['csv']}\n";
}

exit($lulusSemua ? 0 : 1);

// ══════════════════════════════════════════════════════════════════════════

/** Masuk sebagai staf, kembalikan token Sanctum. */
function masuk(string $base, string $email, string $password): ?string
{
    $ch = curl_init("{$base}/auth/login");
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_POSTFIELDS     => json_encode(compact('email', 'password')),
        CURLOPT_TIMEOUT        => 30,
    ]);

    $badan  = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($status !== 200) {
        return null;
    }

    $data = json_decode((string) $badan, true);

    // Bentuk tanggapan login pernah berubah dan bisa berubah lagi; ketiga
    // letak yang mungkin dicoba berurutan supaya skrip ini tidak mati hanya
    // karena satu tingkat pembungkus bergeser.
    return $data['data']['token'] ?? $data['token'] ?? $data['data']['access_token'] ?? null;
}

/**
 * Panggil satu endpoint, kembalikan [durasi_detik, status_http].
 *
 * Waktunya diukur dengan hrtime(), bukan microtime(): hrtime memakai jam
 * monotonik yang tidak ikut melompat kalau jam sistem disesuaikan di tengah
 * pengukuran.
 */
function panggil(string $url, string $token): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ["Authorization: Bearer {$token}", 'Accept: application/json'],
        CURLOPT_TIMEOUT        => 60,
    ]);

    $mulai = hrtime(true);
    curl_exec($ch);
    $detik = (hrtime(true) - $mulai) / 1_000_000_000;

    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [$detik, $status];
}

/** Persentil ke-$p dengan interpolasi linear. */
function persentil(array $nilai, int $p): ?float
{
    if (!$nilai) {
        return null;
    }

    sort($nilai);

    $posisi = ($p / 100) * (count($nilai) - 1);
    $bawah  = (int) floor($posisi);
    $atas   = (int) ceil($posisi);

    if ($bawah === $atas) {
        return $nilai[$bawah];
    }

    return $nilai[$bawah] + ($posisi - $bawah) * ($nilai[$atas] - $nilai[$bawah]);
}

function potong(string $teks, int $panjang): string
{
    return mb_strlen($teks) <= $panjang ? $teks : mb_substr($teks, 0, $panjang - 1) . '…';
}
