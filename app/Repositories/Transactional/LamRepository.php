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

        // --- include=thresholds (ambil versi aktif per LAM) ---
        $thresholdsMap = [];
        if (in_array('thresholds', $include)) {
            // Ambil lam_version aktif terbaru per lam_id
            $activeVersions = DB::connection('oltp')
                ->table('lam_versions')
                ->whereIn('lam_id', $lamIds)
                ->where('is_active', true)
                ->orderByDesc('year')
                ->get()
                ->unique('lam_id');   // 1 versi aktif per LAM (terbaru)

            $versionIds = $activeVersions->pluck('id')->toArray();

            // Map version_id → lam_id
            $versionLamMap = $activeVersions->pluck('lam_id', 'id')->toArray();

            $thresholds = DB::connection('oltp')
                ->table('vw_thresholds_complete')
                ->whereIn('lam_version_id', $versionIds)
                ->orderBy('indicator_id')
                ->orderBy('threshold_level')
                ->get();

            // Group per lam_id, lalu per indicator_id
            foreach ($thresholds as $t) {
                $lamId = $versionLamMap[$t->lam_version_id] ?? null;
                if (! $lamId) continue;
                $thresholdsMap[$lamId][$t->indicator_id][$t->threshold_level] = $t;
            }
        }

        // Attach ke setiap LAM
        return $lams->map(function ($lam) use ($include, $versionsMap, $programsMap, $thresholdsMap) {
            if (in_array('versions', $include)) {
                $lam->versions = $versionsMap[$lam->id] ?? [];
            }
            if (in_array('programs', $include)) {
                $lam->programs = $programsMap[$lam->id] ?? [];
            }
            if (in_array('thresholds', $include)) {
                $raw = $thresholdsMap[$lam->id] ?? [];

                // Format: grouped per indicator
                $lam->thresholds = collect($raw)->map(function ($levels, $indicatorId) {
                    $first = collect($levels)->first();
                    return [
                        'indicator_id'   => (int) $indicatorId,
                        'indicator_key'  => $first->indicator_key,
                        'indicator_name' => $first->indicator_name,
                        'unit'           => $first->indicator_unit,
                        'operator'       => $first->indicator_operator,
                        'baik'   => isset($levels['baik'])   ? ['threshold_id' => $levels['baik']->threshold_id,   'value' => (float) $levels['baik']->threshold_value]   : null,
                        'unggul' => isset($levels['unggul']) ? ['threshold_id' => $levels['unggul']->threshold_id, 'value' => (float) $levels['unggul']->threshold_value] : null,
                    ];
                })->values()->toArray();
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