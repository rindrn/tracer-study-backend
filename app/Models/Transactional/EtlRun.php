<?php
namespace App\Models\Transactional;

use Illuminate\Database\Eloquent\Model;

/**
 * Satu baris = satu eksekusi ETL yang di-trigger lewat queue job (RunEtlJob),
 * dipoll FE (GET /api/etl-runs/{id}) supaya ada UI loading yang jelas setelah
 * Langkah 1 (question_semantic_mapping) berubah -- lihat 005_etl_runs_and_queue.sql.
 */
class EtlRun extends Model
{
    protected $connection = 'oltp';
    protected $table      = 'etl_runs';

    public $timestamps = true;

    protected $fillable = [
        'status', 'reason', 'triggered_by', 'id_waktu',
        'summary', 'error_message', 'started_at', 'finished_at',
    ];

    protected $casts = [
        'summary'     => 'array',
        'started_at'  => 'datetime',
        'finished_at' => 'datetime',
    ];
}
