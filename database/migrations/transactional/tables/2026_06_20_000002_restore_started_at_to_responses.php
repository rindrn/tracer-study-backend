<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mengembalikan responses.started_at yang di-drop 2026_05_25_000001.
 *
 * Migration itu beralasan kolomnya "tidak pernah di-set (response langsung
 * submitted)" -- benar dari sisi OLTP, tapi lapisan analitik masih membacanya:
 *
 *   - SummaryRepository       : rata-rata durasi pengisian (started_at -> submitted_at)
 *   - ResponseRateRepository  : kolom started_at pada drill-down responden
 *
 * Tanpa kolom ini GET /api/dashboard/overview/summary gagal dengan
 * "column r.started_at does not exist", jadi seluruh kartu ringkasan dashboard
 * mati. Dikembalikan sebagai nullable supaya baris lama tetap valid.
 *
 * Catatan: selama alur submit belum mengisi started_at, metrik durasi pengisian
 * akan NULL. Itu keterbatasan data, bukan error -- dan lebih baik daripada
 * endpoint yang crash. Kalau metrik itu memang mau dipakai,
 * TracerStudySubmitService perlu mengisi started_at saat responden mulai.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('oltp')->table('responses', function (Blueprint $table) {
            $table->timestamp('started_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::connection('oltp')->table('responses', function (Blueprint $table) {
            $table->dropColumn('started_at');
        });
    }
};
