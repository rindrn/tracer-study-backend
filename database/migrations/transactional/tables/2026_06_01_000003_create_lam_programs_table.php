<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'oltp';

    public function up(): void
    {
        Schema::connection('oltp')->create('lam_programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lam_id')
                  ->constrained('lams')
                  ->cascadeOnDelete();
            $table->foreignId('program_id')
                  ->constrained('programs')
                  ->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['lam_id', 'program_id']);
        });
    }

    public function down(): void
    {
        Schema::connection('oltp')->dropIfExists('lam_programs');
    }
};
