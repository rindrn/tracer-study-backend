<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

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
        $this->importOlapSchema();

        // Sesudah importOlapSchema(), bukan sebelum: langkah itu melakukan
        // DROP SCHEMA public CASCADE, yang ikut menjatuhkan ekstensinya.
        $this->importSql(
            'dump/004_pg_trgm.sql',
            'ekstensi pg_trgm (deteksi pertanyaan bermakna sama)',
            'OLAP',
        );

        // Referensi, konfigurasi, akun staf, dan template kuesioner.
        // Urutan tabel di dalam berkasnya sudah mengikuti dependensi FK.
        $this->importSql(
            'dump/oltp_master_data.sql',
            'data master OLTP',
            'OLTP',
        );

        // Profil alumni dan seluruh jawaban tracer study. Opsional: berkas ini
        // berisi data pribadi nyata sehingga tidak ikut di repositori. Kalau
        // tidak ada, instalasi tetap berhasil -- hasilnya basis data berisi
        // master data saja, siap diisi lewat aplikasi.
        $this->importSql(
            'dump/oltp_real_data.sql',
            'data asli alumni dan jawaban',
            'OLTP',
            optional: true,
        );
    }

    /**
     * Jalankan satu berkas .sql lewat psql pada koneksi yang diminta.
     *
     * @param string $relative Path relatif terhadap database_path().
     * @param string $label    Nama manusiawi untuk pesan konsol.
     * @param string $conn     'OLTP' atau 'OLAP' -- menentukan prefix env
     *                         mana yang dipakai untuk kredensial.
     * @param bool   $optional true = berkas yang tidak ada dilewati dengan
     *                         peringatan, bukan menggagalkan seeding.
     */
    private function importSql(string $relative, string $label, string $conn, bool $optional = false): void
    {
        $path = database_path($relative);

        if (!is_file($path)) {
            if ($optional) {
                $this->command?->warn("Lewati {$relative} ({$label}) -- berkasnya tidak ada.");

                return;
            }

            throw new RuntimeException("File dump tidak ditemukan: {$path}");
        }

        putenv('PGPASSWORD=' . env("{$conn}_DB_PASSWORD"));

        $dsn = sprintf(
            '-h %s -p %s -U %s -d %s',
            escapeshellarg(env("{$conn}_DB_HOST", '127.0.0.1')),
            escapeshellarg(env("{$conn}_DB_PORT", '5432')),
            escapeshellarg(env("{$conn}_DB_USERNAME", 'postgres')),
            escapeshellarg(env("{$conn}_DB_DATABASE", 'study_tracer')),
        );

        $this->command?->info("Mengimpor {$relative} ({$label})...");
        passthru("psql {$dsn} -v ON_ERROR_STOP=1 -q -f " . escapeshellarg($path), $status);
        if ($status !== 0) {
            throw new RuntimeException("Import {$relative} gagal.");
        }
    }

    /**
     * Import schema OLAP (public) dari pg_dump.
     *
     * olap_schema.sql adalah dump POLOS -- tidak mengandung DROP apa pun, jadi
     * CREATE-nya hanya aman di schema kosong. `migrate:fresh` tidak membantu
     * di sini: dia bekerja di koneksi default (oltp / search_path
     * tracer_oltp) dan tidak menyentuh public sama sekali. Karena itu schema
     * public dijatuhkan eksplisit dulu supaya reseed berulang tidak gagal
     * "already exists" / "duplicate key".
     *
     * Drop ini aman terhadap tracking migration: tabel `migrations` milik
     * Laravel ada di tracer_oltp, bukan public.
     */
    private function importOlapSchema(): void
    {
        $path = database_path('dump/olap_schema.sql');

        if (!is_file($path)) {
            throw new RuntimeException("Dump OLAP tidak ditemukan: {$path}");
        }

        $dsn = sprintf(
            '-h %s -p %s -U %s -d %s',
            escapeshellarg(env('OLAP_DB_HOST', '127.0.0.1')),
            escapeshellarg(env('OLAP_DB_PORT', '5432')),
            escapeshellarg(env('OLAP_DB_USERNAME', 'postgres')),
            escapeshellarg(env('OLAP_DB_DATABASE', 'tracer_study')),
        );

        putenv('PGPASSWORD=' . env('OLAP_DB_PASSWORD'));

        $this->command?->info('Menjatuhkan dan membuat ulang schema public (OLAP)...');
        passthru(
            "psql {$dsn} -v ON_ERROR_STOP=1 -c "
            . escapeshellarg('DROP SCHEMA IF EXISTS public CASCADE; CREATE SCHEMA public;'),
            $status
        );
        if ($status !== 0) {
            throw new RuntimeException('Gagal reset schema public sebelum import OLAP.');
        }

        $this->command?->info('Mengimpor database/dump/olap_schema.sql...');
        passthru(
            "psql {$dsn} -v ON_ERROR_STOP=1 -q -f " . escapeshellarg($path),
            $status
        );
        if ($status !== 0) {
            throw new RuntimeException('Import database/dump/olap_schema.sql gagal.');
        }

        $this->command?->info('Schema OLAP siap.');
    }
}
