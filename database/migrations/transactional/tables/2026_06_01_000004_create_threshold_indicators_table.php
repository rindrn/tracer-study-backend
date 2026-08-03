<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'oltp';

    public function up(): void
    {
        Schema::connection('oltp')->create('threshold_indicators', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('key', 50)->unique();
            $table->string('name', 100);
            $table->string('unit', 20)->default('%');
            $table->string('operator', 10)->default('>=');
            $table->text('description')->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection('oltp')->dropIfExists('threshold_indicators');
    }
};
