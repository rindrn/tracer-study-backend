<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('oltp')->create('cities', function (Blueprint $table) {
            $table->id();
            $table->string('province_code', 10)->index();
            $table->string('code', 10)->unique();
            $table->string('name', 150);
        });
    }

    public function down(): void
    {
        Schema::connection('oltp')->dropIfExists('cities');
    }
};
