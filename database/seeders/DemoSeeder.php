<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Isi basis data dengan data karangan (Faker) untuk pengembangan dan pengujian.
 *
 *     php artisan db:seed --class=DemoSeeder
 *
 * Dulu semua ini bagian dari DatabaseSeeder. Dipisah setelah instalasi baku
 * beralih memakai data asli dari database/dump/oltp_real_data.sql.
 *
 * JANGAN dijalankan di atas basis data yang sudah berisi data asli.
 * QuestionnaireSeeder membuat kuesioner baru dengan `code` yang sama persis
 * (DIKTI_2026_v1..v3 dan XXX_2026_v1..v3 per prodi), sehingga templatenya
 * tergandakan. Jawaban di response_answers terhubung lewat question_code, bukan
 * foreign key, jadi penggandaan itu tidak ditolak basis data -- diam-diam saja
 * membuat laporan menghitung ganda.
 *
 * Pakai di basis data yang baru `php artisan migrate` dan masih kosong.
 */
class DemoSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
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

        // Hanya konfigurasi (LAM, ambang, UMP, peran semantik) -- BUKAN
        // oltp_master_data.sql. Berkas master berisi programs, users, dan
        // template kuesioner yang di jalur demo justru sudah dibuat seeder
        // Faker di atas, jadi memuatnya akan menggandakan semuanya.
        $this->importSql('dump/oltp_config_data.sql');

        $this->registerBulanSesudahLulusRole();
    }

    /**
     * FR-026: kuesioner hasil QuestionnaireSeeder di atas baru saja dibuat,
     * sehingga f303 belum punya pemetaan semantik apa pun -- migration
     * 2026_08_06_000001_map_f303_to_bulan_sesudah_lulus sudah lewat jauh
     * sebelum kuesioner ini ada. Tanpa langkah ini AlumniFactBuilderService
     * selalu menghasilkan bulan_sesudah_lulus = null di instalasi demo.
     *
     * Di jalur instalasi baku langkah ini tidak diperlukan: peran dan
     * pemetaannya sudah ikut terbawa di oltp_master_data.sql.
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
     * Muat satu berkas .sql lewat psql pada koneksi OLTP.
     */
    private function importSql(string $relative): void
    {
        $path = database_path($relative);

        if (!is_file($path)) {
            $this->command?->warn("Lewati {$relative} -- berkasnya tidak ada.");

            return;
        }

        putenv('PGPASSWORD=' . env('OLTP_DB_PASSWORD'));

        $dsn = sprintf(
            '-h %s -p %s -U %s -d %s',
            escapeshellarg(env('OLTP_DB_HOST', '127.0.0.1')),
            escapeshellarg(env('OLTP_DB_PORT', '5432')),
            escapeshellarg(env('OLTP_DB_USERNAME', 'postgres')),
            escapeshellarg(env('OLTP_DB_DATABASE', 'study_tracer')),
        );

        $this->command?->info("Mengimpor {$relative}...");
        passthru("psql {$dsn} -v ON_ERROR_STOP=1 -q -f " . escapeshellarg($path), $status);
    }
}
