<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Nilai parameter dinamis ambang per versi LAM — sebelumnya hanya ada di
 * database/dump/oltp_supplement.sql.
 *
 * Pasangan dari threshold_indicators.dynamic_param_unit: indikator menyatakan
 * satuannya, tabel ini menyimpan angkanya untuk tiap versi LAM.
 * Lihat catatan di 2026_08_11_000001_create_semantic_role_registry_table.php
 * soal kenapa ditulis sebagai SQL mentah.
 */
return new class extends Migration
{
    protected $connection = 'oltp';

    public function up(): void
    {
        DB::connection('oltp')->statement("
            CREATE TABLE threshold_configs (
                id             bigint GENERATED ALWAYS AS IDENTITY,
                lam_version_id bigint         NOT NULL,
                indicator_id   bigint         NOT NULL,
                param_value    numeric(12,2)  NOT NULL,
                created_at     timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP,
                updated_at     timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT threshold_configs_pkey PRIMARY KEY (id),
                CONSTRAINT threshold_configs_lam_version_id_indicator_id_key
                    UNIQUE (lam_version_id, indicator_id),
                CONSTRAINT threshold_configs_lam_version_id_fkey
                    FOREIGN KEY (lam_version_id) REFERENCES lam_versions (id) ON DELETE CASCADE,
                CONSTRAINT threshold_configs_indicator_id_fkey
                    FOREIGN KEY (indicator_id) REFERENCES threshold_indicators (id) ON DELETE RESTRICT
            )
        ");
    }

    public function down(): void
    {
        Schema::connection('oltp')->dropIfExists('threshold_configs');
    }
};
