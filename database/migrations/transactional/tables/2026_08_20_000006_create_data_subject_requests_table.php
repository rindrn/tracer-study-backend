<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Permintaan alumni atas datanya sendiri (hak subjek data, UU 27/2022).
 *
 * UU PDP memberi subjek data hak untuk mengakses, memperbaiki, dan meminta
 * penghapusan datanya (Pasal 6, 8, dan 11). Hak akses (Pasal 7) sudah dilayani
 * langsung oleh endpoint "Data Saya" tanpa perlu antrean. Tiga hak lainnya
 * tidak bisa dijalankan otomatis, dan itu disengaja:
 *
 *   - Koreksi data akademik (NIM, prodi, tahun lulus) tidak boleh dilakukan
 *     sendiri oleh alumni — angka keterserapan per prodi ikut bergeser kalau
 *     alumni bisa memindahkan dirinya ke prodi lain.
 *   - Penghapusan berbenturan dengan kewajiban pelaporan PDDIKTI. Yang bisa
 *     dihapus dan yang wajib disimpan harus ditimbang manusia, per kasus.
 *
 * Maka bentuknya permintaan yang ditinjau Ketua Tracer, bukan tombol yang
 * langsung mengubah data. Yang dijamin sistem adalah permintaannya tercatat,
 * tidak bisa hilang diam-diam, dan punya jawaban tertulis.
 *
 * KENAPA TABEL SENDIRI, BUKAN MENUMPANG `approvals`
 * -------------------------------------------------
 * `approvals` mengantre permintaan ANTAR STAF (Tim Tracer meminta izin Ketua
 * Tracer menambah atau menghapus kuesioner). Pemohonnya user, dan tipe
 * permintaannya berputar di sekitar kuesioner. Permintaan di sini datang
 * dari alumni, tunduk pada tenggat hukum, dan riwayatnya harus bertahan
 * meski kuesionernya sudah lama ditutup. Menumpangkannya akan memaksa
 * `approvals.requested_by` menerima dua jenis pemohon dan membuat setiap
 * query di kedua alur harus menyaring tipe lebih dulu.
 */
return new class extends Migration
{
    protected $connection = 'oltp';

    public function up(): void
    {
        Schema::connection('oltp')->create('data_subject_requests', function (Blueprint $table) {
            $table->id();

            $table->foreignId('alumni_id')
                ->constrained('alumni_profiles')
                ->cascadeOnDelete();

            // 'correction' : minta perbaikan data yang keliru
            // 'erasure'    : minta penghapusan data
            // 'objection'  : keberatan atas pemrosesan tertentu
            $table->string('type', 20);

            $table->text('message');

            // 'pending' → 'in_review' → 'fulfilled' | 'rejected'
            $table->string('status', 20)->default('pending');

            // Jawaban tertulis petugas. Wajib diisi saat status berpindah ke
            // 'rejected': penolakan tanpa alasan bukan jawaban, dan alumni
            // berhak tahu dasar penolakannya.
            $table->text('response')->nullable();

            // Tanpa kunci asing, sama alasannya dengan audit_logs: catatan
            // penanganan harus tetap terbaca setelah akun petugasnya dihapus.
            $table->unsignedBigInteger('handled_by')->nullable();
            $table->string('handled_by_label', 150)->nullable();
            $table->timestamp('handled_at')->nullable();

            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['alumni_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::connection('oltp')->dropIfExists('data_subject_requests');
    }
};
