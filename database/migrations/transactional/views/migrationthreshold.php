<?php
// database/migrations/xxxx_xx_xx_create_vw_thresholds_complete.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    protected $connection = 'oltp';

    public function up(): void
    {
        DB::connection('oltp')->statement("
            CREATE OR REPLACE VIEW vw_thresholds_complete AS
            SELECT
                t.id            AS threshold_id,
                t.name          AS threshold_name,
                t.value         AS threshold_value,
                t.unit          AS threshold_unit,
                t.operator      AS threshold_operator,
                t.created_at,
                t.updated_at,

                lv.id           AS lam_version_id,
                lv.year         AS lam_version_year,
                lv.version_name AS lam_version_name,
                lv.is_active    AS lam_version_is_active,

                l.id            AS lam_id,
                l.name          AS lam_name,
                l.code          AS lam_code

            FROM thresholds t

            JOIN lam_versions lv
                ON lv.id = t.lam_version_id

            JOIN lams l
                ON l.id = lv.lam_id
        ");
    }

    public function down(): void
    {
        DB::connection('oltp')->statement('DROP VIEW IF EXISTS vw_thresholds_complete');
    }
};