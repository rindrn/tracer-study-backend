<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'oltp';

    public function up(): void
    {
        Schema::connection('oltp')->create('questionnaires', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50);
            $table->string('title', 200);
            $table->unsignedSmallInteger('period_year');
            $table->unsignedInteger('version')->default(1);
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();

            $table->unique(['code', 'version']);
            $table->index(['period_year', 'status']);
        });
    }

    public function down(): void
    {
        Schema::connection('oltp')->dropIfExists('questionnaires');
    }
};
