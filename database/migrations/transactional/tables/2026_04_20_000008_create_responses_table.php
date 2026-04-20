<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'oltp';

    public function up(): void
    {
        Schema::connection('oltp')->create('responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('questionnaire_id')
                ->constrained('questionnaires')
                ->cascadeOnDelete();
            $table->foreignId('alumni_id')
                ->constrained('alumni_profiles')
                ->cascadeOnDelete();
            $table->enum('status', ['started', 'submitted', 'verified'])->default('started');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->string('source', 30)->nullable();
            $table->timestamps();

            $table->unique(['questionnaire_id', 'alumni_id']);
            $table->index(['status', 'submitted_at']);
        });
    }

    public function down(): void
    {
        Schema::connection('oltp')->dropIfExists('responses');
    }
};
