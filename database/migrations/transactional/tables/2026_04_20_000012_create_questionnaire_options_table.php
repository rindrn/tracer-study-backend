<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'oltp';

    public function up(): void
    {
        Schema::connection('oltp')->create('questionnaire_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')
                ->constrained('questionnaire_questions')
                ->cascadeOnDelete();
            $table->string('option_code', 80);
            $table->string('option_label', 255);
            $table->string('option_value', 255)->nullable();
            $table->unsignedInteger('order_no')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['question_id', 'option_code']);
            $table->index(['question_id', 'order_no']);
        });
    }

    public function down(): void
    {
        Schema::connection('oltp')->dropIfExists('questionnaire_options');
    }
};
