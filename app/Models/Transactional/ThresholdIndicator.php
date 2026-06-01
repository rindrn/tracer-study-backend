<?php

namespace App\Models\Transactional;

use Illuminate\Database\Eloquent\Model;

class ThresholdIndicator extends Model
{
    protected $connection = 'oltp';
    protected $table      = 'threshold_indicators';
    protected $fillable   = ['id', 'key', 'name', 'unit', 'operator', 'description'];
    public $incrementing  = false;
    protected $keyType    = 'smallint';
}
