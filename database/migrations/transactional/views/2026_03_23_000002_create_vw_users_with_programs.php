<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
 
return new class extends Migration
{
    protected $connection = 'oltp';
 
    public function up(): void
    {
        DB::connection('oltp')->statement("
            CREATE OR REPLACE VIEW vw_users_with_programs AS
            SELECT
                u.id,
                u.name,
                u.email,
                u.role,
                u.program_id,
                p.name   AS program_name,
                p.code   AS program_code,
                p.degree AS program_degree,
                u.created_at,
                u.updated_at
            FROM users u
            LEFT JOIN programs p ON u.program_id = p.id
        ");
    }
 
    public function down(): void
    {
        DB::connection('oltp')
            ->statement('DROP VIEW IF EXISTS vw_users_with_programs');
    }
};
