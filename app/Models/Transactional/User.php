<?php
// app/Models/Transactional/User.php
namespace App\Models\Transactional;
 
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
 
class User extends Authenticatable
{
    use HasApiTokens, Notifiable;
 
    protected $connection = 'oltp';
    protected $table      = 'users';
    protected $fillable   = ['name', 'email', 'password', 'role', 'program_id'];
    protected $hidden     = ['password', 'remember_token'];
    protected $casts      = ['password' => 'hashed'];
 
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class, 'program_id');
    }
 
    // Helper — dipakai di RoleAccessMiddleware dan service
    public function isAdmin(): bool   { return $this->role === "admin"; }
    public function isP2mpp(): bool   { return $this->role === "p2mpp"; }
    public function isProdi(): bool   { return $this->role === "prodi"; }
    public function canAccessAll(): bool { return in_array($this->role, ["admin","p2mpp"]); }
}
