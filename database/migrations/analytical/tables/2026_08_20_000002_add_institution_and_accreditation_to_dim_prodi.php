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
 * PENJAGA. Skema OLAP tidak dibangun oleh migrasi melainkan oleh
 * `database/dump/olap_schema.sql` yang diimpor DatabaseSeeder, sedangkan
 * migrasi selalu jalan SEBELUM seeder. Akibatnya migrasi ini bisa menemui
 * `dim_prodi` dalam dua keadaan yang sama-sama bukan salah pemakai, dan
 * keduanya harus dilewati diam-diam:
 *
 *   - BELUM ADA (pemasangan baru, basis data kosong). Tanpa penjaga, mati
 *     dengan SQLSTATE[42P01].
 *   - SUDAH ADA BESERTA KOLOMNYA (`migrate:fresh` di pemasangan berjalan).
 *     `migrate:fresh` bekerja di koneksi default yang search_path-nya
 *     tracer_oltp, jadi dia mengosongkan tabel `migrations` TAPI tidak
 *     menyentuh schema public sama sekali. Migrasi ini karena itu dianggap
 *     belum pernah jalan padahal kolomnya masih terpasang, dan tanpa penjaga
 *     mati dengan SQLSTATE[42701] -- menghentikan seluruh rangkaian migrasi
 *     di tengah jalan dan meninggalkan basis data separuh termigrasi.
 *
 * Pengecekannya per kolom, bukan per tabel, supaya keadaan kedua ikut
 * tertangkap. Dump-nya sendiri sudah memuat bentuk akhir kolom ini, jadi
 * tidak ada yang hilang bila migrasi dilewati -- yang membutuhkannya hanya
 * pemasangan lama yang skemanya terlanjur dibuat sebelum perubahan ini.
 */
return new class extends Migration
{
    protected $connection = 'olap';

    public function up(): void
    {
        if (! Schema::connection('olap')->hasTable('dim_prodi')
            || Schema::connection('olap')->hasColumn('dim_prodi', 'nama_pt')) {
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
        // Kebalikan dari up(): di sini kolomnya harus ADA supaya ada yang
        // dijatuhkan. Pemasangan yang tidak pernah menjalankan up() -- karena
        // skema OLAP-nya datang dari dump -- tidak punya apa pun untuk
        // dibatalkan.
        if (! Schema::connection('olap')->hasTable('dim_prodi')
            || ! Schema::connection('olap')->hasColumn('dim_prodi', 'nama_pt')) {
            return;
        }

        Schema::connection('olap')->table('dim_prodi', function (Blueprint $table) {
            $table->dropColumn(['nama_pt', 'akreditasi_prodi']);
        });
    }
};
