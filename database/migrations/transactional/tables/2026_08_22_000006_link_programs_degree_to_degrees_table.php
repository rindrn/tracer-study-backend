<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menukar CHECK constraint pada `programs.degree` dengan kunci asing ke
 * `degrees.code`.
 *
 * Selama daftar jenjang masih tetapan di berkas config, CHECK adalah alat yang
 * benar. Begitu daftarnya jadi tabel, CHECK justru berbahaya: isinya membeku
 * pada saat migrasi dijalankan, sehingga jenjang yang ditambahkan admin lewat
 * Master Data akan ditolak basis data padahal barisnya ada. Kunci asing
 * mengikuti isi tabel dengan sendirinya.
 *
 * NILAINYA TIDAK BERUBAH. `programs.degree` tetap menyimpan kode jenjang
 * sebagai teks, bukan id — sengaja, supaya `OltpExtractRepository` dan
 * `ProdiDimService` tidak perlu disentuh sama sekali dan `dim_prodi.jenjang`
 * tetap denormal sebagaimana mestinya sebuah tabel dimensi.
 *
 * `education_records.degree` SENGAJA DIBIARKAN memakai CHECK. Kolom itu
 * mencatat riwayat pendidikan alumni di kampus mana pun, termasuk luar negeri,
 * jadi daftarnya memuat 'Other' yang tidak boleh jadi baris master jenjang
 * milik institusi sendiri. Mengikatnya ke tabel yang sama akan mencampur
 * "jenjang yang boleh dimiliki prodi kami" dengan "jenjang yang pernah
 * ditempuh alumni kami" — dua hal berbeda yang kebetulan bersinggungan.
 *
 * Lebar kolom `programs.degree` sengaja dibiarkan apa adanya. Menyempitkannya
 * agar seragam dengan `degrees.code` sempat dicoba dan ditolak Postgres dengan
 * SQLSTATE[0A000]: view `vw_users_with_programs` bergantung pada kolom itu,
 * jadi tipenya tidak bisa diubah tanpa membongkar dan menyusun ulang view-nya.
 * Kunci asing tidak menuntut lebar yang sama, dan membongkar view demi
 * keseragaman kosmetik jelas tidak sepadan dengan risikonya.
 */
return new class extends Migration
{
    protected $connection = 'oltp';

    public function up(): void
    {
        $conn = DB::connection('oltp');

        $conn->statement('ALTER TABLE programs DROP CONSTRAINT IF EXISTS programs_degree_check');

        $conn->statement(
            'ALTER TABLE programs
             ADD CONSTRAINT programs_degree_foreign
             FOREIGN KEY (degree) REFERENCES degrees (code)
             ON UPDATE CASCADE ON DELETE RESTRICT'
        );
    }

    /**
     * ON UPDATE CASCADE dipasang supaya perbaikan salah ketik pada jenjang yang
     * BELUM dipakai prodi tetap mungkin. Untuk yang sudah dipakai, penjaganya
     * ada di DegreeService: kode baris bawaan ditolak diubah, karena
     * cascade-nya hanya merapikan OLTP dan tidak menyentuh riwayat yang sudah
     * terlanjur tersimpan di gudang data.
     */
    public function down(): void
    {
        $conn = DB::connection('oltp');

        $conn->statement('ALTER TABLE programs DROP CONSTRAINT IF EXISTS programs_degree_foreign');

        $list = implode(',', array_map(
            fn ($v) => "'" . str_replace("'", "''", $v) . "'",
            config('academic.degrees', [])
        ));

        if ($list !== '') {
            $conn->statement(
                "ALTER TABLE programs ADD CONSTRAINT programs_degree_check
                 CHECK (degree::text = ANY (ARRAY[{$list}]::text[]))"
            );
        }
    }
};
