<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Jurusan sebagai master data yang bisa dikelola (RBAC-02).
 *
 * Sebelumnya jurusan hanya kolom teks `programs.jurusan` tanpa daftar induk,
 * sehingga tidak ada tempat untuk menambah jurusan yang belum punya program
 * studi, dan daftar pilihannya di-hardcode di frontend -- daftar itu bahkan
 * tertinggal satu nilai ("Teknik Aeronautika") dibanding isi basis data.
 *
 * KEPUTUSAN: `programs.jurusan` SENGAJA DIPERTAHANKAN sebagai teks, tidak
 * diganti foreign key. Alasannya cakupan: 68 berkas backend membaca kolom
 * teks itu (termasuk seluruh repositori analitik, penyaring lingkup Kajur,
 * dan ETL ProdiDimService), ditambah `users.jurusan` dan `dim_prodi.jurusan`
 * di OLAP. Menggantinya dengan FK berarti menyentuh semuanya sekaligus demi
 * keuntungan yang tidak dirasakan pengguna.
 *
 * Konsekuensinya tabel ini adalah daftar induk, dan penggantian nama harus
 * merambat ke `programs.jurusan` serta `users.jurusan` dalam satu transaksi
 * -- itu tanggung jawab JurusanService, satu-satunya jalur tulis.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::connection('oltp')->create('jurusans', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Isi dari nilai yang sudah dipakai program studi, supaya daftar
        // induk tidak lahir kosong sementara datanya sudah ada.
        $existing = DB::connection('oltp')->table('programs')
            ->whereNotNull('jurusan')
            ->where('jurusan', '<>', '')
            ->distinct()
            ->orderBy('jurusan')
            ->pluck('jurusan');

        $now = now();
        foreach ($existing as $name) {
            DB::connection('oltp')->table('jurusans')->insert([
                'name'       => $name,
                'is_active'  => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::connection('oltp')->dropIfExists('jurusans');
    }
};
