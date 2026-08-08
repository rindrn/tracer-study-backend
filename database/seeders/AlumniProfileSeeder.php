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
        $graduationYears = [2020, 2021, 2022, 2023, 2024, 2025];

        foreach ($programs as $program) {
            // Sengaja diacak per prodi (120-220 alumni), bukan dibagi rata dari
            // satu total tetap -- supaya jumlah data per prodi bervariasi
            // (ratusan, bukan cuma belasan/puluhan). Dengan denominator sebesar
            // ini, persentase breakdown per kategori (mis. Sebaran Level
            // Perusahaan) berhenti selalu jatuh ke kelipatan bersih 5/10/25
            // (25%/50%/75%/100%) dan mulai menghasilkan angka ganjil yang lebih
            // realistis seperti 23%, 51%, 73%.
            $perProgram = $faker->numberBetween(120, 220);

            for ($i = 0; $i < $perProgram; $i++) {
                $tahunLulus = $faker->randomElement($graduationYears);
                $duration = $program->degree === 'D3' ? 3 : 4;
                $tahunMasuk = $tahunLulus - $duration;

                $alumniData[] = [
                    'program_id'      => $program->id,
                    'nim'             => $tahunMasuk . str_pad($nimCounter, 10, '0', STR_PAD_LEFT),
                    'name'            => $faker->name,
                    'email'           => $faker->unique()->safeEmail,
                    'phone'           => '+62' . $faker->numerify('8########'),
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

        foreach (array_chunk($alumniData, 100) as $chunk) {
            DB::connection('oltp')->table('alumni_profiles')->insert($chunk);
        }
    }
}
