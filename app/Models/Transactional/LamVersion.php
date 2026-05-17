<?php
namespace App\Models\Transactional;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LamVersion extends Model
{
    protected $connection = 'oltp';
    protected $table      = 'lam_versions';
    protected $fillable   = ['lam_id', 'year', 'version_name', 'is_active'];
    protected $casts      = ['is_active' => 'boolean'];

    public function lam(): BelongsTo
    {
        return $this->belongsTo(Lam::class, 'lam_id');
    }

    public function thresholds(): HasMany
    {
        return $this->hasMany(Threshold::class, 'lam_version_id');
    }
}