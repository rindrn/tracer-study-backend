<?php
namespace App\Models\Transactional;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $connection = 'oltp';
    protected $table      = 'users';
    protected $fillable   = ['name', 'email', 'password', 'role', 'program_id', 'jurusan', 'status'];
    protected $hidden     = ['password'];
    protected $casts      = ['password' => 'hashed', 'status' => 'boolean'];

    /**
     * Auth pakai Sanctum token; kolom remember_token dihapus pada migration
     * 2026_05_25_000001. Override agar trait Authenticatable tidak menulis
     * ke kolom yang sudah tidak ada.
     */
    public function getRememberTokenName(): string
    {
        return '';
    }
    public function getRememberToken(): ?string
    {
        return null;
    }
    public function setRememberToken($value): void
    {
        // no-op
    }

    // ── Role constants ───────────────────────────────────────────────────────
    public const ROLE_HEAD_TRACER  = 'head_tracer';   // Super Admin
    public const ROLE_TRACER_TEAM  = 'tracer_team';   // Admin
    public const ROLE_WADIR        = 'wadir';         // Pimpinan (Direktur/Wadir/P2MPP)
    public const ROLE_KAJUR        = 'kajur';         // Ketua Jurusan
    public const ROLE_KAPRODI      = 'kaprodi';       // Ketua Program Studi

    public const ROLES_ALL = [
        self::ROLE_HEAD_TRACER,
        self::ROLE_TRACER_TEAM,
        self::ROLE_WADIR,
        self::ROLE_KAJUR,
        self::ROLE_KAPRODI,
    ];

    // ── Relationships ────────────────────────────────────────────────────────
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class, 'program_id');
    }

    public function approvalRequests(): HasMany
    {
        return $this->hasMany(ApprovalRequest::class, 'requester_id');
    }

    // ── Role helpers ─────────────────────────────────────────────────────────
    public function isHeadTracer(): bool  { return $this->role === self::ROLE_HEAD_TRACER; }
    public function isTracerTeam(): bool  { return $this->role === self::ROLE_TRACER_TEAM; }
    public function isWadir(): bool       { return $this->role === self::ROLE_WADIR; }
    public function isKajur(): bool       { return $this->role === self::ROLE_KAJUR; }
    public function isKaprodi(): bool     { return $this->role === self::ROLE_KAPRODI; }

    /**
     * Roles yang bisa lihat data SEMUA prodi (tidak tersegmentasi).
     */
    public function canAccessAll(): bool
    {
        return in_array($this->role, [
            self::ROLE_HEAD_TRACER,
            self::ROLE_TRACER_TEAM,
            self::ROLE_WADIR,
        ], true);
    }

    /**
     * Kajur bisa lihat semua prodi dalam jurusannya.
     */
    public function canAccessJurusan(): bool
    {
        return $this->role === self::ROLE_KAJUR && $this->jurusan !== null;
    }

    public function isActive(): bool
    {
        return (bool) $this->status;
    }
}
