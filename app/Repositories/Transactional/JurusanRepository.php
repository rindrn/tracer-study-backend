<?php
// app/Repositories/Transactional/JurusanRepository.php
namespace App\Repositories\Transactional;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * JurusanRepository — daftar induk jurusan beserta pemakaiannya.
 *
 * Jumlah program studi dan staf yang memakai sebuah jurusan dihitung dari
 * kolom teks `programs.jurusan` dan `users.jurusan`, bukan dari foreign key
 * -- lihat alasannya di migration create_jurusans_table.
 */
class JurusanRepository
{
    private const CONN = 'oltp';

    /**
     * Seluruh jurusan beserta jumlah pemakaiannya.
     *
     * Hitungan dipakai antarmuka untuk menerangkan akibat penggantian nama,
     * dan untuk menonaktifkan tombol hapus ketika masih terpakai.
     */
    public function allWithUsage(): Collection
    {
        return collect(
            DB::connection(self::CONN)->table('jurusans as j')
                ->leftJoin('programs as p', 'p.jurusan', '=', 'j.name')
                ->leftJoin('users as u', 'u.jurusan', '=', 'j.name')
                ->groupBy('j.id', 'j.name', 'j.is_active', 'j.created_at', 'j.updated_at')
                ->orderBy('j.name')
                ->select([
                    'j.id',
                    'j.name',
                    'j.is_active',
                    'j.created_at',
                    'j.updated_at',
                    DB::raw('COUNT(DISTINCT p.id) as program_count'),
                    DB::raw('COUNT(DISTINCT u.id) as user_count'),
                ])
                ->get()
        );
    }

    public function find(int $id): ?object
    {
        return DB::connection(self::CONN)->table('jurusans')->where('id', $id)->first();
    }

    public function findByName(string $name): ?object
    {
        return DB::connection(self::CONN)->table('jurusans')->where('name', $name)->first();
    }

    public function insert(string $name, bool $isActive = true): int
    {
        $now = now();

        return DB::connection(self::CONN)->table('jurusans')->insertGetId([
            'name'       => $name,
            'is_active'  => $isActive,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function update(int $id, array $attributes): void
    {
        DB::connection(self::CONN)->table('jurusans')
            ->where('id', $id)
            ->update($attributes + ['updated_at' => now()]);
    }

    public function delete(int $id): void
    {
        DB::connection(self::CONN)->table('jurusans')->where('id', $id)->delete();
    }

    /** Berapa program studi yang memakai nama jurusan ini. */
    public function programCount(string $name): int
    {
        return DB::connection(self::CONN)->table('programs')->where('jurusan', $name)->count();
    }

    /** Berapa staf (Kajur) yang lingkupnya dipatok ke nama jurusan ini. */
    public function userCount(string $name): int
    {
        return DB::connection(self::CONN)->table('users')->where('jurusan', $name)->count();
    }

    /**
     * Rambatkan penggantian nama ke seluruh kolom teks yang menyimpannya.
     *
     * @return array{programs: int, users: int} jumlah baris yang tersentuh
     */
    public function renameUsages(string $from, string $to): array
    {
        $programs = DB::connection(self::CONN)->table('programs')
            ->where('jurusan', $from)
            ->update(['jurusan' => $to, 'updated_at' => now()]);

        // users.jurusan adalah lingkup akses Kajur. Kalau tidak ikut diganti,
        // Kajur yang bersangkutan kehilangan seluruh datanya secara diam-diam
        // -- penyaringnya mencocokkan teks, dan tidak ada yang cocok lagi.
        $users = DB::connection(self::CONN)->table('users')
            ->where('jurusan', $from)
            ->update(['jurusan' => $to, 'updated_at' => now()]);

        return ['programs' => $programs, 'users' => $users];
    }
}
