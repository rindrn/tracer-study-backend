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

        // Siapkan semua rows (baik + unggul per indicator)
        $rows = [];
        foreach ($thresholds as $item) {
            $rows[] = [
                'lam_version_id' => $lamVersionId,
                'indicator_id'   => $item['indicator_id'],
                'level'          => 'baik',
                'value'          => $item['baik'],
                'created_by'     => $createdBy,
                'created_at'     => $now,
                'updated_at'     => $now,
            ];
            $rows[] = [
                'lam_version_id' => $lamVersionId,
                'indicator_id'   => $item['indicator_id'],
                'level'          => 'unggul',
                'value'          => $item['unggul'],
                'created_by'     => $createdBy,
                'created_at'     => $now,
                'updated_at'     => $now,
            ];
        }

        // Insert semua sekaligus dalam 1 query
        DB::connection('oltp')->table('thresholds')->insert($rows);

        // Return hasil via view
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
}