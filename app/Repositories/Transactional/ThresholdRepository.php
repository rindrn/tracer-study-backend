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

        // year_end dihitung dulu di level lam_versions mentah (LEAD partition per lam_id)
        // sebelum di-join ke thresholds — kalau dihitung setelah join, hasilnya salah
        // karena tiap versi muncul 2x (baik/unggul) dan partition-nya jadi rusak.
        $lamVersionsWithYearEnd = DB::connection('oltp')->table('lam_versions')
            ->selectRaw('lam_versions.*, (LEAD(year) OVER (PARTITION BY lam_id ORDER BY year) - 1) as year_end');

        $rows = DB::connection('oltp')
            ->query()
            ->fromSub($lamVersionsWithYearEnd, 'lv')
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
                'lv.year_end',
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

    public function getTracerResponseThreshold(int $programId, int $graduatedYear): ?object
    {
        return DB::connection('oltp')
            ->table('tracer_response_thresholds')
            ->where('program_id', $programId)
            ->where('graduated_year', $graduatedYear)
            ->first();
    }

    public function latestTracerResponseThreshold(int $programId): ?object
    {
        return DB::connection('oltp')
            ->table('tracer_response_thresholds')
            ->where('program_id', $programId)
            ->orderByDesc('graduated_year')
            ->first();
    }

    public function historyByProgram(int $programId): \Illuminate\Support\Collection
    {
        return DB::connection('oltp')
            ->table('tracer_response_thresholds')
            ->where('program_id', $programId)
            ->orderBy('graduated_year')
            ->get();
    }

    /** Total lulusan per tahun, digabung dari semua prodi — dasar hitung threshold agregat "Semua Prodi". */
    public function totalLulusanPerYearAllPrograms(): \Illuminate\Support\Collection
    {
        return DB::connection('oltp')
            ->table('tracer_response_thresholds')
            ->selectRaw('graduated_year, SUM(total_lulusan) as total_lulusan')
            ->groupBy('graduated_year')
            ->orderBy('graduated_year')
            ->get();
    }

    public function tracerResponseHistoryByLam(int $lamId): \Illuminate\Support\Collection
    {
        // Join lam_programs → dapat semua prodi di bawah LAM ini, lalu histori tiap prodi
        return DB::connection('oltp')
            ->table('lam_programs as lp')
            ->join('programs as p', 'p.id', '=', 'lp.program_id')
            ->join('tracer_response_thresholds as t', 't.program_id', '=', 'lp.program_id')
            ->where('lp.lam_id', $lamId)
            ->select(
                'p.id as program_id', 'p.name as program_name', 'p.code as program_code',
                't.graduated_year', 't.threshold_value', 't.total_lulusan',
                't.min_responden', 't.margin_error', 't.calculated_at',
            )
            ->orderBy('p.name')
            ->orderByDesc('t.graduated_year')
            ->get();
    }
}