<?php
// app/Models/Transactional/RefUmp.php

namespace App\Models\Transactional;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RefUmp extends Model
{
    protected $connection = 'oltp';
    protected $table      = 'ref_ump';

    protected $fillable = [
        'tahun',
        'province_id',
        'nama_provinsi',
        'nilai_ump',
        'sumber',
    ];

    protected $casts = [
        'tahun'      => 'integer',
        'nilai_ump'  => 'integer',
        'province_id'=> 'integer',
    ];

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class, 'province_id');
    }
}