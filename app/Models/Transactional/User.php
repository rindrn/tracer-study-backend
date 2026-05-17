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

    // ── Role constants (sinkron dengan CHECK constraint di migration users) ─
    public const ROLE_ADMIN        = 'admin';
    public const ROLE_P2MPP        = 'p2mpp';
    public const ROLE_KAPRODI      = 'kaprodi';
    public const ROLE_HEAD_TRACER  = 'head_tracer';
    public const ROLE_TRACER_TEAM  = 'tracer_team';
    public const ROLE_WADIR        = 'wadir';

    public const ROLES_ALL = [
        self::ROLE_ADMIN,
        self::ROLE_P2MPP,
        self::ROLE_KAPRODI,
        self::ROLE_HEAD_TRACER,
        self::ROLE_TRACER_TEAM,
        self::ROLE_WADIR,
    ];

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class, 'program_id');
    }

    // ── Helpers ────────────────────────────────────────────────────────────
    public function isAdmin(): bool       { return $this->role === self::ROLE_ADMIN; }
    public function isP2mpp(): bool       { return $this->role === self::ROLE_P2MPP; }
    public function isKaprodi(): bool     { return $this->role === self::ROLE_KAPRODI; }
    public function isHeadTracer(): bool  { return $this->role === self::ROLE_HEAD_TRACER; }
    public function isTracerTeam(): bool  { return $this->role === self::ROLE_TRACER_TEAM; }
    public function isWadir(): bool       { return $this->role === self::ROLE_WADIR; }

    /**
     * Role yang boleh lihat data semua prodi (tidak tersegmentasi ke program_id).
     * Admin super, P2MPP monitoring, Head Tracer koordinator lintas prodi,
     * Wadir monitoring institusi.
     */
    public function canAccessAll(): bool
    {
        return in_array($this->role, [
            self::ROLE_ADMIN,
            self::ROLE_P2MPP,
            self::ROLE_HEAD_TRACER,
            self::ROLE_WADIR,
        ], strict: true);
    }
}
