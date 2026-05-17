<?php
namespace App\Models\Transactional;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Threshold extends Model
{
    protected $connection = 'oltp';
    protected $table      = 'thresholds';
    protected $fillable   = ['lam_version_id', 'name', 'value', 'unit', 'operator', 'created_by'];
    protected $casts      = ['value' => 'decimal:2'];

    public function lamVersion(): BelongsTo
    {
        return $this->belongsTo(LamVersion::class, 'lam_version_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}