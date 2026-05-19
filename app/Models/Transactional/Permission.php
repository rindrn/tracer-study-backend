<?php
namespace App\Models\Transactional;

use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    protected $connection = 'oltp';
    protected $table      = 'permissions';
    protected $fillable   = ['name', 'description'];

    /**
     * Get permissions for a given role.
     */
    public static function forRole(string $role): \Illuminate\Support\Collection
    {
        return static::whereHas('roles', fn ($q) => $q->where('role', $role))->pluck('name');
    }

    public function roles()
    {
        return $this->hasMany(RolePermission::class, 'permission_id');
    }
}
