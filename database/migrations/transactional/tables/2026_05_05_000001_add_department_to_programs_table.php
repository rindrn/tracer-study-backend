<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'oltp';

    public function up(): void
    {
        Schema::connection('oltp')->table('programs', function (Blueprint $table) {
            $table->string('jurusan', 100)->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::connection('oltp')->table('programs', function (Blueprint $table) {
            $table->dropColumn('jurusan');
        });
    }
};
