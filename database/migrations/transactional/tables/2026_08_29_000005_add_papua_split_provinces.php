<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * BPS memecah wilayah administrasi Papua jadi 4 provinsi baru (Papua Barat
 * Daya, Papua Selatan, Papua Tengah, Papua Pegunungan), sehingga webapi UMP
 * BPS sekarang mengembalikan 38 vervar, bukan 34. `provinces.id` dipakai
 * sebagai FK nyata (ref_ump.province_id) dan disimpan mentah di jawaban
 * kuesioner alumni (kode f5a1), jadi baris lama TIDAK BOLEH digeser -- 4
 * baris baru ini harus nambah di ujung supaya id-nya konsisten dengan
 * database/seeders/data/provinces.json (lihat urutan yang sama di sana)
 * dan dengan UmpBpsService::BPS_VERVAR_TO_PROVINCE_ID.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Instalasi baru: tabel provinces masih kosong di titik ini (seeder
        // jalan belakangan secara manual) dan provinces.json sudah memuat 4
        // baris ini di posisi yang sama -- migration ini tidak boleh ikut
        // campur, supaya id 36-39 tidak dobel/salah urut.
        if (! DB::connection('oltp')->table('provinces')->exists()) {
            return;
        }

        DB::connection('oltp')->table('provinces')->insertOrIgnore([
            ['code' => '360000', 'name' => 'Prov. Papua Barat Daya'],
            ['code' => '370000', 'name' => 'Prov. Papua Selatan'],
            ['code' => '380000', 'name' => 'Prov. Papua Tengah'],
            ['code' => '390000', 'name' => 'Prov. Papua Pegunungan'],
        ]);
    }

    public function down(): void
    {
        DB::connection('oltp')->table('provinces')
            ->whereIn('code', ['360000', '370000', '380000', '390000'])
            ->delete();
    }
};
