<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Menambah `fact_multi_select.jawaban_lainnya` (VARCHAR(100), nullable).
 *
 * fact_multi_select grain-nya SUDAH per-alumni (1 baris per alumni per
 * indikator boolean yang dicentang, lihat MultiSelectFactBuilderService) --
 * beda dari dim_indikator_evaluasi yang Type1/global (satu baris label per
 * kode_field, dipakai bareng SEMUA alumni yang pernah mencentang opsi itu).
 * Karena itu teks bebas "Lainnya, tuliskan" (mis. f1613->f1614 "alasan kerja
 * tidak sesuai lainnya") TIDAK BOLEH ditimpakan ke dim_indikator_evaluasi
 * (akan menimpa satu label yang sama untuk semua orang) -- harus jadi kolom
 * baru di FACT ini, sama semangatnya dengan companion substitution untuk
 * role narrow (lihat AnswerResolverService::applyCompanionSubstitutions()),
 * tapi sink-nya beda karena grain tabelnya juga beda.
 *
 * PENJAGA PEMASANGAN BARU. Skema OLAP dibangun oleh
 * database/dump/olap_schema.sql saat seeding, bukan oleh migrasi -- dump-nya
 * SUDAH ikut ditambah kolom ini, jadi ALTER di sini murni untuk instance yang
 * sudah kadung ter-provision sebelum kolom ini ada.
 */
return new class extends Migration
{
    protected $connection = 'olap';

    public function up(): void
    {
        if (! Schema::connection('olap')->hasTable('fact_multi_select')
            || Schema::connection('olap')->hasColumn('fact_multi_select', 'jawaban_lainnya')) {
            return;
        }

        DB::connection('olap')->statement(
            'ALTER TABLE public.fact_multi_select ADD COLUMN jawaban_lainnya VARCHAR(100)'
        );
    }

    public function down(): void
    {
        if (! Schema::connection('olap')->hasTable('fact_multi_select')
            || ! Schema::connection('olap')->hasColumn('fact_multi_select', 'jawaban_lainnya')) {
            return;
        }

        DB::connection('olap')->statement(
            'ALTER TABLE public.fact_multi_select DROP COLUMN jawaban_lainnya'
        );
    }
};
