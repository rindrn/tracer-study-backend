<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ambang response rate hasil hitungan Slovin per prodi per tahun lulusan —
 * sebelumnya hanya ada di database/dump/oltp_supplement.sql.
 *
 * Diisi TracerResponseCalculationService, dibaca ThresholdRepository.
 * `graduated_year` adalah Tahun Lulusan (bukan tahun pelaksanaan kuesioner).
 * Lihat catatan di 2026_08_11_000001_create_semantic_role_registry_table.php
 * soal kenapa ditulis sebagai SQL mentah.
 */
return new class extends Migration
{
    protected $connection = 'oltp';

    public function up(): void
    {
        DB::connection('oltp')->statement("
            CREATE TABLE tracer_response_thresholds (
                id              bigint GENERATED ALWAYS AS IDENTITY,
                program_id      bigint        NOT NULL,
                graduated_year  integer       NOT NULL,
                total_lulusan   integer       NOT NULL,
                margin_error    numeric(5,4)  DEFAULT 0.023 NOT NULL,
                min_responden   integer       NOT NULL,
                threshold_value numeric(5,2)  NOT NULL,
                calculated_at   timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
                CONSTRAINT tracer_response_thresholds_pkey PRIMARY KEY (id),
                CONSTRAINT tracer_response_thresholds_program_id_graduated_year_key
                    UNIQUE (program_id, graduated_year),
                CONSTRAINT tracer_response_thresholds_program_id_fkey
                    FOREIGN KEY (program_id) REFERENCES programs (id) ON DELETE CASCADE
            )
        ");

        DB::connection('oltp')->statement("
            CREATE INDEX idx_tracer_response_thresholds_year
                ON tracer_response_thresholds (graduated_year)
        ");
    }

    public function down(): void
    {
        Schema::connection('oltp')->dropIfExists('tracer_response_thresholds');
    }
};
