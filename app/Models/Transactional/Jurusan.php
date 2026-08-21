<?php
namespace App\Models\Transactional;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Jurusan — entity master data (tabel `jurusans`, sudah ada sejak RBAC-02
 * sebagai daftar induk teks). Model ini menambah relasi `programs()` lewat
 * `jurusan_program_scopes`: keanggotaan EKSPLISIT, bukan cocokkan teks
 * `programs.jurusan` seperti yang dipakai JurusanRepository (query mentah,
 * lihat migration create_jurusans_table untuk alasan kolom teks itu
 * dipertahankan).
 *
 * Dipakai jalur otorisasi Kajur: satu user kajur ditautkan ke SATU jurusan
 * lewat `users.jurusan_id`, dan scope-nya adalah seluruh program dalam
 * jurusan itu.
 */
class Jurusan extends Model
{
    protected $connection = 'oltp';
    protected $table      = 'jurusans';
    protected $fillable   = ['name', 'is_active'];
    protected $casts      = ['is_active' => 'boolean'];

    public function programs(): BelongsToMany
    {
        return $this->belongsToMany(
            Program::class,
            'jurusan_program_scopes',
            'jurusan_id',
            'program_id',
        )->withPivot('created_at');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'jurusan_id');
    }
}
