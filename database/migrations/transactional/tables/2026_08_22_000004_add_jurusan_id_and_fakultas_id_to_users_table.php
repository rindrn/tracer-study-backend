<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tambah FK `jurusan_id` (dipakai Kajur) dan `fakultas_id` (dipakai Ketua
 * Fakultas) ke `users` -- pengganti otorisasi lama yang mengandalkan
 * cocokkan teks `users.jurusan` atau tabel per-user `user_jurusan_scopes`.
 *
 * Kolom teks `users.jurusan` TETAP ADA dan TIDAK disentuh -- masih dibaca
 * konsumen lain di luar jalur otorisasi analitik.
 *
 * BACKFILL: `jurusan_id` diisi untuk setiap user role=kajur dari
 * `jurusans.name = users.jurusan` (match teks yang sudah ada), supaya
 * 10 akun Kajur demo langsung punya scope tanpa perlu di-assign ulang
 * manual. `fakultas_id` sengaja TIDAK di-backfill (lihat migration
 * create_fakultas_table).
 */
return new class extends Migration
{
    protected $connection = 'oltp';

    public function up(): void
    {
        Schema::connection('oltp')->table('users', function (Blueprint $table) {
            $table->foreignId('jurusan_id')->nullable()->after('jurusan')
                  ->constrained('jurusans')->nullOnDelete();
            $table->foreignId('fakultas_id')->nullable()->after('jurusan_id')
                  ->constrained('fakultas')->nullOnDelete();
        });

        DB::connection('oltp')->statement(<<<SQL
            UPDATE users u
            SET jurusan_id = j.id
            FROM jurusans j
            WHERE u.role = 'kajur'
              AND u.jurusan IS NOT NULL
              AND j.name = u.jurusan
        SQL);
    }

    public function down(): void
    {
        Schema::connection('oltp')->table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('fakultas_id');
            $table->dropConstrainedForeignId('jurusan_id');
        });
    }
};
