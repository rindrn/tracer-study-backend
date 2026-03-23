<?php
// app/Models/Transactional/Threshold.php
namespace App\Models\Transactional;
 
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
 
class Threshold extends Model
{
    protected $connection = 'oltp';
    protected $table      = 'thresholds';
    protected $fillable   = ['name', 'value', 'created_by'];
    protected $casts      = ['value' => 'decimal:2'];
 
    // Relasi many-to-many ke programs via threshold_programs
    public function programs(): BelongsToMany
    {
        return $this->belongsToMany(
            Program::class,
            'threshold_programs',
            'threshold_id',
            'program_id'
        )->withPivot("created_at");
    }
 
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
