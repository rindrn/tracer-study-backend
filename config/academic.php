<?php

/*
|--------------------------------------------------------------------------
| Kosakata akademik yang berlaku umum di pendidikan tinggi
|--------------------------------------------------------------------------
|
| Berbeda dengan `config/institution.php` yang berisi identitas satu kampus,
| berkas ini memuat istilah yang sama di semua perguruan tinggi karena
| ditetapkan regulasi, bukan oleh kampus masing-masing.
|
| Sumbernya satu supaya migrasi, aturan validasi, dan dropdown frontend
| tidak pernah berbeda daftar. Sebelumnya daftar jenjang ditulis ulang di
| puluhan tempat, sehingga `programs.degree` hanya menerima empat nilai
| sementara `education_records.degree` menerima tujuh — alumni boleh punya
| riwayat S3 tapi kampusnya tidak boleh punya prodi S3.
|
| PERAN BERKAS INI SUDAH TURUN. Sejak jenjang jadi entity master data, sumber
| kebenarannya adalah tabel `degrees` yang dikelola lewat Master Data. Daftar
| di bawah tinggal dipakai dua hal: mengisi tabel itu pertama kali lewat
| migrasi `create_degrees_table`, dan menjadi jaring pengaman di
| `App\Support\Degree` selama tabelnya belum ada — misalnya saat migrasi
| pemasangan baru sendiri sedang berjalan. Menyuntingnya TIDAK mengubah
| pemasangan yang tabelnya sudah terbentuk.
|
*/

return [

    /**
     * Jenjang program studi, selaras dengan PDDIKTI.
     *
     * Ini kosakata tertutup: kampus tidak mengarang jenjang sendiri, jadi
     * daftarnya boleh dikunci CHECK constraint. Yang tidak boleh adalah
     * menguncinya pada jenjang milik satu kampus saja — SmartTracer harus
     * bisa dipasang di politeknik (D1–D4), universitas (S1–S3), maupun
     * kampus dengan program profesi dan spesialis.
     *
     * Urutan larik menentukan urutan tampil di dropdown dan sumbu grafik,
     * jadi disusun menaik dari jenjang terendah.
     */
    'degrees' => [
        'D1',
        'D2',
        'D3',
        'D4',
        'S1',
        'Profesi',
        'Spesialis',
        'S2',
        'S2 Terapan',
        'S3',
        'S3 Terapan',
    ],

];
