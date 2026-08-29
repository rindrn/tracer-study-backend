<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Index `nim` di alumni_email_log -- dipakai lookup "status email terakhir
 * per alumni" (LEFT JOIN LATERAL) di
 * AlumniProfileRepository::paginateForAdminWithResponseStatus(), yang
 * mendukung kolom "Email Terakhir" di halaman Manajemen Email. Tanpa index
 * ini, tiap baris di tabel alumni (dipaginasi, bisa 100/halaman) memicu
 * scan penuh alumni_email_log untuk menemukan baris terbarunya.
 */
return new class extends Migration
{
    protected $connection = 'oltp';

    public function up(): void
    {
        DB::connection('oltp')->statement(
            'CREATE INDEX IF NOT EXISTS ix_alumni_email_log_nim ON alumni_email_log (nim, id DESC)'
        );
    }

    public function down(): void
    {
        DB::connection('oltp')->statement(
            'DROP INDEX IF EXISTS ix_alumni_email_log_nim'
        );
    }
};
