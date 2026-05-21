<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            ProgramSeeder::class,
            ProvinceSeeder::class,
            CitySeeder::class,
            UserSeeder::class,
            PermissionSeeder::class,
            QuestionnaireSeeder::class,
            AlumniProfileSeeder::class,
            ResponseSeeder::class,
        ]);
    }
}
