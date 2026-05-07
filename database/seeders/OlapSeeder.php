<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class OlapSeeder extends Seeder
{
    /**
     * Sync data from OLTP (oltp schema) → OLAP (olap schema).
     * Uses ON CONFLICT DO UPDATE so it is idempotent — safe to re-run.
     *
     * Star schema tables populated:
     *   dim_time, dim_program, dim_alumni,
     *   dim_employment_status,
     *   fact_response, fact_employment_outcome
     */
    public function run(): void
    {
        $oltp = DB::connection('oltp');
        $olap = DB::connection('olap');
        $now = Carbon::now();

        // ── 1. dim_time ──────────────────────────────────────────────────────────
        // Generate one row per date (graduation_month resolution to keep it manageable).
        // Creates rows for years 2020–2030 covering all possible graduation years.
        $years = range(2020, 2030);
        $timeRecords = [];

        foreach ($years as $year) {
            foreach (range(1, 12) as $month) {
                $date = Carbon::createFromDate($year, $month, 1);
                $timeKey = (int) $date->format('Ym'); // e.g. 202403
                $timeRecords[$timeKey] = [
                    'time_key'        => $timeKey,
                    'full_date'       => $date->toDateString(),
                    'day_of_month'    => $date->day,
                    'month_of_year'   => $date->month,
                    'quarter_of_year' => $date->quarter,
                    'year_number'     => $date->year,
                    'week_of_year'    => $date->weekOfYear,
                    'day_name'        => $date->dayName,
                    'month_name'      => $date->monthName,
                    'is_weekend'      => in_array($date->dayOfWeek, [Carbon::SUNDAY, Carbon::SATURDAY]),
                ];
            }
        }

        foreach ($timeRecords as $row) {
            $olap->table('dim_time')->updateOrInsert(
                ['time_key' => $row['time_key']],
                $row
            );
        }

        $this->command->info('dim_time: ' . count($timeRecords) . ' rows upserted.');

        // ── 2. dim_program ──────────────────────────────────────────────────────
        $programs = $oltp->table('programs')->get();
        $programKeyMap = [];
        $programCount = 0;

        foreach ($programs as $p) {
            $key = $olap->table('dim_program')->updateOrInsert(
                ['program_id' => $p->id],
                [
                    'code'           => $p->code,
                    'name'           => $p->name,
                    'degree'         => $p->degree,
                    'is_active'      => $p->is_active ?? true,
                    'current_flag'   => true,
                    'effective_from' => $now,
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ]
            );

            // Retrieve the program_key (auto-increment PK) for mapping
            $dim = $olap->table('dim_program')->where('program_id', $p->id)->first();
            if ($dim) {
                $programKeyMap[$p->id] = $dim->program_key;
                $programCount++;
            }
        }

        $this->command->info("dim_program: {$programCount} rows upserted.");

        // ── 3. dim_employment_status ────────────────────────────────────────────
        $empStatuses = [
            ['employed',     'Bekerja'],
            ['entrepreneur', 'Wiraswasta'],
            ['further_study', 'Melanjutkan Pendidikan'],
            ['seeking_work',  'Mencari Kerja'],
            ['other',         'Lainnya'],
        ];

        $empStatusKeyMap = [];
        $empCount = 0;
        foreach ($empStatuses as $s) {
            $olap->table('dim_employment_status')->updateOrInsert(
                ['status_code' => $s[0]],
                [
                    'status_label' => $s[1],
                    'is_active'    => true,
                    'created_at'   => $now,
                    'updated_at'   => $now,
                ]
            );
            $dim = $olap->table('dim_employment_status')->where('status_code', $s[0])->first();
            if ($dim) {
                $empStatusKeyMap[$s[0]] = $dim->employment_status_key;
                $empCount++;
            }
        }

        $this->command->info("dim_employment_status: {$empCount} rows upserted.");

        // ── 4. dim_alumni ───────────────────────────────────────────────────────
        $alumniAll = $oltp->table('alumni_profiles')->get();
        $alumniCount = 0;

        foreach ($alumniAll as $a) {
            $olap->table('dim_alumni')->updateOrInsert(
                ['alumni_id' => $a->id],
                [
                    'program_id'       => $a->program_id,
                    'entry_year'       => $a->entry_year,
                    'graduation_year'  => $a->graduation_year,
                    'is_active'        => $a->is_active ?? true,
                    'created_at'       => $now,
                    'updated_at'       => $now,
                ]
            );
            $alumniCount++;
        }

        $this->command->info("dim_alumni: {$alumniCount} rows upserted.");

        // Build alumni_key lookup
        $alumniKeyMap = [];
        $dimAlumniList = $olap->table('dim_alumni')->get();
        foreach ($dimAlumniList as $da) {
            $alumniKeyMap[$da->alumni_id] = $da->alumni_key;
        }

        // ── 5. fact_response ────────────────────────────────────────────────────
        $responses = $oltp->table('responses')
            ->leftJoin('alumni_profiles', 'responses.alumni_id', '=', 'alumni_profiles.id')
            ->select('responses.*', 'alumni_profiles.graduation_year', 'alumni_profiles.program_id')
            ->get();

        $responseCount = 0;
        foreach ($responses as $r) {
            $gradYear = $r->graduation_year ?? date('Y');
            $gradMonth = $r->submitted_at
                ? Carbon::parse($r->submitted_at)->format('Ym')
                : (int) (Carbon::createFromDate($gradYear, 6, 1)->format('Ym')); // default June

            $alumniKey = $alumniKeyMap[$r->alumni_id] ?? null;
            $programKey = isset($r->program_id, $programKeyMap[$r->program_id])
                ? $programKeyMap[$r->program_id]
                : null;

            $olap->table('fact_response')->updateOrInsert(
                ['response_id' => $r->id],
                [
                    'time_key'                  => $gradMonth,
                    'program_key'               => $programKey,
                    'alumni_key'                => $alumniKey,
                    'is_submitted'              => in_array($r->status, ['submitted', 'verified']) ? 1 : 0,
                    'is_verified'               => $r->status === 'verified' ? 1 : 0,
                    'response_duration_minutes' => $r->submitted_at && $r->started_at
                        ? (int) Carbon::parse($r->started_at)->diffInMinutes(Carbon::parse($r->submitted_at))
                        : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
            $responseCount++;
        }

        $this->command->info("fact_response: {$responseCount} rows upserted.");

        // ── 6. fact_employment_outcome ──────────────────────────────────────────
        $empRecords = $oltp->table('employment_records')
            ->leftJoin('alumni_profiles', 'employment_records.alumni_id', '=', 'alumni_profiles.id')
            ->select('employment_records.*', 'alumni_profiles.graduation_year', 'alumni_profiles.program_id')
            ->get();

        $empOutcomeCount = 0;
        foreach ($empRecords as $e) {
            $gradYear = $e->graduation_year ?? date('Y');
            $gradMonth = (int) Carbon::createFromDate($gradYear, 6, 1)->format('Ym');
            $startMonth = $e->first_job_started_at
                ? (int) Carbon::parse($e->first_job_started_at)->format('Ym')
                : null;

            // Resolve time_key: use first_job_started_at month, fallback to graduation month
            $timeKey = $startMonth ?? $gradMonth;

            $alumniKey = $alumniKeyMap[$e->alumni_id] ?? null;
            $programKey = isset($e->program_id, $programKeyMap[$e->program_id])
                ? $programKeyMap[$e->program_id]
                : null;
            $empStatusKey = $empStatusKeyMap[$e->employment_status] ?? null;
            $isEntrepreneur = $e->employment_status === 'entrepreneur' ? 1 : 0;

            $olap->table('fact_employment_outcome')->updateOrInsert(
                ['employment_record_id' => $e->id],
                [
                    'time_key'                => $timeKey,
                    'program_key'             => $programKey,
                    'alumni_key'              => $alumniKey,
                    'employment_status_key'   => $empStatusKey,
                    'waiting_months'          => $e->waiting_months,
                    'salary_first'            => $e->salary_first,
                    'salary_current'          => $e->salary_current,
                    'is_job_relevant'        => $e->is_job_relevant,
                    'is_entrepreneur'        => $isEntrepreneur,
                    'created_at'              => $now,
                    'updated_at'              => $now,
                ]
            );
            $empOutcomeCount++;
        }

        $this->command->info("fact_employment_outcome: {$empOutcomeCount} rows upserted.");
    }
}