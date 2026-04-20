<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'olap';

    public function up(): void
    {
        Schema::connection('olap')->create('dim_time', function (Blueprint $table) {
            $table->unsignedInteger('time_key')->primary();
            $table->date('full_date')->unique();
            $table->unsignedSmallInteger('day_of_month');
            $table->unsignedSmallInteger('month_of_year');
            $table->unsignedSmallInteger('quarter_of_year');
            $table->unsignedSmallInteger('year_number');
            $table->unsignedSmallInteger('week_of_year');
            $table->string('day_name', 15);
            $table->string('month_name', 15);
            $table->boolean('is_weekend')->default(false);
        });
    }

    public function down(): void
    {
        Schema::connection('olap')->dropIfExists('dim_time');
    }
};
