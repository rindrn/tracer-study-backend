<?php
// app/Models/Transactional/Views/VwThresholdsComplete.php
namespace App\Models\Transactional\Views;

use Illuminate\Database\Eloquent\Model;

class VwThresholdsComplete extends Model
{
    protected $connection = "oltp";
    protected $table      = "vw_thresholds_complete";
    protected $primaryKey = "threshold_id";
    public    $timestamps = false;
    public    $incrementing = false;

    protected $casts = [
        "threshold_value"      => "decimal:2",
        "lam_version_is_active" => "boolean",
    ];
}
