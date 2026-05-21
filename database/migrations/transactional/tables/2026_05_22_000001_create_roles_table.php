<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    protected $connection = 'oltp';

    public function up(): void
    {
        Schema::connection('oltp')->create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();       // e.g. head_tracer
            $table->string('label', 100);               // e.g. Super Admin (Ketua Tracer)
            $table->string('description', 255)->nullable();
            $table->timestamps();
        });

        // Seed existing roles
        DB::connection('oltp')->table('roles')->insert([
            ['name' => 'head_tracer', 'label' => 'Super Admin (Ketua Tracer)', 'description' => 'Full system access', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'tracer_team', 'label' => 'Admin (Tim Tracer)', 'description' => 'Kelola & edit kuesioner', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'wadir', 'label' => 'Pimpinan (Direktur/Wadir/P2MPP)', 'description' => 'Viewer seluruh data institusi', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'kajur', 'label' => 'Ketua Jurusan', 'description' => 'Viewer data jurusan', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'kaprodi', 'label' => 'Ketua Program Studi', 'description' => 'Viewer data program studi', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::connection('oltp')->dropIfExists('roles');
    }
};
