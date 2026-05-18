<?php
namespace App\Models\Transactional;

use Illuminate\Database\Eloquent\Model;

class RolePermission extends Model
{
    protected $connection = 'oltp';
    protected $table      = 'role_permissions';
    protected $fillable   = ['role', 'permission_id'];
    public $timestamps    = false;

    public function permission()
    {
        return $this->belongsTo(Permission::class);
    }
}
