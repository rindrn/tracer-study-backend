<?php
// app/Support/PhoneNumber.php
namespace App\Support;

/**
 * Pembakuan penulisan nomor telepon alumni (DATA-09).
 *
 * KENAPA SATU TEMPAT
 *
 * Aturan yang sama sebelumnya ditulis DUA KALI, di AlumniImport dan di
 * TracerStudySubmitService, dan keduanya sudah sempat menyimpang: versi
 * importir mengembalikan nomor berawalan `+62` apa adanya, sedangkan versi
 * pengisian kuesioner tidak memiliki cabang itu sama sekali. Hasil akhirnya
 * kebetulan sama, tetapi lewat jalan yang berbeda — dan penyimpangan semacam
 * itu tidak pernah berhenti pada satu perbedaan. Menyalinnya untuk ketiga
 * kalinya ke jalur staf akan memastikan ketiganya berbeda cepat atau lambat.
 *
 * BENTUK YANG DIHASILKAN
 *
 * Selalu `+62` diikuti nomor tanpa nol di depan, mis. `+628123456789`.
 *
 * Nomor yang tidak dikenali bentuknya DIKEMBALIKAN APA ADANYA, hanya
 * dibersihkan dari pemisah. Itu disengaja: menolak atau memaksa mengubah
 * nomor luar negeri akan merusak data alumni yang memang bekerja di luar
 * negeri, dan tracer study justru menanyakan hal itu.
 */
final class PhoneNumber
{
    /** Pemisah yang lazim diketik manusia dan tidak membawa makna. */
    private const SEPARATORS = '/[\s\-\(\)\.]/';

    /**
     * Bakukan satu nomor telepon.
     *
     * @param  string|null $phone nomor mentah sebagaimana diketik atau diimpor
     * @return string|null null bila masukannya kosong
     */
    public static function normalize(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $clean = preg_replace(self::SEPARATORS, '', trim($phone));

        if ($clean === '' || $clean === null) {
            return null;
        }

        // Sudah baku.
        if (str_starts_with($clean, '+62')) {
            return $clean;
        }

        // Bentuk nasional: 08xxx -> +628xxx.
        if (str_starts_with($clean, '08')) {
            return '+62' . substr($clean, 1);
        }

        // Kode negara tanpa tanda tambah: 62xxx -> +62xxx.
        //
        // Diperiksa SETELAH cabang '08' supaya nomor yang benar-benar diawali
        // nol tidak pernah salah tafsir. Sengaja TIDAK dipersempit menjadi
        // '628' saja: nomor telepon tetap yang ditulis lengkap dengan kode
        // negara, mis. 6221 untuk Jakarta, juga sah dan akan terlewat kalau
        // syaratnya dibuat khusus untuk nomor seluler. Rangkaian angka yang
        // diawali '62' tanpa nol di depan praktis selalu berarti kode negara,
        // karena kode wilayah dalam negeri selalu diawali nol.
        if (str_starts_with($clean, '62')) {
            return '+' . $clean;
        }

        // Bentuk lain — termasuk nomor luar negeri dan nomor tetap dalam
        // negeri — dibiarkan, sudah bersih dari pemisah.
        return $clean;
    }
}
