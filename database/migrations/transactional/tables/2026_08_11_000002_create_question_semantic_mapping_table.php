<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pemetaan question_code OLTP ke peran semantik — sebelumnya hanya ada di
 * database/dump/oltp_supplement.sql.
 *
 * SQL mentah karena dua index unik di bawah bersifat partial (WHERE is_active)
 * dan Blueprint tidak bisa menyatakannya. Lihat catatan di
 * 2026_08_11_000001_create_semantic_role_registry_table.php soal kenapa
 * definisinya harus identik dengan init.sql.
 *
 * Kolom `grain` sengaja duplikat dari semantic_role_registry.grain: predikat
 * partial index di Postgres wajib immutable dan tidak boleh menyubkueri tabel
 * lain, jadi nilainya harus ikut tersimpan di sini.
 */
return new class extends Migration
{
    protected $connection = 'oltp';

    public function up(): void
    {
        DB::connection('oltp')->statement("
            CREATE TABLE question_semantic_mapping (
                id                     bigint GENERATED ALWAYS AS IDENTITY,
                questionnaire_id       bigint                NOT NULL,
                question_code          character varying(80) NOT NULL,
                question_text_snapshot text,
                semantic_role          character varying(50) NOT NULL,
                grain                  character varying(20) NOT NULL,
                effective_date         date    DEFAULT CURRENT_DATE NOT NULL,
                is_active              boolean DEFAULT true         NOT NULL,
                mapped_by              bigint,
                deactivated_at         timestamp without time zone,
                deactivated_by         bigint,
                created_at             timestamp without time zone DEFAULT now() NOT NULL,
                updated_at             timestamp without time zone DEFAULT now() NOT NULL,
                CONSTRAINT question_semantic_mapping_pkey PRIMARY KEY (id),
                CONSTRAINT question_semantic_mapping_grain_check
                    CHECK (grain IN ('narrow','wide')),
                CONSTRAINT question_semantic_mapping_questionnaire_id_fkey
                    FOREIGN KEY (questionnaire_id) REFERENCES questionnaires (id),
                CONSTRAINT question_semantic_mapping_semantic_role_fkey
                    FOREIGN KEY (semantic_role) REFERENCES semantic_role_registry (role_key),
                CONSTRAINT question_semantic_mapping_mapped_by_fkey
                    FOREIGN KEY (mapped_by) REFERENCES users (id),
                CONSTRAINT question_semantic_mapping_deactivated_by_fkey
                    FOREIGN KEY (deactivated_by) REFERENCES users (id)
            )
        ");

        // Satu question_code hanya boleh punya satu pemetaan aktif per kuesioner.
        DB::connection('oltp')->statement("
            CREATE UNIQUE INDEX uq_qsm_active_code
                ON question_semantic_mapping (questionnaire_id, question_code)
             WHERE is_active
        ");

        // Peran narrow hanya boleh dipegang satu pertanyaan per kuesioner.
        // Peran wide sengaja dikecualikan: banyak question_code memang berbagi
        // peran yang sama (identitas per-item ada di dim_indikator_evaluasi).
        DB::connection('oltp')->statement("
            CREATE UNIQUE INDEX uq_qsm_active_narrow_role
                ON question_semantic_mapping (questionnaire_id, semantic_role)
             WHERE is_active AND grain = 'narrow'
        ");

        DB::connection('oltp')->statement("
            CREATE INDEX ix_qsm_role
                ON question_semantic_mapping (semantic_role)
             WHERE is_active
        ");

        DB::connection('oltp')->statement("
            COMMENT ON TABLE question_semantic_mapping IS
            'Maps an OLTP question_code (scoped to one questionnaire) to a semantic_role. Forward-only versioned via is_active -- never delete.'
        ");

        DB::connection('oltp')->statement("
            COMMENT ON COLUMN question_semantic_mapping.grain IS
            'Denormalized copy of semantic_role_registry.grain at write time. Needed because Postgres partial-index predicates must be immutable and cannot subquery another table -- this is what makes the narrow-role uniqueness index below possible.'
        ");
    }

    public function down(): void
    {
        Schema::connection('oltp')->dropIfExists('question_semantic_mapping');
    }
};
