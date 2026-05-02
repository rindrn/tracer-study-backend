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

        $programs = DB::connection('oltp')->table('programs')->pluck('id')->toArray();
        if (empty($programs)) return;

        $alumniData = [];

        // Membuat 30 Data Alumni dengan nama Indonesia yang realistis
        for ($i = 0; $i < 30; $i++) {
            $tahunLulus = $faker->numberBetween(2022, 2025);
            $tahunMasuk = $tahunLulus - 4;

            $alumniData[] = [
                'program_id' => $faker->randomElement($programs),
                'nim' => $tahunMasuk . $faker->numerify('##########'), // Format NIM realistis
                'name' => $faker->name, // Nama Indonesia (Budi, Siti, dll)
                'email' => $faker->unique()->safeEmail,
                'phone' => $faker->phoneNumber,
                'entry_year' => $tahunMasuk,
                'graduation_year' => $tahunLulus,
                'gpa' => $faker->randomFloat(2, 2.7, 4.0),
                'nik' => $faker->numerify('################'), // 16 digit NIK
                'npwp' => $faker->numerify('####################'), // 20 digit NPWP
                'kode_pt' => '001001',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::connection('oltp')->table('alumni_profiles')->insert($alumniData);
    }
}
