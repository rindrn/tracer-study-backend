<?php
// app/Support/Degree.php
namespace App\Support;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

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
 * Nilainya sendiri tinggal di `config/academic.php`, bukan di sini, supaya
 * pemasangan bisa menyesuaikan tanpa menyunting kode.
 *
 * @see \App\Http\Controllers\Api\Analytical — pemakai utama rule()
 */
final class Degree
{
    /**
     * Semua jenjang yang boleh dimiliki program studi, urut dari terendah.
     *
     * @return array<string>
     */
    public static function all(): array
    {
        return config('academic.degrees', []);
    }

    /**
     * Batasan nilai untuk aturan validasi.
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
     * @return array<int, mixed>
     */
    public static function filterRule(): array
    {
        return ['nullable', 'string', self::in()];
    }
}
