<?php
namespace App\Models\Transactional;
 
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
 
class Program extends Model
{
    protected $connection = 'oltp';
    protected $table      = 'programs';
    protected $fillable   = ['name', 'code', 'degree', 'is_active'];
    protected $casts      = ['is_active' => 'boolean'];
 
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
