<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'oltp';

    public function up(): void
    {
        Schema::connection('oltp')->create('employment_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alumni_id')
                ->constrained('alumni_profiles')
                ->cascadeOnDelete();
            $table->foreignId('questionnaire_id')
                ->nullable()
                ->constrained('questionnaires')
                ->nullOnDelete();
            $table->enum('employment_status', ['employed', 'entrepreneur', 'further_study', 'seeking_work', 'other']);
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
    }

    public function down(): void
    {
        Schema::connection('oltp')->dropIfExists('employment_records');
    }
};
