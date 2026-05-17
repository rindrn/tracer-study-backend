<?php
// app/Repositories/Transactional/LamProgramRepository.php
namespace App\Repositories\Transactional;

use Illuminate\Support\Facades\DB;

class LamProgramRepository
{
    public function byLam(int $lamId): \Illuminate\Support\Collection
    {
        return DB::connection('oltp')
            ->table('lam_programs as lp')
            ->join('programs as p', 'p.id', '=', 'lp.program_id')
            ->select('p.id', 'p.name', 'p.code', 'p.degree')
            ->where('lp.lam_id', $lamId)
            ->get();
    }

    // Tambah banyak programs ke LAM, skip duplikat
    public function syncAdd(int $lamId, array $programIds): void
    {
        $rows = array_map(
            fn($pid) => ['lam_id' => $lamId, 'program_id' => $pid, 'created_at' => now()],
            $programIds
        );

        DB::connection('oltp')
            ->table('lam_programs')
            ->insertOrIgnore($rows);
    }

    // Hapus satu program dari LAM
    public function detach(int $lamId, int $programId): void
    {
        DB::connection('oltp')
            ->table('lam_programs')
            ->where('lam_id', $lamId)
            ->where('program_id', $programId)
            ->delete();
    }
}