<?php

namespace App\Models\Transactional\Views;

use Illuminate\Database\Eloquent\Model;

class VwLamVersionsComplete extends Model
{
    protected $connection = "oltp";
    protected $table      = "vw_lam_versions_complete";
    protected $primaryKey = "lam_version_id";
    public    $timestamps = false;
    public    $incrementing = false;

    protected $casts = [
        "is_active" => "boolean",
        "programs"  => "array",
        "thresholds" => "array",
    ];
}
