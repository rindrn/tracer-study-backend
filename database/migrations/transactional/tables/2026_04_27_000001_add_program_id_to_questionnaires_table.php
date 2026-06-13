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
            $table->foreignId('program_id')
                ->nullable()
                ->after('status')
                ->constrained('programs')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::connection('oltp')->table('questionnaires', function (Blueprint $table) {
            $table->dropForeign(['program_id']);
            $table->dropColumn('program_id');
        });
    }
};
