<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Impor jawaban tracer study berformat PDDikti ("Data Kementrian").
 *
 * Tiga perubahan sekaligus karena saling bergantung:
 *
 *  1. Tabel pddikti_imports — satu baris per berkas yang diunggah. Validasi
 *     dan impor berjalan di queue worker; kolom hitungannya dipoll FE untuk
 *     progress bar, dan `issues` menyimpan daftar galat/peringatan per baris
 *     yang ditampilkan di layar pratinjau.
 *
 *  2. responses.source — membedakan pengisian ONLINE oleh alumni dari hasil
 *     IMPOR. Aturannya: impor tidak pernah menimpa pengisian online yang sudah
 *     terkirim, tetapi boleh memperbarui hasil impor sebelumnya (unggah ulang
 *     berkas perbaikan). Seluruh baris yang sudah ada diberi 'online' — data
 *     lama tidak pernah tersentuh impor.
 *
 *  3. import_batch_id di responses dan alumni_profiles — supaya "Batalkan
 *     impor ini" tahu persis baris mana yang dibawa satu berkas.
 */
return new class extends Migration
{
    protected $connection = 'oltp';

    public function up(): void
    {
        $db = DB::connection('oltp');

        $db->statement("
            CREATE TABLE pddikti_imports (
                id             bigint GENERATED ALWAYS AS IDENTITY,
                status         character varying(20) DEFAULT 'validating' NOT NULL,
                original_name  character varying(255) NOT NULL,
                file_path      character varying(255) NOT NULL,
                uploaded_by    bigint,
                total_rows     integer DEFAULT 0 NOT NULL,
                processed_rows integer DEFAULT 0 NOT NULL,
                valid_rows     integer DEFAULT 0 NOT NULL,
                error_rows     integer DEFAULT 0 NOT NULL,
                skipped_rows   integer DEFAULT 0 NOT NULL,
                warning_count  integer DEFAULT 0 NOT NULL,
                imported_rows  integer DEFAULT 0 NOT NULL,
                cohorts        jsonb,
                issues         jsonb,
                error_message  text,
                validated_at   timestamp without time zone,
                started_at     timestamp without time zone,
                finished_at    timestamp without time zone,
                created_at     timestamp without time zone DEFAULT now() NOT NULL,
                updated_at     timestamp without time zone DEFAULT now() NOT NULL,
                CONSTRAINT pddikti_imports_pkey PRIMARY KEY (id),
                CONSTRAINT pddikti_imports_status_check CHECK (status IN (
                    'validating','validated','importing','completed','failed','cancelled','rolled_back'
                )),
                CONSTRAINT pddikti_imports_uploaded_by_fkey
                    FOREIGN KEY (uploaded_by) REFERENCES users (id) ON DELETE SET NULL
            )
        ");

        $db->statement("
            COMMENT ON TABLE pddikti_imports IS
            'Satu baris per berkas impor PDDikti. Dipoll FE (GET /api/pddikti-imports/{id}) untuk progress bar validasi dan impor.'
        ");

        $db->statement("ALTER TABLE responses ADD COLUMN source character varying(10) DEFAULT 'online' NOT NULL");
        $db->statement("ALTER TABLE responses ADD CONSTRAINT responses_source_check CHECK (source IN ('online','import'))");
        $db->statement("
            ALTER TABLE responses ADD COLUMN import_batch_id bigint
                REFERENCES pddikti_imports (id) ON DELETE SET NULL
        ");
        $db->statement("CREATE INDEX ix_responses_import_batch ON responses (import_batch_id) WHERE import_batch_id IS NOT NULL");

        $db->statement("
            ALTER TABLE alumni_profiles ADD COLUMN import_batch_id bigint
                REFERENCES pddikti_imports (id) ON DELETE SET NULL
        ");
        $db->statement("
            COMMENT ON COLUMN alumni_profiles.import_batch_id IS
            'Diisi hanya bila alumni DIBUAT oleh impor PDDikti (bukan diperbarui), supaya pembatalan impor bisa menghapusnya kembali.'
        ");
    }

    public function down(): void
    {
        $db = DB::connection('oltp');

        $db->statement('ALTER TABLE alumni_profiles DROP COLUMN IF EXISTS import_batch_id');
        $db->statement('DROP INDEX IF EXISTS ix_responses_import_batch');
        $db->statement('ALTER TABLE responses DROP COLUMN IF EXISTS import_batch_id');
        $db->statement('ALTER TABLE responses DROP CONSTRAINT IF EXISTS responses_source_check');
        $db->statement('ALTER TABLE responses DROP COLUMN IF EXISTS source');
        $db->statement('DROP TABLE IF EXISTS pddikti_imports');
    }
};
