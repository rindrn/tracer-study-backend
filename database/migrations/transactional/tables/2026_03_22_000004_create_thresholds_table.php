<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'oltp';
 
    public function up(): void
    {
        Schema::connection('oltp')->create('thresholds', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->decimal('value', 12, 2);
            $table->foreignId('created_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();
            $table->timestamps();
        });
    }
 
    public function down(): void
    {
        Schema::connection('oltp')->dropIfExists('thresholds');
    }
};
