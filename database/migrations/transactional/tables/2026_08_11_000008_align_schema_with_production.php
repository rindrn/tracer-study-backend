<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Menyamakan skema hasil migrasi dengan skema produksi di init.sql.
 *
 * Ditemukan saat membandingkan dua basis data uji: satu dibangun dari
 * `migrate` di basis data kosong, satu lagi dari restore init.sql. Keduanya
 * ternyata TIDAK identik — ada perubahan yang dulu diterapkan manual di
 * produksi tanpa pernah ditulis jadi migrasi, dan satu perubahan yang
 * sebaliknya (ada di berkas migrasi tapi tidak pernah sampai ke produksi
 * karena migrasinya sudah terlanjur tercatat jalan).
 *
 * Semua langkah memakai guard sebab migrasi ini harus jalan benar di DUA arah:
 *   - basis data kosong  -> menambal apa yang kurang dari berkas migrasi lama
 *   - restore init.sql   -> menambal apa yang kurang dari produksi
 * Menjalankannya dua kali tidak berefek apa-apa.
 */
return new class extends Migration
{
    protected $connection = 'oltp';

    /**
     * Nilai employment_status yang benar-benar dipakai data asli, dan yang
     * dibaca lapisan analitik (lihat MasaTungguRepository::.. 'values').
     *
     * Ketujuhnya persis label opsi f8 di kuesioner. Sumber tunggalnya di sisi
     * aplikasi: TracerStudySubmitService::EMPLOYMENT_STATUS_BY_F8 — kalau
     * daftar di sini berubah, ubah juga di sana.
     */
    private const EMPLOYMENT_STATUSES = [
        'Bekerja (full time / part time)',
        'Belum memungkinkan bekerja',
        'Wiraswasta',
        'Melanjutkan Pendidikan',
        'Tidak kerja tetapi sedang mencari kerja',
        'Melanjutkan pendidikan sambil bekerja',
        'Melanjutkan pendidikan sambil wiraswasta',
    ];

    public function up(): void
    {
        $conn   = DB::connection('oltp');
        $schema = Schema::connection('oltp');

        // 0. Jatuhkan dulu ketiga view. Harus paling awal: di jalur restore
        //    init.sql, v_employment sudah ada dan bergantung pada kolom
        //    employment_status, sehingga ALTER TYPE di langkah 3 ditolak
        //    Postgres ("cannot alter type of a column used by a view").
        //    Ketiganya dibuat ulang di langkah 5.
        $conn->statement('DROP VIEW IF EXISTS vw_thresholds_complete');
        $conn->statement('DROP VIEW IF EXISTS vw_lam_versions_complete');
        $conn->statement('DROP VIEW IF EXISTS v_employment');

        // 1. users.status — ada di 2026_03_22_000002_create_users_table dan
        //    dipakai User::$fillable + $casts, tapi TIDAK ada di produksi:
        //    berkas migrasinya diubah setelah sempat dijalankan, sehingga
        //    kolomnya tidak pernah benar-benar dibuat di sana.
        if (!$schema->hasColumn('users', 'status')) {
            $schema->table('users', function (Blueprint $table) {
                $table->boolean('status')->default(true);
            });
            $conn->statement('ALTER TABLE users ALTER COLUMN status SET NOT NULL');
        }

        // 2. Lokasi kerja alumni. Kolomnya ada dan TERISI di produksi, tapi
        //    tidak pernah punya migrasi. Belum dibaca kode mana pun — tetap
        //    dibawa supaya datanya tidak hilang saat instalasi ulang.
        if (!$schema->hasColumn('employment_records', 'work_city_id')) {
            $schema->table('employment_records', function (Blueprint $table) {
                $table->foreignId('work_city_id')
                      ->nullable()
                      ->constrained('cities')
                      ->nullOnDelete();
            });
        }

        if (!$schema->hasColumn('employment_records', 'work_province_code')) {
            $schema->table('employment_records', function (Blueprint $table) {
                $table->string('work_province_code', 10)->nullable();
                $table->foreign('work_province_code')
                      ->references('code')->on('provinces')
                      ->nullOnDelete();
            });
        }

        // 3. employment_status. Berkas migrasi lama memasang CHECK berisi
        //    'employed','entrepreneur',... (bahasa Inggris) sedangkan seluruh
        //    data asli dan lapisan analitik memakai frasa Indonesia. Constraint
        //    versi Inggris membuat data asli mustahil dimuat.
        $conn->statement('ALTER TABLE employment_records ALTER COLUMN employment_status TYPE character varying(100)');
        $conn->statement('ALTER TABLE employment_records DROP CONSTRAINT IF EXISTS employment_records_employment_status_check');

        $values = implode(', ', array_map(
            static fn (string $v): string => "'" . str_replace("'", "''", $v) . "'",
            self::EMPLOYMENT_STATUSES
        ));

        $conn->statement("
            ALTER TABLE employment_records
              ADD CONSTRAINT employment_records_employment_status_check
              CHECK (employment_status IN ({$values}))
        ");

        // 4. questionnaires.code — produksi varchar(50), berkas migrasi
        //    varchar(100). Diseragamkan ke 50 mengikuti produksi.
        $conn->statement('ALTER TABLE questionnaires ALTER COLUMN code TYPE character varying(50)');

        // 5. Bangun ulang ketiga view (sudah dijatuhkan di langkah 0). Versi di
        //    produksi lebih kaya daripada yang dihasilkan 2026_06_01_000006:
        //    ada year_end (dihitung lewat LEAD) dan kolom dari
        //    threshold_configs.
        $conn->statement('
            CREATE VIEW v_employment AS
            SELECT id, alumni_id, questionnaire_id, employment_status, work_city,
                   work_city_id, work_province_code, waiting_months, salary_current,
                   company_name, is_job_relevant, created_at, updated_at
              FROM employment_records
        ');

        $conn->statement("
            CREATE VIEW vw_lam_versions_complete AS
            SELECT lv.lam_version_id,
                   lv.year,
                   lv.year_end,
                   lv.version_name,
                   lv.is_active,
                   l.id   AS lam_id,
                   l.name AS lam_name,
                   l.code AS lam_code,
                   COALESCE(json_agg(DISTINCT jsonb_build_object(
                       'id', p.id, 'name', p.name, 'code', p.code, 'degree', p.degree
                   )) FILTER (WHERE p.id IS NOT NULL), '[]'::json) AS programs,
                   COALESCE(json_agg(DISTINCT jsonb_build_object(
                       'threshold_id', t.id, 'indicator_id', ti.id, 'indicator_key', ti.key,
                       'indicator_name', ti.name, 'unit', ti.unit, 'operator', ti.operator,
                       'level', t.level, 'value', t.value
                   )) FILTER (WHERE t.id IS NOT NULL), '[]'::json) AS thresholds
              FROM (
                    SELECT lam_versions.id AS lam_version_id,
                           lam_versions.lam_id,
                           lam_versions.year,
                           lam_versions.version_name,
                           lam_versions.is_active,
                           lead(lam_versions.year) OVER (
                               PARTITION BY lam_versions.lam_id ORDER BY lam_versions.year
                           ) - 1 AS year_end
                      FROM lam_versions
                   ) lv
              JOIN lams l                  ON l.id  = lv.lam_id
              LEFT JOIN lam_programs lp    ON lp.lam_id = l.id
              LEFT JOIN programs p         ON p.id  = lp.program_id
              LEFT JOIN thresholds t       ON t.lam_version_id = lv.lam_version_id
              LEFT JOIN threshold_indicators ti ON ti.id = t.indicator_id
             GROUP BY lv.lam_version_id, lv.year, lv.year_end, lv.version_name,
                      lv.is_active, l.id, l.name, l.code
        ");

        $conn->statement('
            CREATE VIEW vw_thresholds_complete AS
            SELECT t.id    AS threshold_id,
                   t.value AS threshold_value,
                   t.level AS threshold_level,
                   t.created_at,
                   ti.id       AS indicator_id,
                   ti.key      AS indicator_key,
                   ti.name     AS indicator_name,
                   ti.unit     AS indicator_unit,
                   ti.operator AS indicator_operator,
                   ti.dynamic_param_unit,
                   ti.is_system_calculated,
                   tc.param_value,
                   lv.id           AS lam_version_id,
                   lv.year         AS lam_version_year,
                   lv.year_end     AS lam_version_year_end,
                   lv.version_name AS lam_version_name,
                   lv.is_active    AS lam_version_is_active,
                   l.id   AS lam_id,
                   l.name AS lam_name,
                   l.code AS lam_code
              FROM thresholds t
              JOIN threshold_indicators ti ON ti.id = t.indicator_id
              JOIN (
                    SELECT lam_versions.id,
                           lam_versions.lam_id,
                           lam_versions.year,
                           lam_versions.version_name,
                           lam_versions.is_active,
                           lam_versions.created_at,
                           lam_versions.updated_at,
                           lead(lam_versions.year) OVER (
                               PARTITION BY lam_versions.lam_id ORDER BY lam_versions.year
                           ) - 1 AS year_end
                      FROM lam_versions
                   ) lv ON lv.id = t.lam_version_id
              JOIN lams l ON l.id = lv.lam_id
              LEFT JOIN threshold_configs tc
                     ON tc.lam_version_id = t.lam_version_id
                    AND tc.indicator_id   = t.indicator_id
        ');

        // 6. users_role_check — hanya ada di berkas migrasi, tidak di produksi,
        //    dan daftar nilainya (head_tracer, tracer_team, wadir, kajur,
        //    kaprodi) tidak cocok dengan kenyataan: users asli memakai 'kotc'
        //    dan 'p2mpp' yang tidak ada di daftar itu, sementara 'head_tracer'
        //    tidak dipakai sama sekali. Sumber kebenaran peran sekarang tabel
        //    roles, jadi constraint ini dijatuhkan alih-alih diperluas.
        $conn->statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check');

        // 7. Dua UNIQUE yang hanya ada di produksi, menjaga aturan domain nyata:
        //    satu respons per alumni per kuesioner, satu catatan kerja per
        //    alumni per kuesioner. Data asli sudah memenuhi keduanya (nol
        //    duplikat saat diperiksa). ADD CONSTRAINT tidak punya IF NOT EXISTS,
        //    jadi dibungkus DO block supaya aman di jalur restore init.sql yang
        //    sudah memilikinya.
        $this->addConstraintIfMissing(
            'uq_responses_q_alumni',
            'ALTER TABLE responses ADD CONSTRAINT uq_responses_q_alumni UNIQUE (questionnaire_id, alumni_id)'
        );

        $this->addConstraintIfMissing(
            'uq_employment_records',
            'ALTER TABLE employment_records ADD CONSTRAINT uq_employment_records UNIQUE (alumni_id, questionnaire_id)'
        );

        // 8. Di produksi kedua FK lokasi kerja bernama *_fkey (dibuat lewat SQL
        //    mentah), sedangkan Blueprint memberi akhiran *_foreign. Isinya
        //    sama persis; namanya diseragamkan ke gaya Laravel supaya skema
        //    hasil kedua jalur instalasi benar-benar identik.
        foreach ([
            'employment_records_work_city_id_fkey'       => 'employment_records_work_city_id_foreign',
            'employment_records_work_province_code_fkey' => 'employment_records_work_province_code_foreign',
        ] as $lama => $baru) {
            $ada = $conn->selectOne(
                "SELECT 1 AS ada FROM pg_constraint
                  WHERE conname = ? AND connamespace = 'tracer_oltp'::regnamespace",
                [$lama]
            );

            if ($ada !== null) {
                $conn->statement("ALTER TABLE employment_records RENAME CONSTRAINT {$lama} TO {$baru}");
            }
        }

        // 9. uq_response_answers_code persis menduplikasi
        //    response_answers_response_id_question_code_answer_index_unique
        //    bawaan migrasi. Dijatuhkan supaya tidak ada dua index identik.
        $conn->statement('ALTER TABLE response_answers DROP CONSTRAINT IF EXISTS uq_response_answers_code');
    }

    /**
     * Jalankan ALTER TABLE ... ADD CONSTRAINT hanya bila constraint bernama itu
     * belum ada di schema tracer_oltp.
     */
    private function addConstraintIfMissing(string $name, string $sql): void
    {
        $exists = DB::connection('oltp')->selectOne(
            "SELECT 1 AS ada
               FROM pg_constraint
              WHERE conname = ?
                AND connamespace = 'tracer_oltp'::regnamespace",
            [$name]
        );

        if ($exists === null) {
            DB::connection('oltp')->statement($sql);
        }
    }

    public function down(): void
    {
        // Sengaja tidak mengembalikan CHECK versi Inggris maupun menjatuhkan
        // kolom lokasi kerja: keduanya memegang / mencocoki data asli.
        // Yang dibalik hanya view, ke bentuk sebelum penyelarasan.
        $conn = DB::connection('oltp');

        $conn->statement('DROP VIEW IF EXISTS v_employment');
        $conn->statement('DROP VIEW IF EXISTS vw_thresholds_complete');
        $conn->statement('DROP VIEW IF EXISTS vw_lam_versions_complete');
    }
};
