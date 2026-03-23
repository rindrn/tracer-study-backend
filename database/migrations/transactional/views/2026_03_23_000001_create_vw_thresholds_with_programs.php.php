<?php
 
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
 
return new class extends Migration
{
    protected $connection = 'oltp';
 
    public function up(): void
    {
        DB::connection('oltp')->statement("
            CREATE OR REPLACE VIEW vw_thresholds_with_programs AS
            SELECT
                t.id           AS threshold_id,
                t.name         AS threshold_name,
                t.value        AS threshold_value,
                t.created_by,
                t.created_at,
                t.updated_at,
                COALESCE(
                    JSON_AGG(
                        JSON_BUILD_OBJECT(
                            'id',     p.id,
                            'name',   p.name,
                            'code',   p.code,
                            'degree', p.degree
                        ) ORDER BY p.name
                    ) FILTER (WHERE p.id IS NOT NULL),
                    '[]'::json
                ) AS programs
            FROM thresholds t
            LEFT JOIN threshold_programs tp ON t.id = tp.threshold_id
            LEFT JOIN programs           p  ON tp.program_id = p.id
            GROUP BY t.id, t.name, t.value,
                     t.created_by, t.created_at, t.updated_at
        ");
    }
 
    public function down(): void
    {
        DB::connection('oltp')
            ->statement('DROP VIEW IF EXISTS vw_thresholds_with_programs');
    }
};
