<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Faker\Factory as Faker;
use Carbon\Carbon;

class ResponseSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create('id_ID');
        $now = Carbon::now();

        $alumniList = DB::connection('oltp')->table('alumni_profiles')->get();
        $qGlobal = DB::connection('oltp')->table('questionnaires')->whereNull('program_id')->first();

        if (!$qGlobal || $alumniList->isEmpty()) return;

        foreach ($alumniList as $index => $alumni) {
            // Hanya 20 orang yang seolah-olah sudah mengisi kuesioner
            if ($index >= 20) break; 

            // 1. Buat Header Response
            $responseId = DB::connection('oltp')->table('responses')->insertGetId([
                'questionnaire_id' => $qGlobal->id,
                'alumni_id' => $alumni->id,
                'status' => 'submitted',
                'submitted_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $answers = [];

            // 2. Simulasi Jawaban Kementrian
            $statusKerja = $faker->randomElement(['1', '3', '4', '5']); // 1:Kerja, 3:Wiraswasta, 4:Studi, 5:Nganggur
            $answers[] = [
                'response_id' => $responseId,
                'question_code' => 'f8',
                'answer_text' => $statusKerja,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if ($statusKerja == '1' || $statusKerja == '3') {
                $salary = $faker->numberBetween(3000000, 15000000);
                $answers[] = [
                    'response_id' => $responseId,
                    'question_code' => 'f502',
                    'answer_text' => (string) $salary, 
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                // --- DATA DASHBOARD FE ---
                // Menyuntikkan data ke tabel employment_records agar grafik di Frontend (OLAP) bisa menyala
                DB::connection('oltp')->table('employment_records')->insert([
                    'alumni_id' => $alumni->id,
                    'company_name' => $faker->company,
                    'job_title' => $faker->jobTitle,
                    'monthly_salary' => $salary,
                    'job_type' => $statusKerja == '1' ? 'full_time' : 'wiraswasta',
                    'start_date' => $faker->dateTimeBetween('-1 years', 'now')->format('Y-m-d'),
                    'is_current' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            // Jika status kerja adalah Melanjutkan Pendidikan
            if ($statusKerja == '4') {
                DB::connection('oltp')->table('education_records')->insert([
                    'alumni_id' => $alumni->id,
                    'institution_name' => 'Universitas ' . $faker->city,
                    'degree' => 'S2',
                    'major' => 'Manajemen/Teknik',
                    'start_date' => $faker->dateTimeBetween('-6 months', 'now')->format('Y-m-d'),
                    'is_current' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            // 3. Simulasi Jawaban Prodi (Jika dia anak Teknik Informatika)
            $tiProgram = DB::connection('oltp')->table('programs')->where('code', 'TI')->first();
            if ($tiProgram && $alumni->program_id == $tiProgram->id) {
                $answers[] = [
                    'response_id' => $responseId,
                    'question_code' => 'q_framework',
                    'answer_text' => $faker->randomElement(['Laravel', 'React', 'Vue', 'Spring Boot', 'Express']),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                $answers[] = [
                    'response_id' => $responseId,
                    'question_code' => 'q_sertifikasi',
                    'answer_text' => $faker->randomElement(['1', '0']),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            DB::connection('oltp')->table('response_answers')->insert($answers);
        }
    }
}
