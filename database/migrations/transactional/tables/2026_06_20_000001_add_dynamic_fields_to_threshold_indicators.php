<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menambahkan dua kolom threshold_indicators yang dipakai fitur threshold
 * dinamis sisi OLAP (develop-form) tapi tidak pernah punya migration --
 * sebelumnya hanya hidup di database/dump/init.sql.
 *
 * Dipakai oleh ThresholdIndicatorDTO, ThresholdIndicatorValidator,
 * ThresholdService, ThresholdRepository, dan LamRepository (22 kemunculan).
 * Tanpa kolom ini endpoint threshold-indicators error saat membaca $row.
 *
 * Definisi disalin dari CREATE TABLE tracer_oltp.threshold_indicators di
 * init.sql supaya identik dengan skema yang sudah berjalan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('oltp')->table('threshold_indicators', function (Blueprint $table) {
            // Satuan parameter dinamis (mis. 'bulan' untuk masa tunggu,
            // 'rupiah' untuk UMP). NULL = indikator bukan tipe dinamis.
            $table->string('dynamic_param_unit', 20)->nullable()->after('description');

            // true = nilainya dihitung sistem (mis. response rate lewat
            // formula Slovin), bukan diisi manual oleh kotc.
            $table->boolean('is_system_calculated')->default(false);
        });
    }

    public function down(): void
    {
        Schema::connection('oltp')->table('threshold_indicators', function (Blueprint $table) {
            $table->dropColumn(['dynamic_param_unit', 'is_system_calculated']);
        });
    }
};
