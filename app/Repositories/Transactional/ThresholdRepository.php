<?php
// app/Repositories/Transactional/ThresholdRepository.php
namespace App\Repositories\Transactional;
 
use App\Models\Transactional\Threshold;
use Illuminate\Support\Facades\DB;
 
class ThresholdRepository
{
    // ── READ: dari vw_thresholds_with_programs ─────────────────
 
    public function paginate(int $perPage, int $page): array
    {
        $base   = DB::connection('oltp')->table('vw_thresholds_with_programs');
        $total  = (clone $base)->count();
        $rows   = (clone $base)
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
            ->table('vw_thresholds_with_programs')
            ->where('threshold_id', $id)
            ->first();
    }
 
    // ── WRITE: ke tabel asli ───────────────────────────────────
 
    public function create(array $data, array $programIds): object
    {
        $threshold = Threshold::create([
            'name'       => $data['name'],
            'value'      => $data['value'],
            'created_by' => auth()->id(),
        ]);
        $this->syncPrograms($threshold->id, $programIds);
        return $this->findById($threshold->id);
    }
 
    public function update(int $id, array $data, array $programIds): object
    {
        Threshold::findOrFail($id)->update([
            'name'  => $data['name'],
            'value' => $data['value'],
        ]);
        $this->syncPrograms($id, $programIds);
        return $this->findById($id);
    }
 
    public function delete(int $id): void
    {
        Threshold::findOrFail($id)->delete();
        // ON DELETE CASCADE di DB handle threshold_programs
    }
 
    // ── Private ────────────────────────────────────────────────
 
    private function syncPrograms(int $thresholdId, array $programIds): void
    {
        DB::connection('oltp')
            ->table('threshold_programs')
            ->where('threshold_id', $thresholdId)
            ->delete();
 
        if (! empty($programIds)) {
            DB::connection('oltp')
                ->table('threshold_programs')
                ->insert(array_map(
                    fn($pid) => ['threshold_id' => $thresholdId, 'program_id' => $pid],
                    $programIds
                ));
        }
    }
}
