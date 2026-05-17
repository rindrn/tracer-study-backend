<?php

namespace App\Models\Transactional;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ThresholdIndicator extends Model
{
    protected $connection = 'oltp';
    protected $table      = 'threshold_indicators';
    protected $primaryKey = 'id';
    public    $timestamps = false;           // tabel master, tidak ada timestamps
    public    $incrementing = false;         // ID di-set manual
    protected $keyType    = 'int';
    protected $fillable   = ['id', 'key', 'name', 'unit', 'operator', 'description'];

    public function thresholds(): HasMany
    {
        return $this->hasMany(Threshold::class, 'indicator_id');
    }
}