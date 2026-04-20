<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'oltp';

    public function up(): void
    {
        Schema::connection('oltp')->create('questionnaire_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('questionnaire_id')
                ->constrained('questionnaires')
                ->cascadeOnDelete();
            $table->foreignId('section_id')
                ->nullable()
                ->constrained('questionnaire_sections')
                ->nullOnDelete();
            $table->string('code', 80);
            $table->text('question_text');
            $table->enum('question_type', [
                'short_text',
                'long_text',
                'single_choice',
                'multiple_choice',
                'number',
                'date',
                'boolean',
            ]);
            $table->boolean('is_required')->default(false);
            $table->unsignedInteger('order_no')->default(1);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['questionnaire_id', 'code']);
            $table->index(['section_id', 'order_no']);
            $table->index(['questionnaire_id', 'question_type']);
        });
    }

    public function down(): void
    {
        Schema::connection('oltp')->dropIfExists('questionnaire_questions');
    }
};
