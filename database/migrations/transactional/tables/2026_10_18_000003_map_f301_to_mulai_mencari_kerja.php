<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * f301 ("Kapan Anda mulai mencari pekerjaan?") dipetakan ke role
 * "mulai_mencari_kerja" supaya ETL bisa membedakan masa tunggu 0 yang sah
 * dari 0 penanda tidak-diisi ekspor kementerian.
 *
 * Ekspor menulis 0 untuk setiap sel kosong, termasuk f502 (masa tunggu).
 * Pada data riil 32% alumni bekerja angkatan 2023–2024 bermasa tunggu 0.
 * 0 masuk akal bagi yang mulai mencari sebelum lulus atau tidak mencari
 * sama sekali (langsung diterima); bagi yang baru mencari sesudah lulus,
 * atau yang f301-nya kosong, 0 hampir pasti sel kosong -- lihat
 * AlumniFactBuilderService::masaTungguBekerja().
 *
 * Role ini tidak menulis kolom apa pun (target_column null, seperti
 * status_pekerjaan); ia hanya dibaca untuk aturan di atas.
 */
return new class extends Migration
{
    protected $connection = 'oltp';

    private const ROLE = 'mulai_mencari_kerja';

    public function up(): void
    {
        $db = DB::connection('oltp');

        // Sama seperti 2026_08_06_000001: pada migrate:fresh tabel ini baru
        // datang dari dump sesudah seluruh migration, jadi no-op di sana.
        if (! $db->getSchemaBuilder()->hasTable('semantic_role_registry')) {
            return;
        }

        if (! $db->table('semantic_role_registry')->where('role_key', self::ROLE)->exists()) {
            $db->table('semantic_role_registry')->insert([
                'role_key'            => self::ROLE,
                'label'               => 'Kapan Mulai Mencari Kerja',
                'category'            => 'waktu_tunggu',
                'description'         => 'Sebelum lulus, sesudah lulus, atau tidak mencari kerja; dipakai memvalidasi masa tunggu 0',
                'expected_kind'       => 'categorical',
                'value_min'           => null,
                'value_max'           => null,
                'sample_valid_answer' => '1',
                'target_table'        => 'fact_tracer_study',
                'target_column'       => null,
                'grain'               => 'narrow',
                'is_active'           => true,
                'created_at'          => now(),
                'updated_at'          => now(),
            ]);
        }

        $questionnaireIds = $db->table('questionnaire_questions')
            ->where('code', 'f301')
            ->pluck('questionnaire_id');

        foreach ($questionnaireIds as $questionnaireId) {
            $alreadyMapped = $db->table('question_semantic_mapping')
                ->where('questionnaire_id', $questionnaireId)
                ->where('question_code', 'f301')
                ->where('is_active', true)
                ->exists();

            if ($alreadyMapped) {
                continue;
            }

            $db->table('question_semantic_mapping')->insert([
                'questionnaire_id'       => $questionnaireId,
                'question_code'          => 'f301',
                'question_text_snapshot' => 'Kapan Anda mulai mencari pekerjaan? Mohon pekerjaan sambilan tidak dimasukkan.',
                'semantic_role'          => self::ROLE,
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

        if (! $db->getSchemaBuilder()->hasTable('semantic_role_registry')) {
            return;
        }

        $db->table('question_semantic_mapping')
            ->where('question_code', 'f301')
            ->where('semantic_role', self::ROLE)
            ->delete();

        $db->table('semantic_role_registry')->where('role_key', self::ROLE)->delete();
    }
};
