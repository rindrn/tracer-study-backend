<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Satu baris per ALUMNI dalam satu "Terbitkan Akun" (kirim email bulk lewat
 * Brevo) -- lihat App\Jobs\SendAlumniAccountEmailJob dan
 * App\Http\Controllers\Api\Admin\AlumniCredentialEmailController.
 *
 * KENAPA PERLU TABEL INI, BUKAN CUKUP CACAH DI RESPONS JSON
 *
 * Endpoint /alumni/credentials/issue-email hanya MENGANTREKAN pengiriman --
 * ia kembali begitu job masuk tabel `jobs`, jauh sebelum SMTP benar-benar
 * dicoba. Berhasil atau gagalnya baru diketahui belakangan, di proses worker
 * yang terpisah dari request HTTP mana pun. Tanpa tabel ini satu-satunya
 * jejak kegagalan ada di `failed_jobs` (payload serial, tidak dikelompokkan
 * per sesi penerbitan) -- petugas tidak akan pernah tahu ALUMNI MANA yang
 * emailnya gagal terkirim tanpa menggali log secara manual.
 *
 * `batch_id` mengelompokkan seluruh potongan (chunk) satu sesi "Terbitkan
 * Akun" jadi satu kesatuan yang bisa di-poll frontend -- satu sesi bisa
 * terdiri dari beberapa panggilan /issue-email berurutan (kursor
 * `after_nim`), persis seperti /alumni/credentials/issue biasa.
 */
return new class extends Migration
{
    protected $connection = 'oltp';

    public function up(): void
    {
        DB::connection('oltp')->statement("
            CREATE TABLE alumni_credential_email_log (
                id            bigint GENERATED ALWAYS AS IDENTITY,
                batch_id      uuid NOT NULL,
                nim           character varying(30) NOT NULL,
                name          character varying(255),
                email         character varying(255) NOT NULL,
                status        character varying(20) DEFAULT 'queued' NOT NULL,
                error_message text,
                created_at    timestamp without time zone DEFAULT now() NOT NULL,
                updated_at    timestamp without time zone DEFAULT now() NOT NULL,
                CONSTRAINT alumni_credential_email_log_pkey PRIMARY KEY (id),
                CONSTRAINT alumni_credential_email_log_status_check
                    CHECK (status IN ('queued','sent','failed'))
            )
        ");

        // Dipakai keduanya: agregasi cacah per status (COUNT ... GROUP BY),
        // dan pengambilan baris `failed` untuk satu batch.
        DB::connection('oltp')->statement("
            CREATE INDEX ix_alumni_credential_email_log_batch_status
            ON alumni_credential_email_log (batch_id, status)
        ");

        DB::connection('oltp')->statement("
            COMMENT ON TABLE alumni_credential_email_log IS
            'Status pengiriman tiap email \"Terbitkan Akun\" per alumni, dikelompokkan per batch_id -- dipoll FE lewat GET /api/alumni/credentials/email-batches/{batchId} supaya petugas tahu PERSIS email mana yang gagal, bukan cuma cacah agregat.'
        ");

        DB::connection('oltp')->statement("
            COMMENT ON COLUMN alumni_credential_email_log.nim IS
            'Snapshot NIM saat email diantrekan -- bukan FK ke alumni_profiles, supaya baris log tetap terbaca walau data alumni berubah/terhapus belakangan.'
        ");
    }

    public function down(): void
    {
        Schema::connection('oltp')->dropIfExists('alumni_credential_email_log');
    }
};
