<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'olap';

    public function up(): void
    {
        Schema::connection('olap')->create('fact_employment_outcome', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employment_record_id')->unique();
            $table->unsignedInteger('time_key')->nullable();
            $table->unsignedBigInteger('program_key')->nullable();
            $table->unsignedBigInteger('alumni_key')->nullable();
            $table->unsignedBigInteger('employment_status_key')->nullable();
            $table->decimal('waiting_months', 6, 2)->nullable();
            $table->decimal('salary_first', 14, 2)->nullable();
            $table->decimal('salary_current', 14, 2)->nullable();
            $table->boolean('is_job_relevant')->nullable();
            $table->boolean('is_entrepreneur')->default(false);
            $table->timestamps();

            $table->foreign('time_key')->references('time_key')->on('dim_time')->nullOnDelete();
            $table->foreign('program_key')->references('program_key')->on('dim_program')->nullOnDelete();
            $table->foreign('alumni_key')->references('alumni_key')->on('dim_alumni')->nullOnDelete();
            $table->foreign('employment_status_key')->references('employment_status_key')->on('dim_employment_status')->nullOnDelete();

            $table->index(['time_key', 'program_key', 'employment_status_key']);
            $table->index('alumni_key');
        });
    }

    public function down(): void
    {
        Schema::connection('olap')->dropIfExists('fact_employment_outcome');
    }
};
