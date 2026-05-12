<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'oltp';
 
    public function up(): void
    {
        Schema::connection('oltp')->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            // Catatan: Laravel $table->enum() di PostgreSQL diterjemahkan jadi
            // VARCHAR(255) + CHECK constraint, BUKAN native ENUM type.
            // Urutan role: admin (super), p2mpp (dashboard read-only), kaprodi (prodi),
            // head_tracer (kepala tracer), tracer_team (anggota), wadir (wakil direktur).
            $table->enum('role', [
                'admin',
                'p2mpp',
                'kaprodi',
                'head_tracer',
                'tracer_team',
                'wadir',
            ]);

            // FK ke programs.id — nullable karena hanya kaprodi yang terikat ke prodi.
            // admin / p2mpp / head_tracer / tracer_team / wadir selalu NULL.
            $table->foreignId('program_id')
                  ->nullable()
                  ->constrained('programs')   // FK ke programs.id di OLTP
                  ->nullOnDelete();            // jika prodi dihapus, user.program_id = NULL
 
            $table->rememberToken();
            $table->timestamps();
        });
    }
 
    public function down(): void
    {
        Schema::connection('oltp')->dropIfExists('users');
    }
};
