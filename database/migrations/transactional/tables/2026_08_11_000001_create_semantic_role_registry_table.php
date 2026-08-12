<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Registry peran semantik — sebelumnya hanya ada di database/dump/oltp_supplement.sql.
 *
 * Ditulis sebagai SQL mentah, bukan Blueprint, dan itu disengaja. Tabel ini
 * sudah terlanjur ada di init.sql, jadi definisinya harus identik persis
 * supaya instalasi dari dump dan instalasi dari basis data kosong menghasilkan
 * skema yang sama. CHECK constraint dan COMMENT di bawah tidak punya padanan
 * langsung di Blueprint. DDL disalin apa adanya dari pg_dump.
 *
 * Untuk pemasangan dari init.sql, migrasi ini dicatat sebagai sudah jalan oleh
 * database/dump/005_reconcile_init.sql — jangan ubah namanya tanpa
 * memperbarui berkas itu.
 */
return new class extends Migration
{
    protected $connection = 'oltp';

    public function up(): void
    {
        DB::connection('oltp')->statement("
            CREATE TABLE semantic_role_registry (
                role_key            character varying(50)  NOT NULL,
                label               character varying(100) NOT NULL,
                category            character varying(50)  NOT NULL,
                description         text,
                expected_kind       character varying(20)  NOT NULL,
                value_min           numeric(14,2),
                value_max           numeric(14,2),
                sample_valid_answer character varying(200),
                target_table        character varying(100),
                target_column       character varying(100),
                grain               character varying(20)  DEFAULT 'narrow' NOT NULL,
                is_active           boolean                DEFAULT true     NOT NULL,
                created_at          timestamp without time zone DEFAULT now() NOT NULL,
                updated_at          timestamp without time zone DEFAULT now() NOT NULL,
                CONSTRAINT semantic_role_registry_pkey PRIMARY KEY (role_key),
                CONSTRAINT semantic_role_registry_expected_kind_check
                    CHECK (expected_kind IN ('integer','decimal','categorical','boolean','text','date')),
                CONSTRAINT semantic_role_registry_grain_check
                    CHECK (grain IN ('narrow','wide'))
            )
        ");

        DB::connection('oltp')->statement("
            COMMENT ON TABLE semantic_role_registry IS
            'Registry of valid semantic roles: data-type contract + target OLAP column + KPI-domain category for UI grouping.'
        ");

        DB::connection('oltp')->statement("
            COMMENT ON COLUMN semantic_role_registry.category IS
            'KPI domain used to group roles in the admin mapping dropdown (e.g. keterserapan, waktu_tunggu, pendapatan) -- distinct roles in different categories should never share a question_code by mistake.'
        ");

        DB::connection('oltp')->statement("
            COMMENT ON COLUMN semantic_role_registry.grain IS
            'narrow = feeds a single-valued fact_tracer_study column, one mapping per (questionnaire, role). wide = feeds fact_multi_select/fact_range_evaluasi, many question_codes legitimately share the role (uses dim_indikator_evaluasi for per-item identity instead of this table''''s uniqueness).'
        ");
    }

    public function down(): void
    {
        Schema::connection('oltp')->dropIfExists('semantic_role_registry');
    }
};
