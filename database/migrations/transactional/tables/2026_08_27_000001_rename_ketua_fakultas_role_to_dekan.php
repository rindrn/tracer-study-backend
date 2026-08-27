<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Rename role `ketua_fakultas` menjadi `dekan` (istilah baku). Seeder
 * (RoleSeeder/UserSeeder) sudah pakai nama baru lewat updateOrCreate
 * keyed by name/email, jadi tidak otomatis me-rename baris lama --
 * migrasi ini yang membereskan baris `ketua_fakultas` yang sudah ada.
 */
return new class extends Migration
{
    protected $connection = 'oltp';

    public function up(): void
    {
        $conn = DB::connection('oltp');

        $conn->table('roles')
            ->where('name', 'ketua_fakultas')
            ->update(['name' => 'dekan', 'label' => 'Dekan']);

        $conn->table('users')
            ->where('role', 'ketua_fakultas')
            ->update(['role' => 'dekan']);

        $conn->table('role_permissions')
            ->where('role', 'ketua_fakultas')
            ->update(['role' => 'dekan']);
    }

    public function down(): void
    {
        $conn = DB::connection('oltp');

        $conn->table('roles')
            ->where('name', 'dekan')
            ->update(['name' => 'ketua_fakultas', 'label' => 'Ketua Fakultas']);

        $conn->table('users')
            ->where('role', 'dekan')
            ->update(['role' => 'ketua_fakultas']);

        $conn->table('role_permissions')
            ->where('role', 'dekan')
            ->update(['role' => 'ketua_fakultas']);
    }
};
