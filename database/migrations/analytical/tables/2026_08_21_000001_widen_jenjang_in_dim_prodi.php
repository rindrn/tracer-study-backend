<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Melebarkan `dim_prodi.jenjang` dari VARCHAR(5) ke VARCHAR(20).
 *
 * Pasangan sisi OLAP dari migrasi `widen_degree_vocabulary` di skema OLTP.
 * Tanpa ini pelebaran kosakata jenjang berhenti di OLTP: prodi berjenjang
 * "Profesi" (7 huruf), "Spesialis" (9), atau "S2 Terapan" (10) boleh dibuat,
 * tetapi ETL menolaknya dengan SQLSTATE[22001] "value too long" saat memuat
 * dimensi — jenjang lolos di hulu lalu mati di hilir.
 *
 * Lima karakter cukup selama kosakatanya hanya D3/D4/S1/S2. Dua puluh memberi
 * ruang untuk seluruh daftar di `config/academic.php` beserta istilah panjang
 * yang mungkin menyusul, dan pada VARCHAR di Postgres batas yang lebih longgar
 * tidak menambah biaya penyimpanan.
 *
 * PENJAGA PEMASANGAN BARU. Skema OLAP dibangun oleh
 * `database/dump/olap_schema.sql` saat seeding, bukan oleh migrasi, sedangkan
 * README menyuruh migrate dijalankan lebih dahulu. Di basis data kosong
 * `dim_prodi` belum ada dan ALTER ini akan mematikan seluruh rangkaian
 * migrasi. Dump-nya sudah menyatakan VARCHAR(20), jadi melewati migrasi ini
 * pada pemasangan baru tidak menghilangkan apa pun.
 */
return new class extends Migration
{
    protected $connection = 'olap';

    public function up(): void
    {
        if (! Schema::connection('olap')->hasTable('dim_prodi')) {
            return;
        }

        DB::connection('olap')->statement(
            'ALTER TABLE public.dim_prodi ALTER COLUMN jenjang TYPE VARCHAR(20)'
        );
    }

    /**
     * Menyempitkan kembali hanya aman kalau tidak ada baris yang terlanjur
     * memakai jenjang panjang. Postgres akan menolak sendiri kalau ada, dan
     * itu memang perilaku yang diinginkan — lebih baik rollback gagal
     * terang-terangan daripada nama jenjang terpotong diam-diam.
     */
    public function down(): void
    {
        if (! Schema::connection('olap')->hasTable('dim_prodi')) {
            return;
        }

        DB::connection('olap')->statement(
            'ALTER TABLE public.dim_prodi ALTER COLUMN jenjang TYPE VARCHAR(5)'
        );
    }
};
