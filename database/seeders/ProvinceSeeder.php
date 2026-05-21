<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProvinceSeeder extends Seeder
{
    public function run(): void
    {
        $data = json_decode(file_get_contents(__DIR__ . '/data/provinces.json'), true);

        $rows = array_map(fn ($p) => ['code' => $p['code'], 'name' => $p['name']], $data);

        DB::connection('oltp')->table('provinces')->insert($rows);
    }
}
