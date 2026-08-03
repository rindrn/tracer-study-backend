<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class QuestionnaireAssignmentSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();
        $conn = DB::connection('oltp');

        $questionnaires = $conn->table('questionnaires')
            ->where('status', 'published')
            ->get();

        $alumni = $conn->table('alumni_profiles')->get();

        $assignments = [];

        foreach ($questionnaires as $qnr) {
            $targetYears = $qnr->target_graduation_years
                ? json_decode($qnr->target_graduation_years, true)
                : [];

            foreach ($alumni as $alum) {
                // Filter by target graduation years
                if (!empty($targetYears) && !in_array((int) $alum->graduation_year, $targetYears)) {
                    continue;
                }

                // Filter by program_id (prodi questionnaire only for matching program)
                if ($qnr->program_id && $qnr->program_id != $alum->program_id) {
                    continue;
                }

                $assignments[] = [
                    'questionnaire_id' => $qnr->id,
                    'alumni_id'        => $alum->id,
                    'assigned_by'      => null,
                    'assigned_at'      => $now,
                    'due_at'           => $now->copy()->addMonths(3),
                    'status'           => 'assigned',
                    'created_at'       => $now,
                    'updated_at'       => $now,
                ];
            }
        }

        foreach (array_chunk($assignments, 200) as $chunk) {
            $conn->table('questionnaire_assignments')->insert($chunk);
        }
    }
}
