<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Dukungan "batalkan pengiriman" + "pulihkan progres setelah refresh" untuk
 * halaman Manajemen Email:
 *
 * - `created_by` merekam admin yang memulai batch -- dipakai
 *   GET /alumni/email-batches/active untuk menemukan kembali batch yang
 *   masih berjalan milik admin yang sedang login saat halaman di-refresh
 *   (tanpa ini, `batch_id` yang dibangkitkan di localStorage/state
 *   komponen hilang begitu direfresh dan progres tidak bisa dipulihkan).
 * - Status `canceled` ditambahkan ke constraint yang sudah ada supaya baris
 *   `queued` yang belum diproses worker bisa ditandai batal lewat
 *   POST /alumni/email-batches/{batchId}/cancel -- lihat
 *   App\Jobs\SendAlumniAccountEmailJob::handle() dan
 *   App\Jobs\SendAlumniReminderEmailJob::handle(), yang mengecek status
 *   baris sebelum benar-benar mengirim SMTP.
 */
return new class extends Migration
{
    protected $connection = 'oltp';

    public function up(): void
    {
        DB::connection('oltp')->statement(
            'ALTER TABLE alumni_email_log ADD COLUMN created_by bigint NULL REFERENCES users(id) ON DELETE SET NULL'
        );
        DB::connection('oltp')->statement(
            'CREATE INDEX ix_alumni_email_log_created_by_kind ON alumni_email_log (created_by, kind, batch_id)'
        );

        DB::connection('oltp')->statement(
            'ALTER TABLE alumni_email_log DROP CONSTRAINT IF EXISTS alumni_credential_email_log_status_check'
        );
        DB::connection('oltp')->statement(
            "ALTER TABLE alumni_email_log ADD CONSTRAINT alumni_email_log_status_check CHECK (status IN ('queued','sent','failed','canceled'))"
        );
    }

    public function down(): void
    {
        DB::connection('oltp')->statement(
            'ALTER TABLE alumni_email_log DROP CONSTRAINT IF EXISTS alumni_email_log_status_check'
        );
        DB::connection('oltp')->statement(
            "ALTER TABLE alumni_email_log ADD CONSTRAINT alumni_credential_email_log_status_check CHECK (status IN ('queued','sent','failed'))"
        );
        DB::connection('oltp')->statement(
            'DROP INDEX IF EXISTS ix_alumni_email_log_created_by_kind'
        );
        DB::connection('oltp')->statement(
            'ALTER TABLE alumni_email_log DROP COLUMN IF EXISTS created_by'
        );
    }
};
