<?php
// database/migrations/xxxx_xx_xx_create_ref_ump_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Jalankan di koneksi OLTP
    protected $connection = 'oltp';

    public function up(): void
    {
        Schema::connection('oltp')->create('ref_ump', function (Blueprint $table) {
            $table->id();
            $table->integer('tahun');
            // FK ke tabel provinces (sumber kebenaran nama provinsi)
            $table->unsignedInteger('province_id');
            // Denormalisasi nama untuk kemudahan ETL tanpa join
            $table->string('nama_provinsi', 100);
            $table->bigInteger('nilai_ump');          // Rupiah, tanpa desimal
            // BPS_API | IMPORT | MANUAL
            $table->string('sumber', 20)->default('MANUAL');
            $table->timestamps();

            $table->unique(['tahun', 'province_id']);

            $table->foreign('province_id')
                  ->references('id')
                  ->on('provinces')
                  ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::connection('oltp')->dropIfExists('ref_ump');
    }
};