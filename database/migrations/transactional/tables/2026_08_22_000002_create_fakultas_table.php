<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fakultas sebagai entity master data (redesain scope Dekan).
 *
 * Tidak butuh backfill: akun `dekan` yang ada sekarang murni data
 * uji coba sesi sebelumnya, aman dibuat ulang manual lewat Master Data
 * setelah migrasi ini jalan.
 */
return new class extends Migration
{
    protected $connection = 'oltp';

    public function up(): void
    {
        Schema::connection('oltp')->create('fakultas', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('oltp')->dropIfExists('fakultas');
    }
};
