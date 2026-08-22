<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Selaraskan `dim_prodi` dengan Gambar 5.3 proposal.
 *
 * Gambar star schema di proposal menggambarkan dua atribut yang belum pernah
 * ada di tabel sungguhan:
 *
 *   - `nama_pt`          : nama perguruan tinggi pemilik program studi
 *   - `akreditasi_prodi` : peringkat akreditasi yang sedang berlaku
 *
 * Keduanya atribut dimensi, bukan ukuran, jadi tempatnya memang di dim_prodi
 * dan bukan di fact mana pun.
 *
 * `nama_pt` bernilai sama untuk seluruh baris pada satu pemasangan -- itu
 * disengaja. SmartTracer sekarang single-tenant, dan kolom ini fungsinya
 * sebagai jangkar: begitu kelak satu pemasangan melayani lebih dari satu PT,
 * kolomnya sudah ada beserta seluruh riwayat SCD-nya, tidak perlu membongkar
 * dimensi yang sudah berisi data.
 *
 * PENTING soal SCD Type 2. dim_prodi menyimpan versi (valid_from, valid_to,
 * flag_prodi). Baris yang sudah tutup (flag_prodi = false) SENGAJA tidak
 * ikut diisi ulang di sini: nilainya harus mencerminkan keadaan pada masa
 * berlakunya, dan kita tidak punya data historis akreditasi. Membiarkannya
 * NULL jujur; mengisinya dengan peringkat hari ini akan memalsukan sejarah.
 * Hanya versi yang sedang aktif yang diisi.
 *
 * PENJAGA PEMASANGAN BARU. Skema OLAP tidak dibangun oleh migrasi melainkan
 * oleh `database/dump/olap_schema.sql` yang diimpor DatabaseSeeder, sedangkan
 * README menyuruh `php artisan migrate` dijalankan SEBELUM `db:seed`. Di
 * basis data yang masih kosong, `dim_prodi` karena itu belum ada dan migrasi
 * ini mati dengan SQLSTATE[42P01], menghentikan seluruh rangkaian migrasi.
 * Dump-nya sendiri sudah memuat bentuk akhir kolom ini, jadi pemasangan baru
 * tidak kehilangan apa pun bila migrasi ini dilewati -- yang membutuhkannya
 * hanya pemasangan lama yang skemanya terlanjur dibuat sebelum perubahan ini.
 */
return new class extends Migration
{
    protected $connection = 'olap';

    public function up(): void
    {
        if (! Schema::connection('olap')->hasTable('dim_prodi')) {
            return;
        }

        Schema::connection('olap')->table('dim_prodi', function (Blueprint $table) {
            $table->string('nama_pt', 150)->nullable()->after('jurusan');
            $table->string('akreditasi_prodi', 30)->nullable()->after('nama_pt');
        });

        // Isi versi aktif supaya dashboard tidak menampilkan kolom kosong
        // sampai ETL mingguan berikutnya jalan. Lihat catatan SCD di atas:
        // versi yang sudah tutup dibiarkan NULL dengan sengaja.
        $namaPt = config('institution.name');

        if ($namaPt) {
            DB::connection('olap')->table('dim_prodi')
                ->where('flag_prodi', true)
                ->update(['nama_pt' => $namaPt]);
        }
    }

    public function down(): void
    {
        if (! Schema::connection('olap')->hasTable('dim_prodi')) {
            return;
        }

        Schema::connection('olap')->table('dim_prodi', function (Blueprint $table) {
            $table->dropColumn(['nama_pt', 'akreditasi_prodi']);
        });
    }
};
