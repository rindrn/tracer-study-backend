<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 1.5 — Persiapan workflow email blast & alumni login dengan password.
 *
 * 1. alumni_profiles.password
 *    Alumni akan login pakai email + password (per arahan dosen).
 *    Sebelumnya AlumniAuthService menerima NIM/6-digit-NIK sebagai password
 *    "default" tanpa hash. Kolom ini menyimpan hash bcrypt.
 *    Nullable supaya alumni lama yang belum set password tetap masuk
 *    (akan ada flow "set password pertama kali" via link email).
 *
 * 2. questionnaire_assignments — email tracking columns
 *    Workflow email blast:
 *      assigned → email_sent_at terisi → opened_at terisi (alumni klik
 *      link & buka form) → submitted_at terisi (via responses).
 *    last_reminder_at + reminder_count untuk reminder berkala.
 */
return new class extends Migration
{
    protected $connection = 'oltp';

    public function up(): void
    {
        Schema::connection('oltp')->table('alumni_profiles', function (Blueprint $table) {
            $table->string('password', 255)->nullable()->after('phone');
        });

        Schema::connection('oltp')->table('questionnaire_assignments', function (Blueprint $table) {
            $table->timestamp('email_sent_at')->nullable()->after('due_at');
            $table->timestamp('last_reminder_at')->nullable()->after('email_sent_at');
            $table->unsignedSmallInteger('reminder_count')->default(0)->after('last_reminder_at');
            $table->timestamp('opened_at')->nullable()->after('reminder_count');
        });
    }

    public function down(): void
    {
        Schema::connection('oltp')->table('questionnaire_assignments', function (Blueprint $table) {
            $table->dropColumn(['email_sent_at', 'last_reminder_at', 'reminder_count', 'opened_at']);
        });

        Schema::connection('oltp')->table('alumni_profiles', function (Blueprint $table) {
            $table->dropColumn('password');
        });
    }
};
