<?php
namespace App\Repositories\Transactional;

use App\Models\Transactional\Threshold;
use Illuminate\Support\Facades\DB;

class ThresholdRepository
{
    public function paginate(int $perPage, int $page): array
    {
        $base  = DB::connection('oltp')->table('vw_thresholds_complete');
        $total = (clone $base)->count();
        $rows  = (clone $base)
            ->orderBy('indicator_id')
            ->orderBy('threshold_level')
            ->limit($perPage)
            ->offset(($page - 1) * $perPage)
            ->get();

        return [
            'rows'      => $rows,
            'total'     => $total,
            'per_page'  => $perPage,
            'page'      => $page,
            'last_page' => (int) ceil($total / $perPage),
        ];
    }

    public function findById(int $id): ?object
    {
        return DB::connection('oltp')
            ->table('vw_thresholds_complete')
            ->where('threshold_id', $id)
            ->first();
    }

    // Ambil per version, lalu group di service layer
    public function byVersion(int $lamVersionId): \Illuminate\Support\Collection
    {
        return DB::connection('oltp')
            ->table('vw_thresholds_complete')
            ->where('lam_version_id', $lamVersionId)
            ->orderBy('indicator_id')
            ->orderBy('threshold_level')
            ->get();
    }

    public function create(array $data): object
    {
        $threshold = Threshold::create([
            'lam_version_id' => $data['lam_version_id'],
            'indicator_id'   => $data['indicator_id'],
            'level'          => $data['level'],
            'value'          => $data['value'],
            'created_by'     => auth()->id(),
        ]);
        return $this->findById($threshold->id);
    }

    public function update(int $id, array $data): object
    {
        Threshold::findOrFail($id)->update(['value' => $data['value']]);
        return $this->findById($id);
    }

    public function delete(int $id): void
    {
        Threshold::findOrFail($id)->delete();
    }

    public function bulkCreate(int $lamVersionId, array $thresholds): \Illuminate\Support\Collection
    {
        $createdBy = auth()->id();
        $now       = now();

        // Ambil nama indikator dari DB sekaligus untuk semua indicator_id
        $indicatorIds = array_unique(array_column($thresholds, 'indicator_id'));
        $indicators = DB::connection('oltp')
            ->table('threshold_indicators')
            ->whereIn('id', $indicatorIds)
            ->pluck('name', 'id'); // ['id' => 'name']

        $rows = [];
        foreach ($thresholds as $item) {
            $name = $indicators[$item['indicator_id']] ?? null;
            $rows[] = [
                'lam_version_id' => $lamVersionId,
                'indicator_id'   => $item['indicator_id'],
                'level'          => 'baik',
                'value'          => $item['baik'],
                'name'           => $name,
                'created_by'     => $createdBy,
                'created_at'     => $now,
                'updated_at'     => $now,
            ];
            $rows[] = [
                'lam_version_id' => $lamVersionId,
                'indicator_id'   => $item['indicator_id'],
                'level'          => 'unggul',
                'value'          => $item['unggul'],
                'name'           => $name,
                'created_by'     => $createdBy,
                'created_at'     => $now,
                'updated_at'     => $now,
            ];
        }

        DB::connection('oltp')->table('thresholds')->insert($rows);

        return $this->byVersion($lamVersionId);
    }

    public function bulkUpdate(array $thresholds): void
    {
        // Update satu per satu tapi dalam 1 DB transaction
        DB::connection('oltp')->transaction(function () use ($thresholds) {
            foreach ($thresholds as $item) {
                DB::connection('oltp')
                    ->table('thresholds')
                    ->where('id', $item['baik_id'])
                    ->update(['value' => $item['baik_value'], 'updated_at' => now()]);

                DB::connection('oltp')
                    ->table('thresholds')
                    ->where('id', $item['unggul_id'])
                    ->update(['value' => $item['unggul_value'], 'updated_at' => now()]);
            }
        });
    }

    public function byProdiAndIndicator(int $prodiId, string $indicatorKey): ?object
    {
        $lamRow = DB::connection('oltp')
            ->table('lam_programs as lp')
            ->join('lams as l', 'l.id', '=', 'lp.lam_id')
            ->where('lp.program_id', $prodiId)
            ->select('l.id as lam_id', 'l.name as lam_name', 'l.code as lam_code')
            ->first();

        if (! $lamRow) return null;

        $rows = DB::connection('oltp')
            ->table('lam_versions as lv')
            ->join('thresholds as t', 't.lam_version_id', '=', 'lv.id')
            ->join('threshold_indicators as ti', 'ti.id', '=', 't.indicator_id')
            ->leftJoin('threshold_configs as tc', function ($join) {
                $join->on('tc.lam_version_id', '=', 't.lam_version_id')
                    ->on('tc.indicator_id', '=', 't.indicator_id');
            })
            ->where('lv.lam_id', $lamRow->lam_id)
            ->where('ti.key', $indicatorKey)
            ->select(
                'lv.id as version_id',
                'lv.year',
                'lv.version_name',
                'lv.is_active',
                'ti.key as indicator_key',
                'ti.name as indicator_name',
                'ti.unit as indicator_unit',
                'ti.operator as indicator_operator',
                'ti.dynamic_param_unit',
                'ti.is_system_calculated',
                'tc.param_value',
                't.id as threshold_id',
                't.level as threshold_level',
                't.value as threshold_value',
            )
            ->orderBy('lv.year')
            ->orderBy('t.level')
            ->get();

        return (object) ['lam' => $lamRow, 'rows' => $rows];
    }

    public function upsertConfig(int $lamVersionId, int $indicatorId, float $paramValue): void
    {
        DB::connection('oltp')->table('threshold_configs')->updateOrInsert(
            ['lam_version_id' => $lamVersionId, 'indicator_id' => $indicatorId],
            ['param_value' => $paramValue, 'updated_at' => now(), 'created_at' => now()]
        );
    }

    public function getConfig(int $lamVersionId, int $indicatorId): ?float
    {
        $row = DB::connection('oltp')->table('threshold_configs')
            ->where('lam_version_id', $lamVersionId)
            ->where('indicator_id', $indicatorId)
            ->first();

        return $row ? (float) $row->param_value : null;
    }
}