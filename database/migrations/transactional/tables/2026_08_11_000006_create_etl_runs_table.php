<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Riwayat eksekusi ETL — sebelumnya hanya ada di
 * database/dump/oltp_supplement.sql.
 *
 * Lihat catatan di 2026_08_11_000001_create_semantic_role_registry_table.php
 * soal kenapa ditulis sebagai SQL mentah.
 */
return new class extends Migration
{
    protected $connection = 'oltp';

    public function up(): void
    {
        DB::connection('oltp')->statement("
            CREATE TABLE etl_runs (
                id            bigint GENERATED ALWAYS AS IDENTITY,
                status        character varying(20) DEFAULT 'queued' NOT NULL,
                reason        character varying(50) NOT NULL,
                triggered_by  bigint,
                id_waktu      integer,
                summary       jsonb,
                error_message text,
                started_at    timestamp without time zone,
                finished_at   timestamp without time zone,
                created_at    timestamp without time zone DEFAULT now() NOT NULL,
                updated_at    timestamp without time zone DEFAULT now() NOT NULL,
                CONSTRAINT etl_runs_pkey PRIMARY KEY (id),
                CONSTRAINT etl_runs_status_check
                    CHECK (status IN ('queued','running','completed','failed')),
                CONSTRAINT etl_runs_triggered_by_fkey
                    FOREIGN KEY (triggered_by) REFERENCES users (id)
            )
        ");

        DB::connection('oltp')->statement("
            CREATE INDEX ix_etl_runs_status ON etl_runs (status)
        ");

        DB::connection('oltp')->statement("
            COMMENT ON TABLE etl_runs IS
            'Satu baris per eksekusi ETL yang di-trigger lewat queue job (RunEtlJob) -- dipoll FE (GET /api/etl-runs/{id}) supaya ada UI loading yang jelas, bukan diam-diam menunggu tanpa status.'
        ");

        DB::connection('oltp')->statement("
            COMMENT ON COLUMN etl_runs.reason IS
            'Pemicu run ini, mis. langkah1_mapping_store, langkah1_mapping_deactivate, manual_cli.'
        ");

        // FK longgar lintas koneksi: dim_waktu ada di schema public (OLAP),
        // tidak bisa dijadikan constraint sungguhan dari sini.
        DB::connection('oltp')->statement("
            COMMENT ON COLUMN etl_runs.id_waktu IS
            'FK longgar (tanpa constraint lintas-koneksi) ke public.dim_waktu.id_waktu -- diisi EtlOrchestratorService::run() setelah snapshot baru berhasil dibuat.'
        ");
    }

    public function down(): void
    {
        Schema::connection('oltp')->dropIfExists('etl_runs');
    }
};
