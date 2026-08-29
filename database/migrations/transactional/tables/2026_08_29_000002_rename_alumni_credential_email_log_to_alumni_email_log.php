<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Generalisasi alumni_credential_email_log -> alumni_email_log: menambah
 * kolom `kind` supaya satu tabel yang sama menampung DUA jenis email --
 * 'account' (Terbitkan Akun, sudah ada sejak migrasi
 * create_alumni_credential_email_log_table) dan 'reminder' (pengingat isi
 * kuesioner, baru) -- lihat App\Jobs\SendAlumniReminderEmailJob.
 *
 * SATU migrasi, bukan dua langkah (tambah kolom lalu migrasi data lalu
 * rename): tabel ini dibuat migrasi terpisah DI SESI YANG SAMA dan belum
 * pernah dideploy dengan data produksi di dalamnya, jadi tidak ada baris
 * lama yang perlu dijaga kompatibel.
 */
return new class extends Migration
{
    protected $connection = 'oltp';

    public function up(): void
    {
        DB::connection('oltp')->statement(
            'ALTER TABLE alumni_credential_email_log RENAME TO alumni_email_log'
        );

        DB::connection('oltp')->statement(
            "ALTER TABLE alumni_email_log ADD COLUMN kind character varying(20) NOT NULL DEFAULT 'account'"
        );
        // Default sementara di atas hanya untuk mengisi baris lama (kalau
        // ada) tanpa gagal -- begitu kolomnya ada, pemanggil BARU (baik
        // 'account' maupun 'reminder') selalu mengisinya eksplisit lewat
        // AlumniEmailLog::create(), jadi default tidak perlu dipertahankan.
        DB::connection('oltp')->statement(
            'ALTER TABLE alumni_email_log ALTER COLUMN kind DROP DEFAULT'
        );

        DB::connection('oltp')->statement(
            "ALTER TABLE alumni_email_log ADD CONSTRAINT alumni_email_log_kind_check CHECK (kind IN ('account','reminder'))"
        );

        DB::connection('oltp')->statement(
            'DROP INDEX IF EXISTS ix_alumni_credential_email_log_batch_status'
        );
        DB::connection('oltp')->statement(
            'CREATE INDEX ix_alumni_email_log_batch_status_kind ON alumni_email_log (batch_id, status, kind)'
        );

        DB::connection('oltp')->statement(
            "COMMENT ON TABLE alumni_email_log IS
            'Status pengiriman tiap email alumni (kind: account = Terbitkan Akun, reminder = pengingat isi kuesioner), dikelompokkan per batch_id -- dipoll FE lewat GET /api/alumni/email-batches/{batchId}.'"
        );
    }

    public function down(): void
    {
        DB::connection('oltp')->statement(
            'ALTER TABLE alumni_email_log DROP CONSTRAINT IF EXISTS alumni_email_log_kind_check'
        );
        DB::connection('oltp')->statement(
            'DROP INDEX IF EXISTS ix_alumni_email_log_batch_status_kind'
        );
        DB::connection('oltp')->statement(
            'ALTER TABLE alumni_email_log DROP COLUMN kind'
        );
        DB::connection('oltp')->statement(
            'CREATE INDEX ix_alumni_credential_email_log_batch_status ON alumni_email_log (batch_id, status)'
        );
        DB::connection('oltp')->statement(
            'ALTER TABLE alumni_email_log RENAME TO alumni_credential_email_log'
        );
    }
};
