<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * JurusanSeeder — menyelaraskan daftar induk jurusan dengan nilai yang
 * dipakai program studi.
 *
 * WAJIB dipanggil SETELAH ProgramSeeder. Migration create_jurusans_table
 * juga melakukan pengisian awal, tapi itu hanya menolong basis data yang
 * SUDAH berisi data: pada instalasi baru, `migrate` berjalan sebelum
 * `db:seed`, sehingga tabel programs masih kosong saat migration itu
 * dieksekusi dan daftar jurusan akan lahir kosong. Akibatnya dropdown
 * jurusan pada Master Data tidak punya pilihan sama sekali.
 *
 * Idempoten: hanya menambahkan nama yang belum ada, tidak menghapus dan
 * tidak mengubah yang sudah dikelola lewat antarmuka.
 */
class JurusanSeeder extends Seeder
{
    public function run(): void
    {
        $dipakai = DB::connection('oltp')->table('programs')
            ->whereNotNull('jurusan')
            ->where('jurusan', '<>', '')
            ->distinct()
            ->orderBy('jurusan')
            ->pluck('jurusan');

        $sudahAda = DB::connection('oltp')->table('jurusans')->pluck('name')->all();
        $now      = now();
        $baru     = 0;

        foreach ($dipakai as $name) {
            if (in_array($name, $sudahAda, strict: true)) {
                continue;
            }

            DB::connection('oltp')->table('jurusans')->insert([
                'name'       => $name,
                'is_active'  => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $baru++;
        }

        $this->command?->info("JurusanSeeder: {$baru} jurusan ditambahkan, " . count($sudahAda) . ' sudah ada.');
    }
}
