<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'oltp';

    public function up(): void
    {
        Schema::connection('oltp')->create('response_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('response_id')
                ->constrained('responses')
                ->cascadeOnDelete();

            // Keep question reference flexible until question bank tables are added.
            $table->string('question_code', 100);
            $table->unsignedInteger('answer_index')->default(0);

            $table->text('answer_text')->nullable();
            $table->decimal('answer_number', 14, 2)->nullable();
            $table->date('answer_date')->nullable();
            $table->string('answer_option_code', 100)->nullable();
            $table->timestamps();

            $table->index('response_id');
            $table->index('question_code');
            $table->index('answer_option_code');
            $table->unique(['response_id', 'question_code', 'answer_index']);
        });
    }

    public function down(): void
    {
        Schema::connection('oltp')->dropIfExists('response_answers');
    }
};
