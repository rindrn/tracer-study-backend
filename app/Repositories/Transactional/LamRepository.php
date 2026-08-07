<?php
// app/Repositories/Transactional/LamRepository.php
namespace App\Repositories\Transactional;

use Illuminate\Support\Facades\DB;

class LamRepository
{
    public function all(array $include = []): \Illuminate\Support\Collection
    {
        $lams = DB::connection('oltp')->table('lams')->orderBy('name')->get();

        if (empty($include)) return $lams;

        $lamIds = $lams->pluck('id')->toArray();

        // --- include=versions ---
        $versionsMap = [];
        if (in_array('versions', $include)) {
            $versions = DB::connection('oltp')
                ->table('lam_versions')
                ->selectRaw('lam_versions.*, (LEAD(year) OVER (PARTITION BY lam_id ORDER BY year) - 1) as year_end')
                ->whereIn('lam_id', $lamIds)
                ->orderBy('year')
                ->get();

            foreach ($versions as $v) {
                $versionsMap[$v->lam_id][] = $v;
            }
        }

        // --- include=programs ---
        $programsMap = [];
        if (in_array('programs', $include)) {
            $programs = DB::connection('oltp')
                ->table('lam_programs as lp')
                ->join('programs as p', 'p.id', '=', 'lp.program_id')
                ->select('lp.lam_id', 'p.id', 'p.name', 'p.code', 'p.degree')
                ->whereIn('lp.lam_id', $lamIds)
                ->get();

            foreach ($programs as $p) {
                $programsMap[$p->lam_id][] = $p;
            }
        }

        // --- include=thresholds (ambil threshold per version dan per active LAM) ---
        $thresholdsMap = [];
        $versionThresholdsMap = [];
        if (in_array('thresholds', $include)) {
            $allVersions = DB::connection('oltp')
                ->table('lam_versions')
                ->whereIn('lam_id', $lamIds)
                ->get();

            $allVersionIds = $allVersions->pluck('id')->toArray();
            $versionLamMap = $allVersions->pluck('lam_id', 'id')->toArray();

            $activeVersionLamMap = $allVersions->where('is_active', true)
                ->sortByDesc('year')
                ->unique('lam_id')
                ->pluck('id', 'lam_id')
                ->toArray();

            $thresholds = DB::connection('oltp')
                ->table('thresholds as t')
                ->join('threshold_indicators as ti', 'ti.id', '=', 't.indicator_id')
                ->leftJoin('threshold_configs as tc', function ($join) {
                    $join->on('tc.lam_version_id', '=', 't.lam_version_id')
                         ->on('tc.indicator_id', '=', 't.indicator_id');
                })
                ->whereIn('t.lam_version_id', $allVersionIds)
                ->select(
                    't.id as threshold_id',
                    't.value as threshold_value',
                    't.level as threshold_level',
                    't.lam_version_id',
                    't.indicator_id',
                    'ti.key as indicator_key',
                    'ti.name as indicator_name',
                    'ti.unit as indicator_unit',
                    'ti.operator as indicator_operator',
                    'ti.dynamic_param_unit',
                    'ti.is_system_calculated',
                    'tc.param_value',
                )
                ->orderBy('t.indicator_id')
                ->orderBy('t.level')
                ->get();

            foreach ($thresholds as $t) {
                $vId   = $t->lam_version_id;
                $lamId = $versionLamMap[$vId] ?? null;

                $versionThresholdsMap[$vId][$t->indicator_id][$t->threshold_level] = $t;

                if ($lamId && isset($activeVersionLamMap[$lamId]) && $activeVersionLamMap[$lamId] === $vId) {
                    $thresholdsMap[$lamId][$t->indicator_id][$t->threshold_level] = $t;
                }
            }
        }

        $formatThresholds = function (array $rawIndicators) {
            return collect($rawIndicators)->map(function ($levels, $indicatorId) {
                $first      = collect($levels)->first();
                $paramValue = $first->param_value ?? null;

                $name = $first->indicator_name;
                if ($paramValue !== null && str_contains($name, '{value}')) {
                    $formatted = rtrim(rtrim(number_format((float) $paramValue, 2, '.', ''), '0'), '.');
                    $name = str_replace('{value}', $formatted, $name);
                }

                return [
                    'indicator_id'         => (int) $indicatorId,
                    'indicator_key'        => $first->indicator_key,
                    'indicator_name'       => $name,
                    'unit'                 => $first->indicator_unit,
                    'operator'             => $first->indicator_operator,
                    'dynamic_param'        => $first->dynamic_param_unit
                        ? ['value' => $paramValue !== null ? (float) $paramValue : null, 'unit' => $first->dynamic_param_unit]
                        : null,
                    'is_system_calculated' => (bool) $first->is_system_calculated,
                    'baik'   => isset($levels['baik'])   ? ['threshold_id' => $levels['baik']->threshold_id,   'value' => (float) $levels['baik']->threshold_value]   : null,
                    'unggul' => isset($levels['unggul']) ? ['threshold_id' => $levels['unggul']->threshold_id, 'value' => (float) $levels['unggul']->threshold_value] : null,
                ];
            })->values()->toArray();
        };

        // Attach ke setiap LAM
        return $lams->map(function ($lam) use ($include, $versionsMap, $programsMap, $thresholdsMap, $versionThresholdsMap, $formatThresholds) {
            if (in_array('versions', $include)) {
                $versions = $versionsMap[$lam->id] ?? [];
                if (in_array('thresholds', $include)) {
                    foreach ($versions as $v) {
                        $v->thresholds = $formatThresholds($versionThresholdsMap[$v->id] ?? []);
                    }
                }
                $lam->versions = $versions;
            }
            if (in_array('programs', $include)) {
                $lam->programs = $programsMap[$lam->id] ?? [];
            }
            if (in_array('thresholds', $include)) {
                $raw = $thresholdsMap[$lam->id] ?? [];
                $lam->thresholds = $formatThresholds($raw);
            }
            return $lam;
        });
    }

    public function findById(int $id): ?object
    {
        return DB::connection('oltp')->table('lams')->where('id', $id)->first();
    }

    // Create LAM + sekaligus sync programs dalam 1 transaksi
    public function create(array $data, array $programIds = []): object
    {
        return DB::connection('oltp')->transaction(function () use ($data, $programIds) {
            $id = DB::connection('oltp')->table('lams')->insertGetId([
                'name'       => $data['name'],
                'code'       => $data['code'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if (! empty($programIds)) {
                DB::connection('oltp')->table('lam_programs')->insertOrIgnore(
                    array_map(fn($pid) => [
                        'lam_id'     => $id,
                        'program_id' => $pid,
                        'created_at' => now(),
                    ], $programIds)
                );
            }

            return $this->findById($id);
        });
    }

    public function update(int $id, array $data): object
    {
        DB::connection('oltp')->table('lams')->where('id', $id)->update([
            'name'       => $data['name'],
            'code'       => $data['code'],
            'updated_at' => now(),
        ]);
        return $this->findById($id);
    }

    public function delete(int $id): void
    {
        DB::connection('oltp')->table('lams')->where('id', $id)->delete();
    }

    public function fullDetail(int $id, int $year): ?object
    {
        return DB::connection('oltp')
            ->table('vw_lam_versions_complete')
            ->where('lam_id', $id)
            ->where('year', $year)
            ->first();
    }
}