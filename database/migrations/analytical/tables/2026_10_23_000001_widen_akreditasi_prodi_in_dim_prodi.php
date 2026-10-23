<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Melebarkan `dim_prodi.akreditasi_prodi` dari VARCHAR(10) ke VARCHAR(30).
 *
 * Migrasi `add_institution_and_accreditation_to_dim_prodi` membuat kolom ini
 * VARCHAR(30), sama dengan sumbernya `programs.accreditation` di OLTP. Tetapi
 * `database/dump/olap_schema.sql` -- yang sebenarnya membangun skema OLAP --
 * menuliskannya VARCHAR(10). Selama semua prodi belum berakreditasi selisih
 * itu tidak terlihat; begitu peringkat IAPS 4.0 "Baik Sekali" (11 huruf)
 * diisi, ETL mati dengan SQLSTATE[22001] "value too long" saat memuat
 * dimensi. Istilah LAM bisa lebih panjang lagi, jadi batasnya disamakan
 * dengan hulu.
 *
 * PENJAGA PEMASANGAN BARU. Sama seperti `widen_jenjang_in_dim_prodi`: di basis
 * data kosong `dim_prodi` belum ada karena skemanya baru datang dari dump saat
 * seeding. Dump-nya sudah diperbaiki ke VARCHAR(30), jadi melewati migrasi ini
 * pada pemasangan baru tidak menghilangkan apa pun.
 */
return new class extends Migration
{
    protected $connection = 'olap';

    public function up(): void
    {
        if (! Schema::connection('olap')->hasTable('dim_prodi')
            || ! Schema::connection('olap')->hasColumn('dim_prodi', 'akreditasi_prodi')) {
            return;
        }

        DB::connection('olap')->statement(
            'ALTER TABLE public.dim_prodi ALTER COLUMN akreditasi_prodi TYPE VARCHAR(30)'
        );
    }

    /**
     * Postgres menolak penyempitan kalau ada peringkat yang lebih dari 10
     * huruf, dan itu disengaja: rollback gagal terang-terangan lebih baik
     * daripada "Baik Sekali" terpotong diam-diam menjadi "Baik Sekal".
     */
    public function down(): void
    {
        if (! Schema::connection('olap')->hasTable('dim_prodi')
            || ! Schema::connection('olap')->hasColumn('dim_prodi', 'akreditasi_prodi')) {
            return;
        }

        DB::connection('olap')->statement(
            'ALTER TABLE public.dim_prodi ALTER COLUMN akreditasi_prodi TYPE VARCHAR(10)'
        );
    }
};
