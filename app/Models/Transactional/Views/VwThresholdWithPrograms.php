// app/Models/Transactional/Views/VwThresholdsWithPrograms.php
namespace App\Models\Transactional\Views;
 
use Illuminate\Database\Eloquent\Model;
 
class VwThresholdsWithPrograms extends Model
{
    protected $connection = "oltp";
    protected $table      = "vw_thresholds_with_programs";
    protected $primaryKey = "threshold_id";
    public    $timestamps = false;      // View tidak punya created_at sendiri
    public    $incrementing = false;
 
    // programs di view sudah berupa JSON string dari JSON_AGG
    // Cast ke array supaya bisa langsung $model->programs
    protected $casts = [
        "programs"         => "array",
        "threshold_value"  => "decimal:2",
    ];
}
