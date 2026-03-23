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
            $table->enum('role', ['admin', 'p2mpp', 'prodi']);
 
            // FK ke programs.id — nullable karena admin/p2mpp tidak punya prodi
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
