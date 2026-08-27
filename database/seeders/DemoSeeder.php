<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\ImportsSqlDumps;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Isi basis data dengan data karangan (Faker) untuk pengembangan dan pengujian.
 *
 *     php artisan db:seed --class=DemoSeeder
 *
 * Inilah jalur seeding BAKU: DatabaseSeeder tidak melakukan apa pun selain
 * memanggil kelas ini, sehingga `migrate:fresh --seed` berakhir di sini juga.
 * Data alumni sungguhan dipindah ke RealDataSeeder dan harus diminta
 * eksplisit -- lihat catatan di DatabaseSeeder soal alasannya.
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
    use ImportsSqlDumps;

    public function run(): void
    {
        // Rangka star schema OLAP tidak punya migration sama sekali, jadi
        // tanpa langkah ini seluruh query dashboard error di basis data yang
        // baru. Jalur demo harus bisa berdiri sendiri: satu perintah ini saja
        // sesudah `php artisan migrate`, tanpa psql manual.
        $this->prepareOlapSchema();

        $this->call([
            ProgramSeeder::class,
            // Setelah ProgramSeeder: daftar induk jurusan diturunkan dari
            // nilai yang dipakai program studi. Lihat catatan di kelasnya
            // soal kenapa pengisian di migration saja tidak cukup.
            JurusanSeeder::class,
            // Sumber otorisasi kajur/dekan. Backfill-nya dulu ada di
            // migration jurusan_program_scopes, dan di sana selalu mengisi nol
            // baris karena migration jalan sebelum seeder mana pun -- lihat
            // catatan di kelasnya. Jalur demo juga terkena, jadi ikut dipanggil
            // di sini, sesudah programs dan jurusans terisi.
            JurusanProgramScopeSeeder::class,
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
        $this->importSqlFile('dump/oltp_config_data.sql', 'konfigurasi LAM, ambang, dan UMP', 'OLTP');

        // Urutannya penting: ratakan dulu pemetaan bawaan dump ke seluruh
        // kuesioner global, baru daftarkan peran f303 yang memang tidak ada
        // di dump mana pun.
        $this->replicateSemanticMappings();
        $this->registerBulanSesudahLulusRole();
    }

    /**
     * Salin pemetaan semantik dump ke SELURUH kuesioner global.
     *
     * oltp_config_data.sql memasang 51 baris question_semantic_mapping yang
     * questionnaire_id-nya ditulis mati sebagai 1, 2, 3 -- angka yang cuma
     * benar selama QuestionnaireSeeder kebetulan membuat tepat tiga kuesioner
     * global. Begitu jumlah angkatan berubah, kuesioner ke-4 dan seterusnya
     * lahir tanpa pemetaan apa pun.
     *
     * Akibatnya tidak kelihatan sebagai error: AlumniFactBuilderService butuh
     * peran `status_pekerjaan` untuk mengisi status_alumni_sk yang NOT NULL,
     * tidak menemukannya, lalu MELEWATI response itu diam-diam. ETL tetap
     * "sukses", hanya angkanya yang diam-diam tinggal separuh.
     *
     * Karena seluruh kuesioner global dibuat seedGlobalQuestions() dengan
     * daftar pertanyaan yang identik, pemetaan itu sah disalin apa adanya
     * berdasarkan question_code.
     */
    private function replicateSemanticMappings(): void
    {
        $db = DB::connection('oltp');

        if (!$db->getSchemaBuilder()->hasTable('question_semantic_mapping')) {
            return;
        }

        // Kuesioner global = tanpa program_id. Yang punya pemetaan terbanyak
        // dijadikan contoh; sisanya menyusul.
        $globalIds = $db->table('questionnaires')->whereNull('program_id')->pluck('id');

        if ($globalIds->count() < 2) {
            return;
        }

        $templateId = $db->table('question_semantic_mapping')
            ->whereIn('questionnaire_id', $globalIds)
            ->where('is_active', true)
            ->select('questionnaire_id', DB::raw('count(*) as jml'))
            ->groupBy('questionnaire_id')
            ->orderByDesc('jml')
            ->value('questionnaire_id');

        if ($templateId === null) {
            return;
        }

        $template = $db->table('question_semantic_mapping')
            ->where('questionnaire_id', $templateId)
            ->where('is_active', true)
            ->get();

        $ditambahkan = 0;

        foreach ($globalIds as $targetId) {
            if ((int) $targetId === (int) $templateId) {
                continue;
            }

            // Pertanyaan yang benar-benar ada di kuesioner tujuan. Menyalin
            // pemetaan untuk kode yang tidak ada di sana hanya akan jadi baris
            // yatim yang tidak pernah terpakai.
            $kodeTersedia = $db->table('questionnaire_questions')
                ->where('questionnaire_id', $targetId)
                ->pluck('code')
                ->flip();

            // Indeks uq_qsm_active_code dan uq_qsm_active_narrow_role menolak
            // duplikat, jadi keduanya disaring lebih dulu.
            $kodeTerpakai = $db->table('question_semantic_mapping')
                ->where('questionnaire_id', $targetId)
                ->where('is_active', true)
                ->pluck('question_code')
                ->flip();

            $peranNarrowTerpakai = $db->table('question_semantic_mapping')
                ->where('questionnaire_id', $targetId)
                ->where('is_active', true)
                ->where('grain', 'narrow')
                ->pluck('semantic_role')
                ->flip();

            $baris = [];

            foreach ($template as $map) {
                if (!$kodeTersedia->has($map->question_code) || $kodeTerpakai->has($map->question_code)) {
                    continue;
                }

                if ($map->grain === 'narrow' && $peranNarrowTerpakai->has($map->semantic_role)) {
                    continue;
                }

                if ($map->grain === 'narrow') {
                    $peranNarrowTerpakai[$map->semantic_role] = true;
                }

                $baris[] = [
                    'questionnaire_id'       => $targetId,
                    'question_code'          => $map->question_code,
                    'question_text_snapshot' => $map->question_text_snapshot,
                    'semantic_role'          => $map->semantic_role,
                    'grain'                  => $map->grain,
                    'effective_date'         => $map->effective_date,
                    'is_active'              => true,
                    'created_at'             => now(),
                    'updated_at'             => now(),
                ];
            }

            if ($baris !== []) {
                $db->table('question_semantic_mapping')->insert($baris);
                $ditambahkan += count($baris);
            }
        }

        $this->command?->info(
            "Pemetaan semantik disalin ke kuesioner global lain: {$ditambahkan} baris "
            . "(contoh diambil dari questionnaire_id {$templateId})."
        );
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

}
