<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Keanggotaan eksplisit Fakultas → Jurusan.
 *
 * Berhenti di level Jurusan (bukan sampai ke Program) sesuai keputusan:
 * Fakultas membawahi beberapa Jurusan, dan program-program di dalamnya
 * ikut otomatis lewat `jurusan_program_scopes` yang sudah ada
 * (User::scopedProgramIds() yang merangkainya).
 */
return new class extends Migration
{
    protected $connection = 'oltp';

    public function up(): void
    {
        Schema::connection('oltp')->create('fakultas_jurusan_scopes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fakultas_id')
                  ->constrained('fakultas')
                  ->cascadeOnDelete();
            $table->foreignId('jurusan_id')
                  ->constrained('jurusans')
                  ->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['fakultas_id', 'jurusan_id']);
        });
    }

    public function down(): void
    {
        Schema::connection('oltp')->dropIfExists('fakultas_jurusan_scopes');
    }
};
