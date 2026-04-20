<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'oltp';

    public function up(): void
    {
        Schema::connection('oltp')->create('questionnaire_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('questionnaire_id')
                ->constrained('questionnaires')
                ->cascadeOnDelete();
            $table->foreignId('alumni_id')
                ->constrained('alumni_profiles')
                ->cascadeOnDelete();
            $table->foreignId('assigned_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('assigned_at')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->timestamp('due_at')->nullable();
            $table->enum('status', ['assigned', 'opened', 'submitted', 'expired'])->default('assigned');
            $table->timestamps();

            $table->unique(['questionnaire_id', 'alumni_id']);
            $table->index(['status', 'due_at']);
        });
    }

    public function down(): void
    {
        Schema::connection('oltp')->dropIfExists('questionnaire_assignments');
    }
};
