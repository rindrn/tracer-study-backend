<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Database tracer_study punya dua schema yang disediakan lewat jalur
     * berbeda:
     *
     *   tracer_oltp  -> 40 file di database/migrations/transactional.
     *                   Koneksi 'oltp' (default), search_path = tracer_oltp.
     *                   Dibuat oleh `php artisan migrate`, diisi seeder di
     *                   bawah.
     *
     *   public       -> database/dump/olap_schema.sql (rangka star schema +
     *                   data config kpi_category_mapping). Koneksi 'olap',
     *                   search_path = public. TIDAK punya migration sama
     *                   sekali, jadi tanpa import ini seluruh dashboard OLAP
     *                   error saat query.
     *
     * Isi 11 dim_* dan 3 fact_* sengaja TIDAK dibawa di dump: itu data
     * turunan yang dihitung ulang oleh `php artisan etl:run` dari
     * tracer_oltp. Membawanya hanya akan membuat OLAP berisi hasil olahan
     * lama yang tidak cocok dengan isi OLTP hasil seeder di bawah.
     *
     * Schema ketiga, dev_pre_aggregations, dikelola sendiri oleh Cube.js
     * (dipakai FE) dan sengaja tidak disentuh di sini.
     *
     * Urutan: import OLAP dulu, baru seeder OLTP. Keduanya independen,
     * tapi import OLAP menjatuhkan seluruh schema public sehingga lebih
     * aman dijalankan sebelum data OLTP ditulis.
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

        $this->call([
            ProgramSeeder::class,
            // Setelah ProgramSeeder: daftar induk jurusan diturunkan dari
            // nilai yang dipakai program studi. Lihat catatan di kelasnya
            // soal kenapa pengisian di migration saja tidak cukup.
            JurusanSeeder::class,
            ProvinceSeeder::class,
            CitySeeder::class,
            RoleSeeder::class,
            UserSeeder::class,
            PermissionSeeder::class,
            QuestionnaireSeeder::class,
            AlumniProfileSeeder::class,
            QuestionnaireAssignmentSeeder::class,
            ResponseSeeder::class,
            StakeholderContactSeeder::class,
        ]);

        $this->importSql(
            'dump/oltp_master_data.sql',
            'data master OLTP (lams, thresholds)',
            'OLTP',
        );

        $this->importOltpSupplement();

        $this->registerBulanSesudahLulusRole();
    }

    /**
     * FR-026: dump/oltp_supplement.sql adalah snapshot lama yang belum
     * mengenal role "bulan_sesudah_lulus" (f303). Migration
     * 2026_08_06_000001_map_f303_to_bulan_sesudah_lulus no-op di install
     * fresh karena jalan SEBELUM tabel semantic_role_registry/
     * question_semantic_mapping ada (baru dibuat oleh importOltpSupplement
     * di atas). Tanpa langkah ini, AlumniFactBuilderService selalu
     * menghasilkan bulan_sesudah_lulus = null pada instalasi fresh.
     */
    private function registerBulanSesudahLulusRole(): void
    {
        $db = DB::connection('oltp');

        if (!$db->getSchemaBuilder()->hasTable('semantic_role_registry')) {
            return;
        }

        $exists = $db->table('semantic_role_registry')->where('role_key', 'bulan_sesudah_lulus')->exists();
        if (!$exists) {
            $db->table('semantic_role_registry')->insert([
                'role_key'            => 'bulan_sesudah_lulus',
                'label'               => 'Bulan Sesudah Lulus Mulai Cari Kerja',
                'category'            => 'waktu_tunggu',
                'description'         => 'Jumlah bulan sesudah lulus alumni mulai mencari kerja',
                'expected_kind'       => 'integer',
                'value_min'           => 0,
                'value_max'           => 60,
                'sample_valid_answer' => '2',
                'target_table'        => 'fact_tracer_study',
                'target_column'       => 'bulan_sesudah_lulus',
                'grain'               => 'narrow',
                'is_active'           => true,
                'created_at'          => now(),
                'updated_at'          => now(),
            ]);
        }

        $questionnaireIds = $db->table('questionnaire_questions')
            ->where('code', 'f303')
            ->pluck('questionnaire_id');

        foreach ($questionnaireIds as $questionnaireId) {
            $alreadyMapped = $db->table('question_semantic_mapping')
                ->where('questionnaire_id', $questionnaireId)
                ->where('question_code', 'f303')
                ->where('is_active', true)
                ->exists();

            if ($alreadyMapped) {
                continue;
            }

            $db->table('question_semantic_mapping')->insert([
                'questionnaire_id'       => $questionnaireId,
                'question_code'          => 'f303',
                'question_text_snapshot' => 'Kira-kira berapa bulan sesudah lulus Anda mulai mencari pekerjaan?',
                'semantic_role'          => 'bulan_sesudah_lulus',
                'grain'                  => 'narrow',
                'effective_date'         => now()->toDateString(),
                'is_active'              => true,
                'created_at'             => now(),
                'updated_at'             => now(),
            ]);
        }
    }

    /**
     * Import tabel tracer_oltp yang tidak punya migration.
     *
     * Sembilan tabel ini datang dari sisi OLAP/ETL (develop-form) yang dulu
     * hanya hidup di init.sql: question_semantic_mapping, semantic_role_registry,
     * ref_ump, etl_runs, threshold_configs, tracer_response_thresholds, plus
     * tiga tabel queue Laravel (QUEUE_CONNECTION=database, dipakai RunEtlJob).
     * Tanpa ini `php artisan etl:run` gagal: SemanticMappingRepository dan
     * dim_ump membaca langsung dari tabel-tabel tersebut.
     *
     * Dijalankan SETELAH seeder OLTP karena FK-nya menunjuk ke programs,
     * questionnaires, provinces, dan users yang baru dibuat seeder.
     *
     * Ini tambalan, bukan solusi akhir -- idealnya sembilan tabel ini
     * ditulis jadi migration sungguhan.
     */
    private function importOltpSupplement(): void
    {
        $this->importSql(
            'dump/oltp_supplement.sql',
            'tabel pelengkap OLTP',
            'OLTP',
        );
    }

    /**
     * Jalankan satu file .sql lewat psql pada koneksi yang diminta.
     *
     * @param string $relative Path relatif terhadap database_path().
     * @param string $label    Nama manusiawi untuk pesan konsol.
     * @param string $conn     'OLTP' atau 'OLAP' -- menentukan prefix env
     *                         mana yang dipakai untuk kredensial.
     */
    private function importSql(string $relative, string $label, string $conn): void
    {
        $path = database_path($relative);

        if (!is_file($path)) {
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
        passthru("psql {$dsn} -v ON_ERROR_STOP=1 -f " . escapeshellarg($path), $status);
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
            "psql {$dsn} -v ON_ERROR_STOP=1 -f " . escapeshellarg($path),
            $status
        );
        if ($status !== 0) {
            throw new RuntimeException('Import database/dump/olap_schema.sql gagal.');
        }

        $this->command?->info('Schema OLAP siap.');
    }
}
