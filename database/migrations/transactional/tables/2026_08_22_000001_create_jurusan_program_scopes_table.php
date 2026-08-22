<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Keanggotaan eksplisit Jurusan → Program Studi (redesain scope Kajur).
 *
 * Sebelumnya Kajur mendapat prodi lewat COCOKKAN TEKS `programs.jurusan`
 * dengan `users.jurusan` -- bukan assignment sungguhan. Tabel ini
 * menjadikan keanggotaan itu eksplisit dan dikelola admin (Master Data),
 * dengan FK asli ke `programs`, sejalan dengan pola `threshold_programs`.
 *
 * `programs.jurusan` (teks) TETAP ADA dan TIDAK disentuh -- masih dibaca
 * puluhan konsumen lain (ETL, laporan, dsb). Tabel ini murni menambah
 * lapisan otorisasi baru berbasis ID, bukan mengganti kolom teks itu.
 *
 * BACKFILL: diisi otomatis dari kondisi sekarang -- untuk tiap jurusan,
 * masukkan semua program yang `programs.jurusan`-nya cocok persis. Supaya
 * hari pertama setelah migrasi, hasil query "prodi milik jurusan X" via
 * tabel ini IDENTIK dengan hasil text-matching lama (dan tidak
 * meregresi 10 akun Kajur demo yang sudah ada). Admin bisa menyesuaikan
 * manual sesudahnya lewat Master Data.
 *
 * Backfill-nya sendiri TIDAK dijalankan di sini -- migration berjalan
 * sebelum seeder mengisi `jurusans`/`programs`, jadi query itu akan
 * selalu menyisipkan 0 baris di instalasi fresh. Lihat
 * `database\Seeders\JurusanProgramScopeSeeder`, dipanggil dari
 * `DatabaseSeeder` setelah data master ter-import.
 */
return new class extends Migration
{
    protected $connection = 'oltp';

    public function up(): void
    {
        Schema::connection('oltp')->create('jurusan_program_scopes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jurusan_id')
                  ->constrained('jurusans')
                  ->cascadeOnDelete();
            $table->foreignId('program_id')
                  ->constrained('programs')
                  ->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['jurusan_id', 'program_id']);
        });
    }

    public function down(): void
    {
        Schema::connection('oltp')->dropIfExists('jurusan_program_scopes');
    }
};
