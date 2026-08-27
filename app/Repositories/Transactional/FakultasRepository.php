<?php
// app/Repositories/Transactional/FakultasRepository.php
namespace App\Repositories\Transactional;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * FakultasRepository — daftar induk Fakultas beserta keanggotaan jurusannya.
 *
 * Berbeda dari JurusanRepository (yang punya jalur ganda: teks lama +
 * pivot baru), Fakultas SELALU FK murni sejak awal -- tidak ada kolom teks
 * warisan untuk disinkronkan.
 */
class FakultasRepository
{
    private const CONN = 'oltp';

    public function allWithUsage(): Collection
    {
        return collect(
            DB::connection(self::CONN)->table('fakultas as f')
                ->leftJoin('fakultas_jurusan_scopes as fjs', 'fjs.fakultas_id', '=', 'f.id')
                ->leftJoin('users as u', 'u.fakultas_id', '=', 'f.id')
                ->groupBy('f.id', 'f.name', 'f.is_active', 'f.created_at', 'f.updated_at')
                ->orderBy('f.name')
                ->select([
                    'f.id', 'f.name', 'f.is_active', 'f.created_at', 'f.updated_at',
                    DB::raw('COUNT(DISTINCT fjs.jurusan_id) as jurusan_count'),
                    DB::raw('COUNT(DISTINCT u.id) as user_count'),
                ])
                ->get()
        );
    }

    public function find(int $id): ?object
    {
        return DB::connection(self::CONN)->table('fakultas')->where('id', $id)->first();
    }

    public function findByName(string $name): ?object
    {
        return DB::connection(self::CONN)->table('fakultas')->where('name', $name)->first();
    }

    public function insert(string $name, bool $isActive = true): int
    {
        $now = now();

        return DB::connection(self::CONN)->table('fakultas')->insertGetId([
            'name' => $name, 'is_active' => $isActive, 'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    public function update(int $id, array $attributes): void
    {
        DB::connection(self::CONN)->table('fakultas')->where('id', $id)
            ->update($attributes + ['updated_at' => now()]);
    }

    public function delete(int $id): void
    {
        DB::connection(self::CONN)->table('fakultas')->where('id', $id)->delete();
    }

    public function userCount(int $id): int
    {
        return DB::connection(self::CONN)->table('users')->where('fakultas_id', $id)->count();
    }

    /** @return array<int> jurusan_id anggota fakultas ini. */
    public function scopedJurusanIds(int $fakultasId): array
    {
        return DB::connection(self::CONN)->table('fakultas_jurusan_scopes')
            ->where('fakultas_id', $fakultasId)
            ->pluck('jurusan_id')
            ->all();
    }

    /**
     * Ganti seluruh keanggotaan jurusan fakultas ini (hapus lalu insert ulang).
     *
     * Dibungkus transaksi: tanpa ini, kegagalan di tengah insert meninggalkan
     * keanggotaan KOSONG -- setiap Dekan yang bergantung pada
     * fakultas ini kehilangan seluruh cakupannya secara diam-diam.
     */
    public function syncJurusans(int $fakultasId, array $jurusanIds): void
    {
        $conn = DB::connection(self::CONN);

        $conn->transaction(function () use ($conn, $fakultasId, $jurusanIds) {
            $conn->table('fakultas_jurusan_scopes')->where('fakultas_id', $fakultasId)->delete();

            if (empty($jurusanIds)) {
                return;
            }

            $now = now();
            $conn->table('fakultas_jurusan_scopes')->insert(
                array_map(fn (int $jurusanId) => [
                    'fakultas_id' => $fakultasId,
                    'jurusan_id'  => $jurusanId,
                    'created_at'  => $now,
                ], array_unique($jurusanIds))
            );
        });
    }
}
