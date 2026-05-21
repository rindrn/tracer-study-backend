<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    protected $connection = 'oltp';

    public function up(): void
    {
        Schema::connection('oltp')->table('roles', function (Blueprint $table) {
            $table->string('scope', 100)->nullable()->after('description');
        });

        DB::connection('oltp')->table('roles')->where('name', 'head_tracer')->update(['scope' => 'Seluruh Jurusan']);
        DB::connection('oltp')->table('roles')->where('name', 'tracer_team')->update(['scope' => 'Seluruh Jurusan']);
        DB::connection('oltp')->table('roles')->where('name', 'wadir')->update(['scope' => 'Seluruh Jurusan']);
        DB::connection('oltp')->table('roles')->where('name', 'kajur')->update(['scope' => 'Jurusan']);
        DB::connection('oltp')->table('roles')->where('name', 'kaprodi')->update(['scope' => 'Program Studi']);
    }

    public function down(): void
    {
        Schema::connection('oltp')->table('roles', function (Blueprint $table) {
            $table->dropColumn('scope');
        });
    }
};
