<?php

use App\Services\ETL\OrgUnitHierarchyResolverService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * DFR-17/DFR-18 (Fase 3): kolom hierarki bertingkat tetap di `dim_prodi`.
 *
 * `level_1_name` .. `level_5_name` -- BUKAN JSONB. Cube.js punya 7
 * pre-aggregation di FactTracerStudy.js yang masing-masing mendaftarkan
 * DimProdi.jenjang/jurusan/nama_prodi sebagai dimension bertipe eksplisit;
 * blob JSONB berkedalaman variabel tidak bisa langsung jadi dimension
 * pre-agg. Lihat catatan revisi di cetak-biru-struktur-dinamis.md.
 *
 * `level_index` di org_unit_types dipetakan LANGSUNG ke level_N_name
 * (level_index 1 -> level_1_name, dst) -- lihat
 * OrgUnitHierarchyResolverService. Level yang tidak dipakai template aktif
 * (mis. level 2-5 pada Politeknik yang cuma 1 level) dibiarkan NULL.
 *
 * PENTING soal SCD Type 2 (pola sama dengan migration
 * 2026_08_20_000002_add_institution_and_accreditation_to_dim_prodi): versi
 * yang sudah tutup (flag_prodi = false) SENGAJA tidak ikut diisi ulang di
 * sini. Struktur organisasi pada masa berlaku versi lama itu tidak kita
 * ketahui persis (org_units baru dibuat Fase 1, sesudah data historis
 * ETL ada) -- membiarkannya NULL jujur, mengisi dengan struktur hari ini
 * akan memalsukan sejarah. Hanya versi yang sedang aktif yang diisi.
 *
 * `jurusan`/`jenjang`/`nama_prodi` existing TIDAK disentuh sama sekali --
 * kolom baru ini murni aditif, 7 pre-aggregation existing tetap memakai
 * dimension lama tanpa perubahan.
 */
return new class extends Migration
{
    protected $connection = 'olap';

    public function up(): void
    {
        Schema::connection('olap')->table('dim_prodi', function (Blueprint $table) {
            $table->string('level_1_name', 150)->nullable()->after('jenjang');
            $table->string('level_2_name', 150)->nullable()->after('level_1_name');
            $table->string('level_3_name', 150)->nullable()->after('level_2_name');
            $table->string('level_4_name', 150)->nullable()->after('level_3_name');
            $table->string('level_5_name', 150)->nullable()->after('level_4_name');
        });

        // Isi versi aktif saja, supaya dashboard tidak menunggu ETL
        // mingguan berikutnya jalan -- lihat catatan SCD di atas. Dipanggil
        // per baris karena resolusinya menelusuri pohon org_units
        // (adjacency list walk-up), bukan operasi SQL set-based.
        $resolver = app(OrgUnitHierarchyResolverService::class);

        $activeVersions = DB::connection('olap')->table('dim_prodi')
            ->where('flag_prodi', true)
            ->get(['prodi_sk', 'id_prodi', 'jurusan']);

        $programsById = DB::connection('oltp')->table('programs')
            ->get(['id', 'org_unit_id'])
            ->keyBy('id');

        foreach ($activeVersions as $version) {
            $program = $programsById->get($version->id_prodi);
            $orgUnitId = $program?->org_unit_id !== null ? (int) $program->org_unit_id : null;

            $levels = $resolver->resolve($orgUnitId, $version->jurusan);

            DB::connection('olap')->table('dim_prodi')
                ->where('prodi_sk', $version->prodi_sk)
                ->update($levels);
        }
    }

    public function down(): void
    {
        Schema::connection('olap')->table('dim_prodi', function (Blueprint $table) {
            $table->dropColumn(['level_1_name', 'level_2_name', 'level_3_name', 'level_4_name', 'level_5_name']);
        });
    }
};
