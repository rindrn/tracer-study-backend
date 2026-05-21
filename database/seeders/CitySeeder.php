<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CitySeeder extends Seeder
{
    public function run(): void
    {
        $data = json_decode(file_get_contents(__DIR__ . '/data/cities.json'), true);

        // Insert in chunks to avoid memory issues
        $rows = array_map(fn ($c) => [
            'province_code' => $c['province_code'],
            'code'          => $c['code'],
            'name'          => $c['name'],
        ], $data);

        foreach (array_chunk($rows, 100) as $chunk) {
            DB::connection('oltp')->table('cities')->insert($chunk);
        }
    }
}
