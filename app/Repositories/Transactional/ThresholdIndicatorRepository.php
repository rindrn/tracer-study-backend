<?php
// app/Repositories/Transactional/ThresholdIndicatorRepository.php
namespace App\Repositories\Transactional;

use Illuminate\Support\Facades\DB;

class ThresholdIndicatorRepository
{
    public function all(): \Illuminate\Support\Collection
    {
        return DB::connection('oltp')
            ->table('threshold_indicators')
            ->orderBy('id')
            ->get();
    }

    public function findById(int $id): ?object
    {
        return DB::connection('oltp')
            ->table('threshold_indicators')
            ->where('id', $id)
            ->first();
    }
}