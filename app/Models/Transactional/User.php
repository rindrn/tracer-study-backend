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
    protected $fillable   = ['name', 'email', 'password', 'role', 'program_id', 'jurusan', 'jurusan_id', 'fakultas_id', 'status'];
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
    public const ROLE_KAJUR           = 'kajur';            // Ketua Jurusan
    public const ROLE_KAPRODI         = 'kaprodi';          // Ketua Program Studi
    public const ROLE_DEKAN  = 'dekan';   // Dekan (multi-jurusan)

    public const ROLES_ALL = [
        self::ROLE_HEAD_TRACER,
        self::ROLE_TRACER_TEAM,
        self::ROLE_WADIR,
        self::ROLE_KAJUR,
        self::ROLE_KAPRODI,
        self::ROLE_DEKAN,
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

    /** Jurusan yang dipimpin user `kajur` (satu-satu, dipilih dari Master Data). */
    public function jurusanEntity(): BelongsTo
    {
        return $this->belongsTo(Jurusan::class, 'jurusan_id');
    }

    /** Fakultas yang dipimpin user `dekan` (satu-satu). */
    public function fakultas(): BelongsTo
    {
        return $this->belongsTo(Fakultas::class, 'fakultas_id');
    }

    /**
     * Resolve daftar program_id dalam cakupan user, sesuai role:
     *   - kaprodi         → [program_id] miliknya sendiri.
     *   - kajur           → seluruh program dalam jurusan yang dipimpinnya.
     *   - dekan  → gabungan program dari SELURUH jurusan anggota
     *                       fakultas yang dipimpinnya.
     *   - role lain       → array kosong (tidak relevan, canAccessAll()
     *                       yang dipakai untuk role tanpa batasan prodi).
     *
     * Satu method inilah yang dipakai EnforcesProdiScope, AdminAlumniService,
     * dan RingkasanTahunController supaya definisi "cakupan" tidak
     * bercabang tiga kali di tiga tempat.
     *
     * @return array<int>
     */
    public function scopedProgramIds(): array
    {
        return match ($this->role) {
            self::ROLE_KAPRODI => $this->program_id !== null ? [$this->program_id] : [],
            self::ROLE_KAJUR => $this->jurusanEntity?->programs->pluck('id')->all() ?? [],
            self::ROLE_DEKAN => $this->fakultas
                ?->jurusans->flatMap(fn ($j) => $j->programs)->pluck('id')->unique()->values()->all() ?? [],
            default => [],
        };
    }

    // ── Role helpers ─────────────────────────────────────────────────────────
    public function isHeadTracer(): bool      { return $this->role === self::ROLE_HEAD_TRACER; }
    public function isTracerTeam(): bool      { return $this->role === self::ROLE_TRACER_TEAM; }
    public function isWadir(): bool           { return $this->role === self::ROLE_WADIR; }
    public function isKajur(): bool           { return $this->role === self::ROLE_KAJUR; }
    public function isKaprodi(): bool         { return $this->role === self::ROLE_KAPRODI; }
    public function isDekan(): bool   { return $this->role === self::ROLE_DEKAN; }

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

    public function isActive(): bool
    {
        return (bool) $this->status;
    }
}
