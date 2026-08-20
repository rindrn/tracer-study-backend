<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Persetujuan eksplisit alumni atas pemrosesan data pribadinya.
 *
 * Kolom `consent_at` pernah ada lalu dibuang di migration 2026_05_25_000001
 * dengan alasan "tidak ada fitur consent screen di app". Alasannya benar saat
 * itu; yang salah adalah kesimpulannya — yang kurang bukan kolomnya,
 * melainkan layarnya. UU 27/2022 Pasal 20 menempatkan persetujuan yang sah
 * sebagai dasar pemrosesan, dan persetujuan yang tidak tercatat sama saja
 * dengan tidak ada.
 *
 * Tiga kolom, bukan satu:
 *
 *   - `consent_at`      kapan disetujui
 *   - `consent_version` versi pemberitahuan privasi yang disetujui. Tanpa ini
 *                       tidak ada cara membedakan alumni yang menyetujui teks
 *                       lama dari yang menyetujui teks sekarang, padahal
 *                       perubahan materiil pada teks mewajibkan persetujuan
 *                       ulang. Sumbernya config('privacy.notice_version').
 *   - `consent_ip`      alamat asal saat menyetujui, sebagai bukti pendukung
 *                       kalau persetujuannya dipersoalkan.
 *
 * Penarikan persetujuan tidak diberi kolom tersendiri: menariknya berarti
 * mengosongkan ketiga kolom ini, dan peristiwanya sendiri tercatat di
 * `audit_logs` sebagai `consent.withdrawn`. Menyimpan tanggal tarik di sini
 * akan membuat dua sumber kebenaran untuk satu keadaan.
 */
return new class extends Migration
{
    protected $connection = 'oltp';

    public function up(): void
    {
        Schema::connection('oltp')->table('alumni_profiles', function (Blueprint $table) {
            $table->timestamp('consent_at')->nullable()->after('gpa');
            $table->string('consent_version', 20)->nullable()->after('consent_at');
            $table->string('consent_ip', 45)->nullable()->after('consent_version');
        });
    }

    public function down(): void
    {
        Schema::connection('oltp')->table('alumni_profiles', function (Blueprint $table) {
            $table->dropColumn(['consent_at', 'consent_version', 'consent_ip']);
        });
    }
};
