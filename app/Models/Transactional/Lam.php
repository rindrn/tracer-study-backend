<?php
namespace App\Models\Transactional;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Lam extends Model
{
    protected $connection = 'oltp';
    protected $table      = 'lams';
    protected $fillable   = ['name', 'code'];

    public function versions(): HasMany
    {
        return $this->hasMany(LamVersion::class, 'lam_id');
    }

    public function programs(): BelongsToMany
    {
        return $this->belongsToMany(
            Program::class,
            'lam_programs',
            'lam_id',
            'program_id'
        );
    }
}