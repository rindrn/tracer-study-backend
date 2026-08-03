<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'oltp';
 
    public function up(): void
    {
        Schema::connection('oltp')->create('threshold_programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('threshold_id')
                  ->constrained('thresholds')
                  ->cascadeOnDelete();
            $table->foreignId('program_id')
                  ->constrained('programs')    // FK langsung ke programs di OLTP
                  ->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['threshold_id', 'program_id']);
        });
    }
 
    public function down(): void
    {
        Schema::connection('oltp')->dropIfExists('threshold_programs');
    }
};
