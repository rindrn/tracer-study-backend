<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'oltp';

    public function up(): void
    {
        Schema::connection('oltp')->create('education_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alumni_id')
                ->constrained('alumni_profiles')
                ->cascadeOnDelete();
            $table->foreignId('questionnaire_id')
                ->nullable()
                ->constrained('questionnaires')
                ->nullOnDelete();
            $table->boolean('is_further_study')->default(false);
            $table->string('institution_name', 150)->nullable();
            $table->enum('degree', ['D3', 'D4', 'S1', 'S2', 'S3', 'Profesi', 'Other'])->nullable();
            $table->string('major', 150)->nullable();
            $table->unsignedSmallInteger('start_year')->nullable();
            $table->boolean('is_scholarship')->nullable();
            $table->timestamps();

            $table->index(['alumni_id', 'is_further_study']);
            $table->index('questionnaire_id');
        });
    }

    public function down(): void
    {
        Schema::connection('oltp')->dropIfExists('education_records');
    }
};
