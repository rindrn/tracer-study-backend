<?php
namespace App\Models\Transactional;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Fakultas — entity master data baru, membawahi beberapa Jurusan lewat
 * `fakultas_jurusan_scopes`. Berhenti di level Jurusan (bukan sampai ke
 * Program) sesuai keputusan: program-program di dalamnya ikut otomatis
 * lewat `Jurusan::programs()`.
 *
 * Dipakai jalur otorisasi Ketua Fakultas: satu user ketua_fakultas
 * ditautkan ke SATU fakultas lewat `users.fakultas_id`, dan scope-nya
 * adalah gabungan seluruh program di seluruh jurusan anggota fakultas itu.
 */
class Fakultas extends Model
{
    protected $connection = 'oltp';
    protected $table      = 'fakultas';
    protected $fillable   = ['name', 'is_active'];
    protected $casts      = ['is_active' => 'boolean'];

    public function jurusans(): BelongsToMany
    {
        return $this->belongsToMany(
            Jurusan::class,
            'fakultas_jurusan_scopes',
            'fakultas_id',
            'jurusan_id',
        )->withPivot('created_at');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'fakultas_id');
    }
}
