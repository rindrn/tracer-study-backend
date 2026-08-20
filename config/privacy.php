<?php

/*
|--------------------------------------------------------------------------
| Perlindungan Data Pribadi (UU No. 27 Tahun 2022)
|--------------------------------------------------------------------------
|
| Nilai-nilai yang mengatur bagaimana persetujuan alumni diminta dan berapa
| lama datanya disimpan. Sengaja di konfigurasi, bukan di kode: keduanya
| keputusan kebijakan institusi yang bisa berubah tanpa perubahan perangkat
| lunak, dan keduanya harus bisa berbeda antar perguruan tinggi.
|
*/

return [

    /**
     * Versi pemberitahuan privasi yang sedang berlaku.
     *
     * Nilai ini ikut tersimpan di `alumni_profiles.consent_version` setiap
     * kali alumni menyetujui. Menaikkannya membuat seluruh persetujuan lama
     * dianggap kedaluwarsa dan alumni diminta menyetujui ulang sebelum bisa
     * mengisi kuesioner berikutnya.
     *
     * NAIKKAN HANYA KALAU ISI PEMBERITAHUANNYA BERUBAH SECARA MATERIIL —
     * tujuan pemrosesan bertambah, penerima data bertambah, masa simpan
     * berubah. Menaikkannya untuk perbaikan ejaan berarti memaksa ribuan
     * alumni menyetujui ulang tanpa ada yang benar-benar berubah bagi
     * mereka, dan itu melatih orang untuk menyetujui tanpa membaca.
     */
    'notice_version' => env('PRIVACY_NOTICE_VERSION', '2026.1'),

    /**
     * Masa simpan data pribadi alumni, dihitung dalam tahun sejak kelulusan.
     *
     * Belum ada penghapusan otomatis yang membaca nilai ini — angkanya
     * dipakai memberi tahu alumni di halaman "Data Saya" sampai kapan
     * datanya disimpan, sesuai asas pembatasan penyimpanan. Menyatakan masa
     * simpan adalah kewajiban tersendiri, terpisah dari menegakkannya.
     */
    'retention_years' => (int) env('PERSONAL_DATA_RETENTION_YEARS', 10),

    /**
     * Perbuatan yang wajib masuk jejak audit meski tidak mengubah data
     * apa pun. Membaca data pribadi dalam jumlah besar sama pentingnya
     * dengan mengubahnya: ekspor satu berkas bisa memindahkan ribuan NIK
     * keluar dari sistem, dan tanpa catatan tidak ada yang tahu.
     */
    'audit_read_actions' => [
        'export.ministry',
        'export.alumni',
        'export.stakeholder_contacts',
    ],

];
