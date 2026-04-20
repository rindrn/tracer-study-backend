<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'olap';

    public function up(): void
    {
        Schema::connection('olap')->create('dim_program', function (Blueprint $table) {
            $table->id('program_key');
            $table->unsignedBigInteger('program_id')->unique();
            $table->string('code', 20);
            $table->string('name', 100);
            $table->enum('degree', ['S1', 'D3', 'D4', 'S2']);
            $table->boolean('is_active')->default(true);
            $table->timestamp('effective_from')->nullable();
            $table->timestamp('effective_to')->nullable();
            $table->boolean('current_flag')->default(true);
            $table->timestamps();

            $table->index(['degree', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::connection('olap')->dropIfExists('dim_program');
    }
};
