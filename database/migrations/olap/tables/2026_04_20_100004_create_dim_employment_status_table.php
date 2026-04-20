<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'olap';

    public function up(): void
    {
        Schema::connection('olap')->create('dim_employment_status', function (Blueprint $table) {
            $table->id('employment_status_key');
            $table->string('status_code', 30)->unique();
            $table->string('status_label', 100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('olap')->dropIfExists('dim_employment_status');
    }
};
