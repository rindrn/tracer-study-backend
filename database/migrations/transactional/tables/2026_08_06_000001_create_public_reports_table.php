<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Laporan Tracer Study tahunan yang diunggah Ketua Tracer untuk diakses publik.
 *
 * Berkasnya TIDAK disimpan di disk publik. file_path menunjuk ke disk privat
 * dan unduhan dilayani route publik yang mengecek is_published lebih dulu --
 * kalau ditaruh di disk publik, URL yang sudah tersebar tetap bisa diunduh
 * meski publikasinya dicabut, dan jumlah unduhan tidak bisa dihitung.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::connection('oltp')->create('public_reports', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200);
            $table->text('description')->nullable();

            // Tahun PELAKSANAAN tracer study, bukan tahun lulusan responden --
            // laporan diberi judul "Tahun Pelaksanaan 2025" mengikuti kebiasaan
            // situs lama. Bukan unique: satu tahun boleh punya beberapa dokumen.
            $table->unsignedSmallInteger('report_year');

            $table->string('file_path', 255);
            $table->string('file_name', 255);   // nama asli, dipakai saat diunduh
            $table->unsignedBigInteger('file_size');
            $table->string('mime_type', 100);

            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->unsignedInteger('download_count')->default(0);

            // Pengunggah boleh dihapus tanpa ikut menghapus laporannya --
            // laporan yang sudah publik tidak boleh hilang gara-gara akun staf
            // dinonaktifkan.
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            // Daftar publik selalu difilter is_published lalu diurutkan tahun.
            $table->index(['is_published', 'report_year']);
        });
    }

    public function down(): void
    {
        Schema::connection('oltp')->dropIfExists('public_reports');
    }
};
