<?php
// app/Repositories/Transactional/LamRepository.php
namespace App\Repositories\Transactional;

use App\Models\Transactional\Lam;
use Illuminate\Support\Facades\DB;

class LamRepository
{
    public function all(): \Illuminate\Support\Collection
    {
        return DB::connection('oltp')->table('lams')->orderBy('name')->get();
    }

    public function findById(int $id): ?object
    {
        return DB::connection('oltp')->table('lams')->where('id', $id)->first();
    }

    public function create(array $data): object
    {
        $id = DB::connection('oltp')->table('lams')->insertGetId([
            'name'       => $data['name'],
            'code'       => $data['code'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return $this->findById($id);
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

    // Full detail: LAM + version + programs + thresholds (dari view)
    public function fullDetail(int $id, int $year): ?object
    {
        return DB::connection('oltp')
            ->table('vw_lam_versions_complete')
            ->where('lam_id', $id)
            ->where('year', $year)
            ->first();
    }
}