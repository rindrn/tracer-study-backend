<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'oltp';

    public function up(): void
    {
        Schema::connection('oltp')->table('questionnaires', function (Blueprint $table) {
            $table->jsonb('target_graduation_years')
                  ->nullable()
                  ->after('period_year');
        });
    }

    public function down(): void
    {
        Schema::connection('oltp')->table('questionnaires', function (Blueprint $table) {
            $table->dropColumn('target_graduation_years');
        });
    }
};
