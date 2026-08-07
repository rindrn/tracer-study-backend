<?php
// app/Models/Transactional/AlumniProfile.php
namespace App\Models\Transactional;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * AlumniProfile — pengisi kuesioner.
 *
 * Model ini ada khusus supaya alumni bisa memegang token Sanctum sendiri;
 * seluruh pembacaan data alumni lain tetap lewat AlumniProfileRepository
 * (query builder). Jangan menambah logic bisnis di sini.
 *
 * Alumni BUKAN User. Keduanya sengaja dipisah ke provider berbeda di
 * config/auth.php supaya token alumni tidak pernah bisa dipakai di endpoint
 * staff, dan sebaliknya — pemisahan itu ditegakkan Sanctum lewat
 * Guard::hasValidProvider().
 */
class AlumniProfile extends Authenticatable
{
    use HasApiTokens;

    protected $connection = 'oltp';
    protected $table      = 'alumni_profiles';
    protected $guarded    = ['id'];
    protected $hidden     = ['password'];

    /**
     * Cast `hashed` WAJIB ada. Tanpanya, penetapan kata sandi lewat model ini
     * tersimpan sebagai teks polos — persis yang dilarang NFR-01. Model User
     * sudah memakai pola yang sama.
     */
    protected $casts = [
        'is_active'          => 'boolean',
        'password'           => 'hashed',
        'password_issued_at' => 'datetime',
    ];

    /**
     * alumni_profiles tidak punya kolom remember_token — sama seperti User
     * (lihat migration 2026_05_25_000001). Override supaya trait bawaan tidak
     * menulis ke kolom yang tidak ada.
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
}
