<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Cleanup Fase 2a — drop tabel yang tidak dipakai aplikasi.
 *
 * Yang di-drop:
 *   - employment_records      : data employment sudah ada di response_answers (f8, f502, f505, f5b, f5c, f5a2).
 *                                Untuk OLAP akan lewat ETL, bukan tabel terpisah.
 *   - education_records       : data education sudah ada di response_answers (f18b, f18c).
 *                                Untuk OLAP akan lewat ETL, bukan tabel terpisah.
 *   - questionnaire_assignments : tabel dead — migration ada tapi tidak ada kode yang mereferensikannya.
 */
return new class extends Migration
{
    protected $connection = 'oltp';

    public function up(): void
    {
        Schema::connection('oltp')->dropIfExists('employment_records');
        Schema::connection('oltp')->dropIfExists('education_records');
        Schema::connection('oltp')->dropIfExists('questionnaire_assignments');
    }

    public function down(): void
    {
        // Recreate employment_records
        Schema::connection('oltp')->create('employment_records', function ($table) {
            $table->id();
            $table->foreignId('alumni_id')->constrained('alumni_profiles')->cascadeOnDelete();
            $table->foreignId('questionnaire_id')->nullable()->constrained('questionnaires')->nullOnDelete();
            $table->string('employment_status', 255);
            $table->date('first_job_started_at')->nullable();
            $table->decimal('waiting_months', 6, 2)->nullable();
            $table->decimal('salary_first', 14, 2)->nullable();
            $table->decimal('salary_current', 14, 2)->nullable();
            $table->string('company_name', 150)->nullable();
            $table->string('industry', 100)->nullable();
            $table->string('job_title', 150)->nullable();
            $table->string('job_level', 100)->nullable();
            $table->string('work_city', 100)->nullable();
            $table->boolean('is_job_relevant')->nullable();
            $table->timestamps();
            $table->index(['alumni_id', 'employment_status']);
            $table->index('questionnaire_id');
        });

        // Recreate education_records
        Schema::connection('oltp')->create('education_records', function ($table) {
            $table->id();
            $table->foreignId('alumni_id')->constrained('alumni_profiles')->cascadeOnDelete();
            $table->foreignId('questionnaire_id')->nullable()->constrained('questionnaires')->nullOnDelete();
            $table->boolean('is_further_study')->default(false);
            $table->string('institution_name', 150)->nullable();
            $table->string('degree', 255)->nullable();
            $table->string('major', 150)->nullable();
            $table->smallInteger('start_year')->nullable();
            $table->boolean('is_scholarship')->nullable();
            $table->timestamps();
            $table->index(['alumni_id', 'is_further_study']);
            $table->index('questionnaire_id');
        });

        // Recreate questionnaire_assignments
        Schema::connection('oltp')->create('questionnaire_assignments', function ($table) {
            $table->id();
            $table->foreignId('questionnaire_id')->constrained('questionnaires')->cascadeOnDelete();
            $table->foreignId('alumni_id')->constrained('alumni_profiles')->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->timestamp('due_at')->nullable();
            $table->string('status', 255)->default('assigned');
            $table->timestamps();
            $table->unique(['questionnaire_id', 'alumni_id']);
            $table->index(['status', 'due_at']);
        });
    }
};
