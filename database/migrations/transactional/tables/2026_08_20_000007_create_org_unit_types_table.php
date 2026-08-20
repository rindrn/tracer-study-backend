<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * org_unit_types — katalog level hierarki organisasi per jenis institusi
 * (Fase 1 dari rencana struktur dinamis, DFR-02/DFR-03).
 *
 * Satu baris = satu level pada satu template institusi, mis. (politeknik,
 * level 1, "Jurusan") atau (universitas, level 2, "Departemen"). Tabel ini
 * SENGAJA memuat preset ketiga jenis institusi sekaligus, bukan cuma
 * template yang aktif -- supaya beralih template (Fase 2+) tidak perlu
 * migration baru, cukup ganti nilai `institution.structure_template`
 * (lihat app_settings key `institution_structure_template` yang ikut
 * di-seed migration ini) dan pilih baris `institution_type` yang sesuai.
 *
 * `org_units.org_unit_type_id` menunjuk ke sini, sehingga level mana yang
 * "sedang dipakai" ditentukan oleh baris org_units yang benar-benar ada,
 * bukan oleh flag terpisah -- itulah dasar guard DFR-05 (lihat
 * OrgUnitTypeService::assertMutable()): baris org_unit_types tidak boleh
 * diubah urutan/dihapus begitu ada org_units yang memakainya.
 */
return new class extends Migration {
    protected $connection = 'oltp';

    public function up(): void
    {
        Schema::connection('oltp')->create('org_unit_types', function (Blueprint $table) {
            $table->id();
            $table->string('institution_type', 30);
            $table->unsignedTinyInteger('level_index');
            $table->string('label', 100);
            $table->boolean('is_required')->default(true);
            $table->timestamps();

            // Satu level_index hanya boleh muncul sekali per jenis institusi
            // -- ini yang menjaga urutan level tidak ambigu.
            $table->unique(['institution_type', 'level_index']);
        });

        $now = now();

        // Preset per DFR-02. "custom" (DFR-04) sengaja tidak diisi baris
        // bawaan -- level custom didefinisikan admin sendiri lewat wizard di
        // Fase 4, tabel ini hanya perlu terima institution_type = 'custom'.
        DB::connection('oltp')->table('org_unit_types')->insert([
            [
                'institution_type' => 'politeknik',
                'level_index'      => 1,
                'label'            => 'Jurusan',
                'is_required'      => true,
                'created_at'       => $now,
                'updated_at'       => $now,
            ],
            [
                'institution_type' => 'universitas',
                'level_index'      => 1,
                'label'            => 'Fakultas',
                'is_required'      => true,
                'created_at'       => $now,
                'updated_at'       => $now,
            ],
            [
                'institution_type' => 'universitas',
                'level_index'      => 2,
                'label'            => 'Departemen',
                'is_required'      => true,
                'created_at'       => $now,
                'updated_at'       => $now,
            ],
            [
                'institution_type' => 'institut',
                'level_index'      => 1,
                'label'            => 'Fakultas/Sekolah',
                'is_required'      => true,
                'created_at'       => $now,
                'updated_at'       => $now,
            ],
            [
                'institution_type' => 'institut',
                'level_index'      => 2,
                'label'            => 'Departemen',
                'is_required'      => false,
                'created_at'       => $now,
                'updated_at'       => $now,
            ],
        ]);

        // DFR-25: flag rollout tersimpan di app_settings (tabel key-value
        // yang sudah ada) supaya tidak perlu kolom/tabel baru untuk satu
        // nilai konfigurasi. Dibaca lewat AppSettingRepository, sumber
        // kebenaran memakai `institution.structure_template` (config/env)
        // -- baris ini nilai bawaannya, bukan sumber ganda: lihat
        // config/institution.php.
        if (Schema::connection('oltp')->hasTable('app_settings')) {
            $exists = DB::connection('oltp')->table('app_settings')
                ->where('key', 'institution_structure_template')
                ->exists();

            if (! $exists) {
                DB::connection('oltp')->table('app_settings')->insert([
                    'key'         => 'institution_structure_template',
                    'value'       => config('institution.structure_template', 'politeknik'),
                    'description' => 'Jenis template struktur organisasi aktif (politeknik/universitas/institut/custom) -- DFR-25.',
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::connection('oltp')->hasTable('app_settings')) {
            DB::connection('oltp')->table('app_settings')
                ->where('key', 'institution_structure_template')
                ->delete();
        }

        Schema::connection('oltp')->dropIfExists('org_unit_types');
    }
};
