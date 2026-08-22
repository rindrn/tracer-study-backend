<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\ImportsSqlDumps;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;
    use ImportsSqlDumps;

    /**
     * Mengisi basis data yang baru saja dimigrasi dengan data sungguhan.
     *
     * Dua schema, dua jalur berbeda:
     *
     *   tracer_oltp  -> seluruh strukturnya milik migration di
     *                   database/migrations/transactional. Seeder ini TIDAK
     *                   membuat tabel apa pun, hanya memuat isinya dari dua
     *                   berkas data-only.
     *
     *   public       -> database/dump/olap_schema.sql (rangka star schema +
     *                   data konfigurasi kpi_category_mapping). Koneksi 'olap',
     *                   search_path = public. TIDAK punya migration sama
     *                   sekali, jadi tanpa import ini seluruh dashboard OLAP
     *                   error saat query.
     *
     * Isi 11 dim_* dan 3 fact_* sengaja TIDAK dibawa: itu data turunan yang
     * dihitung ulang `php artisan etl:run` dari tracer_oltp. Membawanya hanya
     * akan membuat OLAP berisi hasil olahan lama yang tidak cocok dengan OLTP.
     * Sesudah seeding, jalankan `php artisan etl:run` supaya dashboard terisi.
     *
     * Schema ketiga, dev_pre_aggregations, dikelola sendiri oleh Cube.js
     * (dipakai FE) dan sengaja tidak disentuh di sini.
     *
     * DATA KARANGAN SUDAH TIDAK DIPAKAI DI SINI. Seeder berbasis Faker
     * (alumni, respons, penugasan, kontak penilai) dipindah ke DemoSeeder dan
     * hanya jalan kalau diminta eksplisit:
     *
     *     php artisan db:seed --class=DemoSeeder
     *
     * Itu disengaja: template kuesioner di oltp_master_data.sql dan jawaban di
     * oltp_real_data.sql saling terkait lewat question_code. Menjalankan
     * QuestionnaireSeeder di atas data asli akan menggandakan `code` kuesioner.
     */
    public function run(): void
    {
        $this->prepareOlapSchema();

        // Referensi, konfigurasi, akun staf, dan template kuesioner.
        // Urutan tabel di dalam berkasnya sudah mengikuti dependensi FK.
        $this->importSqlFile(
            'dump/oltp_master_data.sql',
            'data master OLTP',
            'OLTP',
        );

        // Daftar role (termasuk yang belum ikut di dump lama seperti
        // ketua_fakultas). updateOrCreate, aman diulang.
        $this->call(RoleSeeder::class);

        // Backfill jurusan_program_scopes dari programs.jurusan (teks) --
        // harus jalan SESUDAH jurusans/programs terisi lewat import di atas.
        // Lihat komentar di JurusanProgramScopeSeeder.
        $this->call(JurusanProgramScopeSeeder::class);

        // Backfill users.jurusan_id (FK) dari users.jurusan (teks lama) --
        // dump master data dibuat sebelum kolom jurusan_id ada. Lihat
        // komentar di UserJurusanLinkSeeder.
        $this->call(UserJurusanLinkSeeder::class);

        // Profil alumni dan seluruh jawaban tracer study. Opsional: berkas ini
        // berisi data pribadi nyata sehingga tidak ikut di repositori. Kalau
        // tidak ada, instalasi tetap berhasil -- hasilnya basis data berisi
        // master data saja, siap diisi lewat aplikasi.
        $this->importSqlFile(
            'dump/oltp_real_data.sql',
            'data asli alumni dan jawaban',
            'OLTP',
            optional: true,
        );
    }
}
