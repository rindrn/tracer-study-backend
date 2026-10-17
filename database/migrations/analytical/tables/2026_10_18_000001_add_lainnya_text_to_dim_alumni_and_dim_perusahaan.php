<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Menambah kolom teks isian "Lainnya, tuliskan" (VARCHAR(100), nullable):
 *   - dim_alumni.sumber_biaya_lainnya        (f1201 -> f1202)
 *   - dim_perusahaan.jenis_perusahaan_lainnya (f1101 -> f1102)
 *
 * Sebelumnya teks ketikan ditimpakan ke label kategorinya, sehingga tiap
 * ketikan berbeda menjadi kelompok sendiri di dimensi. Sekarang labelnya
 * tetap "Lainnya" dan ketikannya disimpan di kolom ini, untuk ditampilkan
 * saat drill-through (lihat AnswerResolverService::applyCompanionSubstitutions()).
 *
 * PENJAGA PEMASANGAN BARU. Skema OLAP dibangun oleh
 * database/dump/olap_schema.sql saat seeding, bukan oleh migrasi -- dump-nya
 * SUDAH ikut ditambah kolom ini, jadi ALTER di sini murni untuk instance yang
 * sudah kadung ter-provision sebelum kolom ini ada.
 */
return new class extends Migration
{
    protected $connection = 'olap';

    private const COLUMNS = [
        'dim_alumni'     => 'sumber_biaya_lainnya',
        'dim_perusahaan' => 'jenis_perusahaan_lainnya',
    ];

    public function up(): void
    {
        foreach (self::COLUMNS as $table => $column) {
            if (! Schema::connection('olap')->hasTable($table)
                || Schema::connection('olap')->hasColumn($table, $column)) {
                continue;
            }

            DB::connection('olap')->statement("ALTER TABLE public.{$table} ADD COLUMN {$column} VARCHAR(100)");
        }
    }

    public function down(): void
    {
        foreach (self::COLUMNS as $table => $column) {
            if (! Schema::connection('olap')->hasTable($table)
                || ! Schema::connection('olap')->hasColumn($table, $column)) {
                continue;
            }

            DB::connection('olap')->statement("ALTER TABLE public.{$table} DROP COLUMN {$column}");
        }
    }
};
