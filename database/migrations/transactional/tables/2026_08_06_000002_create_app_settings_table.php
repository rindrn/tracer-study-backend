<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Pengaturan aplikasi berbentuk key-value.
 *
 * Dipakai pertama kali untuk rentang tahun lulusan yang ditampilkan di halaman
 * publik ("pengarsipan visual"): tahun di luar rentang disembunyikan supaya
 * halaman publik tidak menghitung seluruh angkatan tiap kali dibuka.
 *
 * Key-value dipilih ketimbang tabel satu-baris berkolom tetap karena
 * pengaturan halaman publik berikutnya (teks pengantar, jumlah laporan yang
 * tampil, dst.) akan menyusul, dan menambah baris jauh lebih murah daripada
 * menambah kolom lewat migration tiap kali. Konsekuensinya nilai selalu string
 * dan validasi tipenya jadi tanggung jawab service -- diterima sadar.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::connection('oltp')->create('app_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->text('value')->nullable();
            $table->string('description', 255)->nullable();
            $table->timestamps();
        });

        // Nilai awal mengikuti situs lama (penelusuranalumni.polban.ac.id
        // menampilkan 2018-2025), supaya halaman publik tidak kosong sebelum
        // admin sempat mengatur apa pun.
        $now = now();
        DB::connection('oltp')->table('app_settings')->insert([
            [
                'key'         => 'public_year_start',
                'value'       => '2018',
                'description' => 'Tahun lulusan paling awal yang ditampilkan di halaman publik',
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
            [
                'key'         => 'public_year_end',
                'value'       => '2025',
                'description' => 'Tahun lulusan paling akhir yang ditampilkan di halaman publik',
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::connection('oltp')->dropIfExists('app_settings');
    }
};
