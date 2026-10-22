<?php
namespace App\Models\Transactional;

use Illuminate\Database\Eloquent\Model;

/**
 * Satu baris = satu berkas impor PDDikti. Validasi (ValidatePddiktiImportJob)
 * dan impor (RunPddiktiImportJob) berjalan di queue; FE mempoll baris ini
 * untuk progress bar — lihat 2026_10_23_000001_create_pddikti_imports_table.
 */
class PddiktiImport extends Model
{
    protected $connection = 'oltp';
    protected $table      = 'pddikti_imports';

    public $timestamps = true;

    protected $fillable = [
        'status', 'original_name', 'file_path', 'uploaded_by',
        'total_rows', 'processed_rows', 'valid_rows', 'error_rows', 'skipped_rows',
        'warning_count', 'imported_rows', 'cohorts', 'issues', 'error_message',
        'validated_at', 'started_at', 'finished_at',
    ];

    protected $casts = [
        'cohorts'      => 'array',
        'issues'       => 'array',
        'validated_at' => 'datetime',
        'started_at'   => 'datetime',
        'finished_at'  => 'datetime',
    ];
}
