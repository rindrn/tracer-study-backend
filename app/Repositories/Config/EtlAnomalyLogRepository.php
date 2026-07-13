<?php

namespace App\Repositories\Config;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Sumber public.etl_anomaly_log (koneksi olap). Write path dipanggil dari
 * AnomalyLoggerService (ETL runtime); read path dipanggil dari
 * EtlAnomalyLogController (halaman monitoring admin).
 */
class EtlAnomalyLogRepository
{
    private function olap(): \Illuminate\Database\Connection
    {
        return DB::connection('olap');
    }

    public function insert(array $data): void
    {
        $this->olap()->table('etl_anomaly_log')->insert([
            'etl_run_id'       => $data['etl_run_id'],
            'alumni_nim'       => $data['alumni_nim'] ?? null,
            'questionnaire_id' => $data['questionnaire_id'] ?? null,
            'question_code'    => $data['question_code'] ?? null,
            'semantic_role'    => $data['semantic_role'] ?? null,
            'raw_answer'       => $data['raw_answer'] ?? null,
            'expected_kind'    => $data['expected_kind'] ?? null,
            'reason'           => $data['reason'],
            'detail'           => $data['detail'] ?? null,
            'occurred_at'      => now(),
        ]);
    }

    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $q = $this->olap()->table('etl_anomaly_log')->orderByDesc('occurred_at');

        if (!empty($filters['etl_run_id'])) {
            $q->where('etl_run_id', $filters['etl_run_id']);
        }
        if (!empty($filters['semantic_role'])) {
            $q->where('semantic_role', $filters['semantic_role']);
        }
        if (!empty($filters['nim'])) {
            $q->where('alumni_nim', 'like', '%' . $filters['nim'] . '%');
        }

        return $q->paginate($perPage);
    }
}
