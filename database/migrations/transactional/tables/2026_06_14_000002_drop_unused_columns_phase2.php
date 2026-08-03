<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cleanup Fase 2b — drop kolom yang tidak dipakai aplikasi.
 *
 * Yang di-drop:
 *   - response_answers.answer_index       : tidak pernah diisi, selalu default 0
 *   - response_answers.answer_number      : angka disimpan di answer_text
 *   - response_answers.answer_date        : tanggal disimpan di answer_text
 *   - response_answers.answer_option_code : option code disimpan di answer_text
 *   - alumni_profiles.gpa                 : tidak pernah diisi oleh submit atau import
 *   - questionnaire_options.option_value  : selalu NULL, FE pakai option_code sebagai value
 *
 * Unique constraint response_answers diubah:
 *   DARI: (response_id, question_code, answer_index)
 *   KE:   (response_id, question_code)
 */
return new class extends Migration
{
    protected $connection = 'oltp';

    public function up(): void
    {
        // response_answers — drop kolom + ubah unique constraint
        Schema::connection('oltp')->table('response_answers', function (Blueprint $table) {
            // Drop unique constraint lama (answer_index termasuk di dalamnya)
            $table->dropUnique('response_answers_response_id_question_code_answer_index_unique');
        });

        Schema::connection('oltp')->table('response_answers', function (Blueprint $table) {
            $table->dropColumn(['answer_index', 'answer_number', 'answer_date', 'answer_option_code']);
        });

        Schema::connection('oltp')->table('response_answers', function (Blueprint $table) {
            // Unique constraint baru tanpa answer_index
            $table->unique(['response_id', 'question_code']);
        });

        // alumni_profiles — drop gpa
        Schema::connection('oltp')->table('alumni_profiles', function (Blueprint $table) {
            $table->dropColumn('gpa');
        });

        // questionnaire_options — drop option_value
        Schema::connection('oltp')->table('questionnaire_options', function (Blueprint $table) {
            $table->dropColumn('option_value');
        });
    }

    public function down(): void
    {
        // Restore alumni_profiles.gpa
        Schema::connection('oltp')->table('alumni_profiles', function (Blueprint $table) {
            $table->decimal('gpa', 3, 2)->nullable()->after('graduation_year');
        });

        // Restore questionnaire_options.option_value
        Schema::connection('oltp')->table('questionnaire_options', function (Blueprint $table) {
            $table->string('option_value', 255)->nullable()->after('option_label');
        });

        // Restore response_answers columns + unique constraint
        Schema::connection('oltp')->table('response_answers', function (Blueprint $table) {
            $table->dropUnique(['response_id', 'question_code']);
        });

        Schema::connection('oltp')->table('response_answers', function (Blueprint $table) {
            $table->unsignedInteger('answer_index')->default(0)->after('question_code');
            $table->decimal('answer_number', 14, 2)->nullable()->after('answer_text');
            $table->date('answer_date')->nullable()->after('answer_number');
            $table->string('answer_option_code', 100)->nullable()->after('answer_date');
        });

        Schema::connection('oltp')->table('response_answers', function (Blueprint $table) {
            $table->unique(['response_id', 'question_code', 'answer_index']);
            $table->index('answer_option_code');
        });
    }
};
