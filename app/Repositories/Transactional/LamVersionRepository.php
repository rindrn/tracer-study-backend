<?php
// app/Repositories/Transactional/LamVersionRepository.php
namespace App\Repositories\Transactional;

use Illuminate\Support\Facades\DB;

class LamVersionRepository
{
    public function findById(int $id): ?object
    {
        return DB::connection('oltp')
            ->table('lam_versions as lv')
            ->join('lams as l', 'l.id', '=', 'lv.lam_id')
            ->select('lv.*', 'l.name as lam_name', 'l.code as lam_code')
            ->where('lv.id', $id)
            ->first();
    }

    public function byLam(int $lamId): \Illuminate\Support\Collection
    {
        return DB::connection('oltp')
            ->table('lam_versions')
            ->where('lam_id', $lamId)
            ->orderBy('year')
            ->get();
    }

    public function create(array $data): object
    {
        $id = DB::connection('oltp')->table('lam_versions')->insertGetId([
            'lam_id'       => $data['lam_id'],
            'year'         => $data['year'],
            'version_name' => $data['version_name'] ?? null,
            'is_active'    => $data['is_active'] ?? true,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
        return $this->findById($id);
    }

    public function update(int $id, array $data): object
    {
        DB::connection('oltp')->table('lam_versions')->where('id', $id)->update([
            'version_name' => $data['version_name'] ?? null,
            'is_active'    => $data['is_active'] ?? true,
            'updated_at'   => now(),
        ]);
        return $this->findById($id);
    }

    public function delete(int $id): void
    {
        DB::connection('oltp')->table('lam_versions')->where('id', $id)->delete();
    }
}