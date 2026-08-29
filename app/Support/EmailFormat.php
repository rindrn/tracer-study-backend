<?php

namespace App\Support;

/**
 * Validasi format alamat surel SEBELUM masuk antrean pengiriman -- dipakai
 * AlumniCredentialEmailController dan AlumniReminderController.
 *
 * KENAPA DICEK DI SINI, BUKAN CUKUP MENGANDALKAN KEGAGALAN DI WORKER
 *
 * Tanpa ini, alamat yang jelas rusak (bukan kosong -- itu sudah dicek
 * terpisah -- tapi teks yang bukan surel sama sekali) tetap lolos masuk
 * antrean sebagai `queued`, dikirim ke worker, dicoba SendAlumniAccountEmailJob
 * sampai tiga kali dengan jeda naik (10, 30, 90 detik) sebelum akhirnya
 * ditandai gagal oleh Symfony Mailer -- membuang waktu worker untuk sesuatu
 * yang sudah pasti gagal sejak awal, dan menunda petugas melihat baris itu
 * di panel hasil sampai seluruh percobaan habis.
 */
class EmailFormat
{
    public static function isValid(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Domain yang dicadangkan IANA khusus dokumentasi/contoh (RFC 2606,
     * RFC 6761) -- TIDAK PERNAH sah dipakai alumni sungguhan, dan ini
     * persis domain yang dipakai data seed/testing di proyek ini
     * (contoh: zizi39@example.org). Dicek lewat daftar tetap, BUKAN lewat
     * DNS, supaya hasilnya selalu pasti tanpa bergantung jaringan.
     */
    private const RESERVED_DOMAINS = [
        'example.com', 'example.net', 'example.org', 'example.edu',
        'test', 'invalid', 'localhost',
    ];

    /**
     * Domainnya benar-benar bisa menerima surel -- BEDA dari isValid(),
     * yang cuma mengecek bentuk teksnya. Alamat seperti "a@example.net"
     * lolos isValid() (formatnya sah) tapi domainnya null-MX (RFC 7505,
     * target MX "." atau kosong) -- deklarasi eksplisit "domain ini tidak
     * pernah menerima surel apa pun". Tanpa pengecekan ini, kirim ke alamat
     * begitu tetap ditandai `sent` karena relay SMTP (Brevo) MENERIMA pesan
     * untuk diteruskan -- baru bounce belakangan, async, di luar jangkauan
     * job yang cuma menunggu Mail::send() tidak melempar exception.
     *
     * PENTING: hasil dns_get_record()/checkdnsrr() DI SINI TIDAK SELALU
     * BISA DIPERCAYA -- resolver bisa timeout/gagal sesaat karena jaringan,
     * dan itu TIDAK BOLEH ditafsirkan sebagai "domain tidak bisa menerima
     * surel". Salah menolak email alumni yang sungguh valid (karena
     * kebetulan lookup DNS-nya gagal) jauh lebih buruk daripada salah
     * meloloskan satu email yang memang akan bounce belakangan -- jadi
     * fungsi ini GAGAL TERBUKA: hanya menjawab false kalau punya jawaban
     * DNS yang MEYAKINKAN (null MX eksplisit), tidak pernah karena lookup-
     * nya sendiri gagal/kosong.
     */
    public static function hasDeliverableDomain(string $email): bool
    {
        $domain = strtolower(trim(substr((string) strrchr($email, '@'), 1)));
        if ($domain === '' || $domain === false) {
            return false;
        }

        foreach (self::RESERVED_DOMAINS as $reserved) {
            if ($domain === $reserved || str_ends_with($domain, '.' . $reserved)) {
                return false;
            }
        }

        $mxRecords = @dns_get_record($domain, DNS_MX);
        if ($mxRecords !== false && count($mxRecords) > 0) {
            $allNull = true;
            foreach ($mxRecords as $record) {
                if (trim((string) ($record['target'] ?? ''), '.') !== '') {
                    $allNull = false;
                    break;
                }
            }

            // Null MX eksplisit (RFC 7505) -- satu-satunya jawaban DNS yang
            // cukup meyakinkan untuk menolak.
            return !$allNull;
        }

        // Tidak dapat jawaban MX yang meyakinkan (tidak ada record ATAU
        // lookup gagal/timeout, dua-duanya sama-sama false di sini) --
        // gagal terbuka, jangan tolak hanya karena DNS sedang tidak stabil.
        return true;
    }
}
