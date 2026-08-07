<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * FR-026: f303 ("Kira-kira berapa bulan sesudah lulus Anda mulai mencari
 * pekerjaan?") sudah ada di questionnaire (show_if f301=2) dan sudah punya
 * data respons asli, tapi belum pernah dipetakan ke semantic_role apapun --
 * AlumniFactBuilderService hardcode bulan_sesudah_lulus = null karena tidak
 * ada role terdaftar untuk kolom itu. Migration ini mendaftarkan role
 * "bulan_sesudah_lulus" di semantic_role_registry (mirror role
 * "bulan_sebelum_lulus" yang sudah ada untuk f302) dan memetakan f303 ke
 * role tsb di setiap versi questionnaire yang punya f303.
 */
return new class extends Migration
{
    public function up(): void
    {
        $db = DB::connection('oltp');

        $exists = $db->table('semantic_role_registry')->where('role_key', 'bulan_sesudah_lulus')->exists();
        if (! $exists) {
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

    public function down(): void
    {
        $db = DB::connection('oltp');

        $db->table('question_semantic_mapping')
            ->where('question_code', 'f303')
            ->where('semantic_role', 'bulan_sesudah_lulus')
            ->delete();

        $db->table('semantic_role_registry')->where('role_key', 'bulan_sesudah_lulus')->delete();
    }
};
