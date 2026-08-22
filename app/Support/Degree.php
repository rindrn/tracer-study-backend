<?php
// app/Support/Degree.php
namespace App\Support;

use App\Repositories\Transactional\DegreeRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;
use Throwable;

/**
 * Degree — satu-satunya sumber daftar jenjang program studi.
 *
 * KENAPA ADA
 * ----------
 * Daftar jenjang sempat ditulis ulang sebagai literal di 46 aturan validasi,
 * satu migrasi, dan dua berkas frontend. Akibatnya daftarnya menyimpang satu
 * sama lain tanpa ada yang menyadari: sebagian besar pengendali menerima
 * empat jenjang, tetapi PembiayaanController dan PendapatanController hanya
 * menerima dua, sehingga penyaringan S2 di kedua halaman itu ditolak 422
 * padahal prodi S2 ada di basis data.
 *
 * DARI MANA NILAINYA
 * ------------------
 * Dari tabel master `degrees`, yang dikelola lewat Master Data. Berkas
 * `config/academic.php` turun peran jadi daftar bawaan: dipakai migrasi
 * pembuatan tabel untuk mengisinya pertama kali, dan dipakai di sini sebagai
 * jaring pengaman selama tabelnya belum ada — misalnya saat `php artisan
 * migrate` sendiri sedang berjalan di pemasangan baru. Tanpa jaring itu,
 * aturan validasi yang menyentuh jenjang akan meledak sebelum tabelnya
 * sempat dibuat.
 *
 * DUA DAFTAR YANG BERBEDA
 * -----------------------
 * `all()` memuat jenjang yang aktif saja — itu yang boleh dipilih saat
 * MEMBUAT prodi. `allIncludingInactive()` memuat semuanya dan dipakai
 * validasi penyaring dasbor, karena prodi lama boleh berjenjang yang kini
 * dinonaktifkan dan menyaringnya harus tetap sah.
 *
 * @see \App\Http\Controllers\Api\Analytical — pemakai utama filterRule()
 */
final class Degree
{
    private const CACHE_ACTIVE = 'degrees.active_codes';
    private const CACHE_ALL    = 'degrees.all_codes';

    /**
     * Jenjang yang boleh dipilih untuk program studi baru, urut tampil.
     *
     * @return array<string>
     */
    public static function all(): array
    {
        return self::remember(self::CACHE_ACTIVE, fn (DegreeRepository $repo) => $repo->activeCodes());
    }

    /**
     * Seluruh jenjang yang dikenal, termasuk yang dinonaktifkan.
     *
     * @return array<string>
     */
    public static function allIncludingInactive(): array
    {
        return self::remember(self::CACHE_ALL, fn (DegreeRepository $repo) => $repo->allCodes());
    }

    /**
     * Batasan nilai untuk aturan validasi pembuatan prodi.
     *
     * Dikembalikan sebagai objek Rule, bukan potongan string `in:D3,D4`,
     * karena sebagian jenjang mengandung spasi ("S2 Terapan") dan string
     * `in:` memperlakukan koma sebagai pemisah — aman sekarang, tetapi
     * menjadi jebakan begitu ada jenjang baru yang mengandung koma.
     */
    public static function in(): In
    {
        return Rule::in(self::all());
    }

    /**
     * Aturan lengkap untuk parameter penyaring `jenjang` yang boleh kosong.
     *
     * Sengaja memakai daftar lengkap, bukan yang aktif saja. Lihat catatan
     * "dua daftar yang berbeda" di atas.
     *
     * @return array<int, mixed>
     */
    public static function filterRule(): array
    {
        return ['nullable', 'string', Rule::in(self::allIncludingInactive())];
    }

    /**
     * Membuang cache daftar jenjang.
     *
     * Dipanggil DegreeService setiap kali master jenjang berubah. Tanpa ini,
     * jenjang yang baru ditambahkan admin akan ditolak validasi sampai
     * cache-nya kedaluwarsa sendiri — persis jenis kegagalan diam yang
     * membuat orang menyimpulkan "fiturnya tidak jalan".
     */
    public static function flushCache(): void
    {
        Cache::forget(self::CACHE_ACTIVE);
        Cache::forget(self::CACHE_ALL);
    }

    /**
     * Baca dari cache, jatuh ke daftar bawaan bila tabelnya belum ada.
     *
     * Kegagalan koneksi juga ikut ditangkap: perintah artisan seperti
     * `config:cache` boleh jalan tanpa basis data hidup, dan meledak di sini
     * akan membuatnya gagal karena alasan yang sama sekali tidak berhubungan.
     *
     * @param  callable(DegreeRepository): array<string>  $reader
     * @return array<string>
     */
    private static function remember(string $key, callable $reader): array
    {
        try {
            $repo = app(DegreeRepository::class);

            if (! $repo->tableExists()) {
                return config('academic.degrees', []);
            }

            $codes = Cache::rememberForever($key, fn () => $reader($repo));

            return $codes !== [] ? $codes : config('academic.degrees', []);
        } catch (Throwable) {
            return config('academic.degrees', []);
        }
    }
}
