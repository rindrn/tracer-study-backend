<?php
namespace App\Models\Transactional;
 
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
 
class Program extends Model
{
    protected $connection = 'oltp';
    protected $table      = 'programs';
    protected $fillable   = ['name', 'code', 'dikti_code', 'degree', 'jurusan', 'org_unit_id', 'is_active', 'accreditation', 'accredited_until'];
    protected $casts      = ['is_active' => 'boolean', 'accredited_until' => 'date'];
 
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'program_id');
    }
 
    public function thresholds(): BelongsToMany
    {
        return $this->belongsToMany(
            Threshold::class,
            'threshold_programs',
            'program_id',
            'threshold_id'
        );
    }
}
