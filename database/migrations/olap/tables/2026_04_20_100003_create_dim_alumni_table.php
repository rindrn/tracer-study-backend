<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'olap';

    public function up(): void
    {
        Schema::connection('olap')->create('dim_alumni', function (Blueprint $table) {
            $table->id('alumni_key');
            $table->unsignedBigInteger('alumni_id')->unique();
            $table->unsignedBigInteger('program_id')->nullable();
            $table->unsignedSmallInteger('entry_year')->nullable();
            $table->unsignedSmallInteger('graduation_year')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['program_id', 'graduation_year']);
        });
    }

    public function down(): void
    {
        Schema::connection('olap')->dropIfExists('dim_alumni');
    }
};
