<?php

namespace Database\Seeders;

use Faker\Factory as Faker;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StakeholderContactSeeder extends Seeder
{
    public function run(): void
    {
        $conn = DB::connection('oltp');
        $faker = Faker::create('id_ID');

        // Get alumni who responded with f8 = 1, 3, or 4
        $alumni = $conn->table('response_answers')
            ->where('question_code', 'f8')
            ->whereIn('answer_text', ['1', '3', '4'])
            ->join('responses', 'response_answers.response_id', '=', 'responses.id')
            ->select('responses.alumni_id', 'responses.questionnaire_id', 'response_answers.answer_text as f8')
            ->get();

        $types = ['atasan', 'rekan', 'senior'];
        $statusMap = ['1' => 'bekerja', '3' => 'wiraswasta', '4' => 'lanjut_studi'];
        $now = now();
        $rows = [];

        foreach ($alumni as $a) {
            $count = $faker->numberBetween(1, 3);
            for ($i = 0; $i < $count; $i++) {
                $rows[] = [
                    'alumni_id' => $a->alumni_id,
                    'questionnaire_id' => $a->questionnaire_id,
                    'contact_type' => $types[$i],
                    'contact_name' => $faker->name,
                    'contact_email' => $faker->safeEmail,
                    'alumni_status' => $statusMap[$a->f8] ?? 'bekerja',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if (!empty($rows)) {
            foreach (array_chunk($rows, 200) as $chunk) {
                $conn->table('stakeholder_contacts')->insert($chunk);
            }
        }
    }
}
