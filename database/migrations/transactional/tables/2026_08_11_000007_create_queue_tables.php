<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel antrean bawaan Laravel (jobs, job_batches, failed_jobs) — sebelumnya
 * hanya ada di database/dump/oltp_supplement.sql.
 *
 * Dipakai RunEtlJob dan email blast. Ketiganya digabung dalam satu berkas
 * mengikuti bawaan Laravel, karena selalu dipasang bersama.
 *
 * SQL mentah supaya identik dengan init.sql: di sana `id` memakai IDENTITY,
 * sedangkan Blueprint id() menghasilkan bigserial. Beda cara, tapi kalau
 * dibiarkan berbeda maka instalasi dari dump dan dari basis data kosong tidak
 * lagi menghasilkan skema yang sama.
 */
return new class extends Migration
{
    protected $connection = 'oltp';

    public function up(): void
    {
        DB::connection('oltp')->statement("
            CREATE TABLE jobs (
                id           bigint GENERATED ALWAYS AS IDENTITY,
                queue        character varying(255) NOT NULL,
                payload      text     NOT NULL,
                attempts     smallint NOT NULL,
                reserved_at  integer,
                available_at integer  NOT NULL,
                created_at   integer  NOT NULL,
                CONSTRAINT jobs_pkey PRIMARY KEY (id)
            )
        ");

        DB::connection('oltp')->statement("
            CREATE INDEX ix_jobs_queue ON jobs (queue)
        ");

        DB::connection('oltp')->statement("
            CREATE TABLE job_batches (
                id             character varying(255) NOT NULL,
                name           character varying(255) NOT NULL,
                total_jobs     integer NOT NULL,
                pending_jobs   integer NOT NULL,
                failed_jobs    integer NOT NULL,
                failed_job_ids text    NOT NULL,
                options        text,
                cancelled_at   integer,
                created_at     integer NOT NULL,
                finished_at    integer,
                CONSTRAINT job_batches_pkey PRIMARY KEY (id)
            )
        ");

        DB::connection('oltp')->statement("
            CREATE TABLE failed_jobs (
                id         bigint GENERATED ALWAYS AS IDENTITY,
                uuid       character varying(255) NOT NULL,
                connection text NOT NULL,
                queue      text NOT NULL,
                payload    text NOT NULL,
                exception  text NOT NULL,
                failed_at  timestamp without time zone DEFAULT now() NOT NULL,
                CONSTRAINT failed_jobs_pkey PRIMARY KEY (id),
                CONSTRAINT failed_jobs_uuid_key UNIQUE (uuid)
            )
        ");
    }

    public function down(): void
    {
        Schema::connection('oltp')->dropIfExists('failed_jobs');
        Schema::connection('oltp')->dropIfExists('job_batches');
        Schema::connection('oltp')->dropIfExists('jobs');
    }
};
