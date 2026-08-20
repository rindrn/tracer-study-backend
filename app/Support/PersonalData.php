<?php
// app/Support/PersonalData.php
namespace App\Support;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * PersonalData — enkripsi data pribadi alumni yang tersimpan (UU 27/2022).
 *
 * YANG DILINDUNGI
 * ---------------
 * NIK dan NPWP. Keduanya pengenal tunggal seumur hidup yang tidak bisa
 * diganti kalau bocor, dan keduanya tidak pernah dipakai untuk mencari,
 * menyaring, mengurutkan, atau menggabung tabel di seluruh aplikasi ini
 * (pencarian alumni memakai NIM dan nama, lihat
 * AlumniProfileRepository::paginateForAdmin) — jadi mengenkripsinya tidak
 * mematahkan satu pun query.
 *
 * Nama, surel, dan telepon SENGAJA tidak dienkripsi. Ketiganya dipakai
 * mencari dan menggabung; mengenkripsinya akan mematikan pencarian alumni,
 * penggabungan kontak penilai, dan login alumni lewat surel. Perlindungannya
 * bertumpu pada kontrol akses berbasis peran dan jejak audit
 * (AuditLogService), bukan enkripsi.
 *
 * KENAPA BUKAN CAST ELOQUENT
 * --------------------------
 * Kelihatannya `protected $casts = ['nik' => 'encrypted']` lebih ringkas.
 * Di kodebase ini cast itu TIDAK AKAN PERNAH JALAN: seluruh baca-tulis
 * alumni menempuh query builder lewat AlumniProfileRepository, dan model
 * AlumniProfile hanya dipakai untuk token Sanctum serta kata sandi. Cast
 * yang tidak pernah dilewati justru berbahaya — ia memberi kesan aman
 * padahal kolomnya tetap polos. Maka enkripsinya dipasang di batas
 * repository, satu-satunya pintu yang benar-benar dilewati semua orang.
 *
 * SATU PENGECUALIAN YANG HARUS DIINGAT
 * ------------------------------------
 * AlumniProfileRepository::getForReportQuery() mengembalikan query builder
 * mentah, bukan baris — maatwebsite/excel yang menjalankannya sendiri secara
 * bertahap. Repository tidak punya kesempatan mendekripsi di sana, jadi
 * MinistrySheetExport::map() WAJIB memanggil reveal() sendiri. Kalau tidak,
 * lembar ekspor kementerian berisi ciphertext.
 *
 * KUNCI
 * -----
 * Memakai APP_KEY lewat Crypt (AES-256-CBC + HMAC bawaan Laravel).
 * Konsekuensinya: APP_KEY hilang berarti NIK dan NPWP hilang selamanya.
 * Simpan cadangannya terpisah dari cadangan basis data — kalau keduanya
 * disimpan di satu tempat, enkripsi ini tidak melindungi apa pun.
 */
final class PersonalData
{
    /** Kolom alumni_profiles yang disimpan terenkripsi. */
    public const FIELDS = ['nik', 'npwp'];

    /** Cegah kelas ini di-instansiasi; seluruh isinya statis. */
    private function __construct() {}

    /**
     * Enkripsi satu nilai sebelum disimpan.
     *
     * Nilai kosong dikembalikan sebagai null, bukan sebagai ciphertext dari
     * string kosong: kolomnya nullable dan "tidak diisi" harus tetap terbaca
     * sebagai tidak diisi di query maupun di ekspor.
     */
    public static function protect(?string $plain): ?string
    {
        $plain = $plain === null ? null : trim($plain);

        if ($plain === null || $plain === '') {
            return null;
        }

        // Nilai yang SUDAH terenkripsi dibiarkan apa adanya. Ini yang menahan
        // enkripsi ganda saat satu baris melewati dua jalur tulis berturut-turut
        // (mis. impor massal lalu koreksi manual) — enkripsi ganda tidak akan
        // ketahuan saat menulis, hanya saat membaca, dan saat itu nilai aslinya
        // sudah tidak bisa dipulihkan tanpa dekripsi berlapis.
        if (self::looksEncrypted($plain)) {
            return $plain;
        }

        return Crypt::encryptString($plain);
    }

    /**
     * Dekripsi satu nilai yang dibaca dari basis data.
     *
     * Nilai yang gagal didekripsi dikembalikan apa adanya, bukan dilempar
     * sebagai galat. Alasannya: baris yang ditulis sebelum migrasi enkripsi
     * berjalan masih polos, dan satu baris lawas tidak boleh menjatuhkan
     * seluruh halaman daftar alumni atau seluruh berkas ekspor.
     */
    public static function reveal(?string $stored): ?string
    {
        if ($stored === null || $stored === '') {
            return null;
        }

        try {
            return Crypt::decryptString($stored);
        } catch (DecryptException) {
            return $stored;
        }
    }

    /**
     * Samarkan nilai untuk ditampilkan: empat digit pertama dan empat digit
     * terakhir dibiarkan, sisanya diganti bulatan.
     *
     * Dipakai jejak audit supaya catatannya berguna ("NIK 3273••••••••1234
     * diubah") tanpa menjadikan tabel log sebagai salinan kedua data pribadi
     * yang justru tidak terenkripsi.
     */
    public static function mask(?string $plain): ?string
    {
        if ($plain === null || $plain === '') {
            return null;
        }

        $length = mb_strlen($plain);

        // Terlalu pendek untuk disamarkan sebagian tanpa membocorkan hampir
        // seluruhnya — samarkan total.
        if ($length <= 8) {
            return str_repeat('•', $length);
        }

        return mb_substr($plain, 0, 4)
            . str_repeat('•', $length - 8)
            . mb_substr($plain, -4);
    }

    /**
     * Enkripsi kolom yang dilindungi di dalam satu larik data tulis.
     *
     * Kunci yang tidak ada di larik dilewati, bukan diisi null — supaya
     * pembaruan sebagian (mis. hanya mengubah telepon) tidak menghapus NIK
     * yang tidak ikut dikirim.
     */
    public static function protectAttributes(array $data): array
    {
        foreach (self::FIELDS as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = self::protect(
                    $data[$field] === null ? null : (string) $data[$field]
                );
            }
        }

        return $data;
    }

    /** Dekripsi kolom yang dilindungi pada satu baris hasil query builder. */
    public static function revealRow(?object $row): ?object
    {
        if ($row === null) {
            return null;
        }

        foreach (self::FIELDS as $field) {
            if (property_exists($row, $field)) {
                $row->{$field} = self::reveal(
                    $row->{$field} === null ? null : (string) $row->{$field}
                );
            }
        }

        return $row;
    }

    /**
     * Dekripsi kolom yang dilindungi pada sekumpulan baris.
     *
     * Barisnya diubah di tempat dan larik aslinya yang dikembalikan, supaya
     * pemanggil bisa memakai ini pada isi paginator tanpa kehilangan metadata
     * halaman.
     */
    public static function revealRows(iterable $rows): iterable
    {
        foreach ($rows as $row) {
            self::revealRow($row);
        }

        return $rows;
    }

    /**
     * Tebakan murah apakah sebuah nilai sudah berupa ciphertext Laravel.
     *
     * Crypt menghasilkan JSON ber-base64 berisi kunci iv, value, dan mac.
     * Percobaan dekripsi penuh dipakai sebagai pemeriksaan terakhir; saringan
     * bentuk di depannya menahan mayoritas nilai polos supaya tidak setiap
     * NIK harus melewati percobaan dekripsi yang gagal.
     */
    private static function looksEncrypted(string $value): bool
    {
        // NIK dan NPWP polos jauh lebih pendek dari ciphertext terpendek.
        if (mb_strlen($value) < 40) {
            return false;
        }

        $decoded = base64_decode($value, true);

        if ($decoded === false || !str_contains($decoded, '"mac"')) {
            return false;
        }

        try {
            Crypt::decryptString($value);
            return true;
        } catch (DecryptException) {
            return false;
        }
    }
}
