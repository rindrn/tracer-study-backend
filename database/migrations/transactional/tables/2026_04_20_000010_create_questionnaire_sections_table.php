<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'oltp';

    public function up(): void
    {
        Schema::connection('oltp')->create('questionnaire_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('questionnaire_id')
                ->constrained('questionnaires')
                ->cascadeOnDelete();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->unsignedInteger('order_no')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['questionnaire_id', 'order_no']);
            $table->index(['questionnaire_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::connection('oltp')->dropIfExists('questionnaire_sections');
    }
};
