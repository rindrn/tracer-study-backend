<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'oltp';

    public function up(): void
    {
        Schema::connection('oltp')->table('alumni_profiles', function (Blueprint $table) {
            $table->string('nik', 16)->nullable()->after('email');
            $table->string('npwp', 20)->nullable()->after('nik');
            $table->string('kode_pt', 10)->nullable()->after('npwp');
        });
    }

    public function down(): void
    {
        Schema::connection('oltp')->table('alumni_profiles', function (Blueprint $table) {
            $table->dropColumn(['nik', 'npwp', 'kode_pt']);
        });
    }
};
