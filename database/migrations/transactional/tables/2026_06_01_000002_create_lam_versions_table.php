<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'oltp';

    public function up(): void
    {
        Schema::connection('oltp')->create('lam_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lam_id')
                  ->constrained('lams')
                  ->cascadeOnDelete();
            $table->integer('year');
            $table->string('version_name', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['lam_id', 'year']);
        });
    }

    public function down(): void
    {
        Schema::connection('oltp')->dropIfExists('lam_versions');
    }
};
