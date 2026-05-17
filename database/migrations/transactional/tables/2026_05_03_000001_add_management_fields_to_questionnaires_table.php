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
            $table->text('description')->nullable()->after('title');
            $table->string('target', 255)->nullable()->after('status');
            $table->json('sample_respondents')->nullable()->after('target');
        });
    }

    public function down(): void
    {
        Schema::connection('oltp')->table('questionnaires', function (Blueprint $table) {
            $table->dropColumn(['description', 'target', 'sample_respondents']);
        });
    }
};