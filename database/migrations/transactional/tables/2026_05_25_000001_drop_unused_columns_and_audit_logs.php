<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 1 Cleanup — drop kolom & tabel yang tidak punya konteks domain.
 *
 * Yang di-drop:
 *   - users.remember_token   : auth pakai Sanctum, fitur "remember me" web tidak aktif
 *   - alumni_profiles.consent_at : tidak ada fitur consent screen di app
 *   - responses.started_at   : tidak pernah di-set (response langsung submitted)
 *   - responses.source       : tidak pernah di-set, kanal pengisian tunggal
 *   - audit_logs (table)     : nol referensi di kode; audit trail nantinya pakai package
 *
 * Yang TIDAK di-drop (walau saat ini "dead"): kolom-kolom domain Tracer Study
 * (employment_records.{salary_first, first_job_started_at, industry, job_level,
 * is_job_relevant}, education_records.{degree, start_year, is_scholarship},
 * response_answers.{answer_number, answer_date, answer_option_code}). Ini akan
 * diisi saat refactor service di Fase 2.
 */
return new class extends Migration
{
    protected $connection = 'oltp';

    public function up(): void
    {
        Schema::connection('oltp')->table('users', function (Blueprint $table) {
            $table->dropColumn('remember_token');
        });

        Schema::connection('oltp')->table('alumni_profiles', function (Blueprint $table) {
            $table->dropColumn('consent_at');
        });

        Schema::connection('oltp')->table('responses', function (Blueprint $table) {
            $table->dropColumn(['started_at', 'source']);
        });

        Schema::connection('oltp')->dropIfExists('audit_logs');
    }

    public function down(): void
    {
        // Recreate audit_logs (mirror of 2026_04_20_000016).
        Schema::connection('oltp')->create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('action', 100);
            $table->string('entity_type', 100);
            $table->string('entity_id', 64)->nullable();
            $table->uuid('request_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->json('before_data')->nullable();
            $table->json('after_data')->nullable();
            $table->timestamp('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));

            $table->index(['actor_user_id', 'created_at']);
            $table->index(['entity_type', 'entity_id']);
            $table->index('request_id');
        });

        Schema::connection('oltp')->table('responses', function (Blueprint $table) {
            $table->timestamp('started_at')->nullable();
            $table->string('source', 30)->nullable();
        });

        Schema::connection('oltp')->table('alumni_profiles', function (Blueprint $table) {
            $table->timestamp('consent_at')->nullable()->after('gpa');
        });

        Schema::connection('oltp')->table('users', function (Blueprint $table) {
            $table->rememberToken();
        });
    }
};
