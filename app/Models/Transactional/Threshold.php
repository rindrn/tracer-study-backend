<?php
namespace App\Models\Transactional;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Threshold extends Model
{
    protected $connection = 'oltp';
    protected $table      = 'thresholds';
    protected $fillable   = [
        'lam_version_id',
        'indicator_id',
        'level',       // 'baik' | 'unggul'
        'value',
        'created_by',
    ];
    protected $casts = ['value' => 'decimal:2'];

    public function indicator(): BelongsTo
    {
        return $this->belongsTo(ThresholdIndicator::class, 'indicator_id');
    }

    public function lamVersion(): BelongsTo
    {
        return $this->belongsTo(LamVersion::class, 'lam_version_id');
    }
}