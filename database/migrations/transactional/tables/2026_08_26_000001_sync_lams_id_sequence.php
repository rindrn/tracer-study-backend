<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Perbaikan preventif: baris `lams` yang di-provisioning lewat restore/import
 * data (bukan lewat Eloquent/seeder) meninggalkan sequence `lams_id_seq`
 * tertinggal di belakang MAX(id) -- INSERT berikutnya lewat aplikasi (mis.
 * "Tambah LAM" di Threshold Management) lalu gagal HTTP 500 dengan
 * "duplicate key value violates unique constraint lams_pkey" karena
 * PostgreSQL mengulang nomor yang sudah terpakai.
 *
 * setval ini idempoten -- aman dijalankan berkali-kali, dan tidak berefek
 * kalau sequence memang sudah sinkron.
 */
return new class extends Migration
{
    protected $connection = 'oltp';

    public function up(): void
    {
        DB::connection('oltp')->statement(
            "SELECT setval(pg_get_serial_sequence('lams', 'id'), COALESCE((SELECT MAX(id) FROM lams), 1))"
        );
    }

    public function down(): void
    {
        // Tidak ada rollback yang bermakna untuk penyelarasan sequence.
    }
};
