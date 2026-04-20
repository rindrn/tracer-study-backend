<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'olap';

    public function up(): void
    {
        Schema::connection('olap')->create('fact_response', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('response_id')->unique();
            $table->unsignedInteger('time_key')->nullable();
            $table->unsignedBigInteger('program_key')->nullable();
            $table->unsignedBigInteger('alumni_key')->nullable();
            $table->unsignedSmallInteger('is_submitted')->default(0);
            $table->unsignedSmallInteger('is_verified')->default(0);
            $table->unsignedInteger('response_duration_minutes')->nullable();
            $table->timestamps();

            $table->foreign('time_key')->references('time_key')->on('dim_time')->nullOnDelete();
            $table->foreign('program_key')->references('program_key')->on('dim_program')->nullOnDelete();
            $table->foreign('alumni_key')->references('alumni_key')->on('dim_alumni')->nullOnDelete();

            $table->index(['time_key', 'program_key']);
            $table->index('alumni_key');
        });
    }

    public function down(): void
    {
        Schema::connection('olap')->dropIfExists('fact_response');
    }
};
