<?php

namespace App\Services\ETL;

use App\Repositories\Config\EtlAnomalyLogRepository;

/**
 * Thin wrapper di atas EtlAnomalyLogRepository -- satu titik masuk untuk
 * mencatat kegagalan validasi runtime ETL (tipe data tidak cocok / nilai
 * di luar rentang expected_kind/value_min/value_max sebuah semantic_role),
 * TANPA menghentikan run. Postur soft-fail: kolom fact yang terkait
 * dibiarkan NULL, baris di sini yang jadi jejak audit untuk admin
 * (lihat GET /api/etl-anomaly-log).
 */
class AnomalyLoggerService
{
    public function __construct(
        private readonly EtlAnomalyLogRepository $repo,
    ) {}

    /**
     * @param array{
     *   etl_run_id: string, alumni_nim?: ?string, questionnaire_id?: ?int,
     *   question_code?: ?string, semantic_role?: ?string, raw_answer?: ?string,
     *   expected_kind?: ?string, reason: string, detail?: ?string
     * } $data
     */
    public function log(array $data): void
    {
        $this->repo->insert($data);
    }
}
