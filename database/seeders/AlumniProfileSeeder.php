<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Faker\Factory as Faker;
use Carbon\Carbon;

class AlumniProfileSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create('id_ID');
        $now = Carbon::now();

        $programs = DB::connection('oltp')->table('programs')->get();
        if ($programs->isEmpty()) return;

        $alumniData = [];
        $nimCounter = 1;
        $fixedYears = [2022, 2023, 2024];

        foreach ($programs as $program) {
            for ($i = 0; $i < 5; $i++) {
                // First 3: fixed grad years; last 2: random
                $tahunLulus = $i < 3 ? $fixedYears[$i] : $faker->randomElement($fixedYears);
                $tahunMasuk = $tahunLulus - ($program->degree === 'D3' ? 3 : 4);

                $alumniData[] = [
                    'program_id'      => $program->id,
                    'nim'             => $tahunMasuk . str_pad($nimCounter, 10, '0', STR_PAD_LEFT),
                    'name'            => $faker->name,
                    'email'           => $faker->unique()->safeEmail,
                    'phone'           => $faker->phoneNumber,
                    'entry_year'      => $tahunMasuk,
                    'graduation_year' => $tahunLulus,
                    'gpa'             => $faker->randomFloat(2, 2.7, 4.0),
                    'nik'             => $faker->numerify('################'),
                    'npwp'            => $faker->numerify('####################'),
                    'kode_pt'         => '001001',
                    'is_active'       => true,
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ];
                $nimCounter++;
            }
        }

        foreach (array_chunk($alumniData, 50) as $chunk) {
            DB::connection('oltp')->table('alumni_profiles')->insert($chunk);
        }
    }
}
