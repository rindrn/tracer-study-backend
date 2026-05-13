<?php
namespace App\Repositories\Transactional;

use App\Models\Transactional\Threshold;
use Illuminate\Support\Facades\DB;

class ThresholdRepository
{
    // READ dari view
    public function paginate(int $perPage, int $page): array
    {
        $base  = DB::connection('oltp')->table('vw_thresholds_complete');
        $total = (clone $base)->count();
        $rows  = (clone $base)
            ->orderByDesc('created_at')
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

    public function byVersion(int $lamVersionId): \Illuminate\Support\Collection
    {
        return DB::connection('oltp')
            ->table('vw_thresholds_complete')
            ->where('lam_version_id', $lamVersionId)
            ->orderBy('threshold_name')
            ->get();
    }

    // WRITE ke tabel asli
    public function create(array $data): object
    {
        $threshold = Threshold::create([
            'lam_version_id' => $data['lam_version_id'],
            'name'           => $data['name'],
            'value'          => $data['value'],
            'unit'           => $data['unit'],
            'operator'       => $data['operator'],
            'created_by'     => auth()->id(),
        ]);
        return $this->findById($threshold->id);
    }

    public function update(int $id, array $data): object
    {
        Threshold::findOrFail($id)->update(array_filter([
            'name'     => $data['name']     ?? null,
            'value'    => $data['value'],
            'unit'     => $data['unit']     ?? null,
            'operator' => $data['operator'] ?? null,
        ], fn($v) => $v !== null));

        return $this->findById($id);
    }

    public function delete(int $id): void
    {
        Threshold::findOrFail($id)->delete();
    }
}