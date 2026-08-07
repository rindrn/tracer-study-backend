<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penanda kapan kredensial alumni terakhir diterbitkan (RBAC-16).
 *
 * Kolom `password` sendiri sudah ada sejak 2026_05_25_000002, tapi tidak
 * pernah terisi karena tidak ada satu pun jalur tulis. Penanda ini yang
 * membuat penerbitan bisa dikelola: Tim Tracer perlu tahu siapa yang sudah
 * dikirimi kredensial dan siapa yang belum, terutama saat penerbitan
 * dilakukan bertahap per angkatan atau per program studi.
 *
 * Tanpa penanda ini satu-satunya cara membedakan adalah `password IS NULL`,
 * yang hanya menjawab "pernah atau belum" dan hilang maknanya begitu
 * kredensial diterbitkan ulang.
 */
return new class extends Migration
{
    protected $connection = 'oltp';

    public function up(): void
    {
        Schema::connection('oltp')->table('alumni_profiles', function (Blueprint $table) {
            $table->timestamp('password_issued_at')->nullable()->after('password');
        });
    }

    public function down(): void
    {
        Schema::connection('oltp')->table('alumni_profiles', function (Blueprint $table) {
            $table->dropColumn('password_issued_at');
        });
    }
};
