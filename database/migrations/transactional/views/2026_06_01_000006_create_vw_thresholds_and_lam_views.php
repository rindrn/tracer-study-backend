<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    protected $connection = 'oltp';

    public function up(): void
    {
        // Drop view lama
        DB::connection('oltp')
            ->statement('DROP VIEW IF EXISTS vw_thresholds_with_programs');

        // Buat view baru: vw_thresholds_complete
        DB::connection('oltp')->statement("
            CREATE VIEW vw_thresholds_complete AS
            SELECT
                t.id            AS threshold_id,
                t.value         AS threshold_value,
                t.level         AS threshold_level,
                t.created_at,
                ti.id           AS indicator_id,
                ti.key          AS indicator_key,
                ti.name         AS indicator_name,
                ti.unit         AS indicator_unit,
                ti.operator     AS indicator_operator,
                lv.id           AS lam_version_id,
                lv.year         AS lam_version_year,
                lv.version_name AS lam_version_name,
                lv.is_active    AS lam_version_is_active,
                l.id            AS lam_id,
                l.name          AS lam_name,
                l.code          AS lam_code
            FROM thresholds t
            JOIN threshold_indicators ti ON ti.id = t.indicator_id
            JOIN lam_versions lv         ON lv.id = t.lam_version_id
            JOIN lams l                  ON l.id  = lv.lam_id
        ");

        // Buat view baru: vw_lam_versions_complete
        DB::connection('oltp')->statement("
            CREATE VIEW vw_lam_versions_complete AS
            SELECT
                lv.id           AS lam_version_id,
                lv.year,
                lv.version_name,
                lv.is_active,
                l.id            AS lam_id,
                l.name          AS lam_name,
                l.code          AS lam_code,
                COALESCE(
                    json_agg(DISTINCT jsonb_build_object(
                        'id',     p.id,
                        'name',   p.name,
                        'code',   p.code,
                        'degree', p.degree
                    )) FILTER (WHERE p.id IS NOT NULL),
                    '[]'::json
                ) AS programs,
                COALESCE(
                    json_agg(DISTINCT jsonb_build_object(
                        'threshold_id',   t.id,
                        'indicator_id',   ti.id,
                        'indicator_key',  ti.key,
                        'indicator_name', ti.name,
                        'unit',           ti.unit,
                        'operator',       ti.operator,
                        'level',          t.level,
                        'value',          t.value
                    )) FILTER (WHERE t.id IS NOT NULL),
                    '[]'::json
                ) AS thresholds
            FROM lam_versions lv
            JOIN lams l                       ON l.id  = lv.lam_id
            LEFT JOIN lam_programs lp         ON lp.lam_id = l.id
            LEFT JOIN programs p              ON p.id  = lp.program_id
            LEFT JOIN thresholds t            ON t.lam_version_id = lv.id
            LEFT JOIN threshold_indicators ti ON ti.id = t.indicator_id
            GROUP BY lv.id, lv.year, lv.version_name, lv.is_active, l.id, l.name, l.code
        ");
    }

    public function down(): void
    {
        DB::connection('oltp')
            ->statement('DROP VIEW IF EXISTS vw_lam_versions_complete');
        DB::connection('oltp')
            ->statement('DROP VIEW IF EXISTS vw_thresholds_complete');
    }
};
