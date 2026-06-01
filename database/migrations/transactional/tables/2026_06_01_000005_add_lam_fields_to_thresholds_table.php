<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'oltp';

    public function up(): void
    {
        Schema::connection('oltp')->table('thresholds', function (Blueprint $table) {
            $table->foreignId('lam_version_id')
                  ->nullable()
                  ->constrained('lam_versions')
                  ->cascadeOnDelete();
            $table->foreignId('indicator_id')
                  ->nullable()
                  ->constrained('threshold_indicators')
                  ->restrictOnDelete();
            $table->string('level', 10)->default('baik');

            $table->unique(['lam_version_id', 'indicator_id', 'level']);
        });
    }

    public function down(): void
    {
        Schema::connection('oltp')->table('thresholds', function (Blueprint $table) {
            $table->dropUnique(['lam_version_id', 'indicator_id', 'level']);
            $table->dropConstrainedForeignId('indicator_id');
            $table->dropConstrainedForeignId('lam_version_id');
            $table->dropColumn('level');
        });
    }
};
