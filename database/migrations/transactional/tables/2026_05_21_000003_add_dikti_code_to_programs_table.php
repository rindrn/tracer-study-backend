<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('oltp')->table('programs', function (Blueprint $table) {
            $table->string('dikti_code', 10)->nullable()->after('code');
        });
    }

    public function down(): void
    {
        Schema::connection('oltp')->table('programs', function (Blueprint $table) {
            $table->dropColumn('dikti_code');
        });
    }
};
