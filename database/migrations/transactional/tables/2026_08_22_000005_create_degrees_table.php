<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Jenjang program studi sebagai entity master data.
 *
 * Sebelumnya daftar jenjang tinggal di `config/academic.php` dan dikunci CHECK
 * constraint, sehingga menambah jenjang menuntut sunting kode plus migrasi
 * baru. Kampus yang jenjangnya di luar daftar harus menunggu rilis.
 *
 * KENAPA `code` DIKUNCI UNTUK BARIS BAWAAN
 * ----------------------------------------
 * `code` bukan sekadar tampilan: nilainya mengalir ke gudang data lewat
 * `ProdiDimService` dan disimpan apa adanya di `dim_prodi.jenjang`. Service
 * itu ikut membandingkan `jenjang` saat menentukan versi SCD, jadi mengganti
 * kode "D4" jadi "D-IV" akan menutup versi setiap prodi berjenjang itu dan
 * membuka versi baru — dasbor historis terbelah jadi sebelum dan sesudah,
 * tanpa galat apa pun. Karena itu baris bawaan hanya boleh diubah `label`,
 * `sort_order`, dan `is_active`-nya; `code` ditolak di lapisan service.
 *
 * `label` sebaliknya aman diubah sesuka kampus: gudang data tidak pernah
 * menyimpannya, ia murni lapisan tampilan.
 *
 * Nilai awalnya diambil dari `config/academic.php` supaya pemasangan yang
 * sudah jalan tidak kehilangan satu jenjang pun saat migrasi ini dijalankan.
 * Sesudah migrasi ini, tabel inilah sumber kebenarannya dan berkas config
 * turun peran jadi daftar bawaan untuk pemasangan baru.
 */
return new class extends Migration
{
    protected $connection = 'oltp';

    /**
     * Label panjang untuk jenjang yang penulisan lazimnya berbeda dari kodenya.
     * Yang tidak terdaftar memakai kodenya sebagai label.
     */
    private const LONG_LABELS = [
        'D1' => 'D-I',
        'D2' => 'D-II',
        'D3' => 'D-III',
        'D4' => 'D-IV',
        'S1' => 'S-1',
        'S2' => 'S-2',
        'S3' => 'S-3',
    ];

    public function up(): void
    {
        Schema::connection('oltp')->create('degrees', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('label', 50);
            $table->unsignedSmallInteger('sort_order')->default(0);

            // Menandai baris yang lahir dari daftar bawaan. Dipakai service
            // untuk menolak perubahan `code` dan penghapusan — bukan sekadar
            // hiasan antarmuka.
            $table->boolean('is_seeded')->default(false);

            // Menyembunyikan jenjang dari dropdown pembuatan prodi TANPA
            // menghapusnya. Prodi lama yang memakainya tetap sah, dan baris
            // `dim_prodi` yang sudah terlanjur terbentuk tetap muncul di
            // penyaring dasbor karena penyaring membaca gudang data, bukan
            // tabel ini.
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });

        $now  = now();
        $rows = [];

        foreach (config('academic.degrees', []) as $i => $code) {
            $rows[] = [
                'code'       => $code,
                'label'      => self::LONG_LABELS[$code] ?? $code,
                'sort_order' => ($i + 1) * 10,
                'is_seeded'  => true,
                'is_active'  => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows !== []) {
            DB::connection('oltp')->table('degrees')->insert($rows);
        }
    }

    public function down(): void
    {
        Schema::connection('oltp')->dropIfExists('degrees');
    }
};
