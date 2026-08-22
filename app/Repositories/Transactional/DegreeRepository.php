<?php
// app/Repositories/Transactional/DegreeRepository.php
namespace App\Repositories\Transactional;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * DegreeRepository — daftar induk jenjang program studi.
 *
 * `programCount()` membaca `programs.degree` yang menyimpan KODE, bukan id.
 * Itu disengaja (lihat migrasi `link_programs_degree_to_degrees_table`), jadi
 * join-nya memang lewat teks.
 */
class DegreeRepository
{
    private const CONN = 'oltp';

    /**
     * Apakah tabelnya sudah ada.
     *
     * Dibutuhkan karena aturan validasi memanggil daftar jenjang lewat
     * `App\Support\Degree`, dan itu bisa terjadi pada pemasangan yang belum
     * dimigrasi — misalnya saat `php artisan migrate` sendiri sedang berjalan.
     * Tanpa penjaga ini, seluruh pemasangan baru mati sebelum sempat membuat
     * tabelnya.
     */
    public function tableExists(): bool
    {
        return Schema::connection(self::CONN)->hasTable('degrees');
    }

    /** Kode jenjang aktif, urut tampil. @return array<string> */
    public function activeCodes(): array
    {
        return DB::connection(self::CONN)->table('degrees')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->pluck('code')
            ->all();
    }

    /**
     * Seluruh kode, termasuk yang dinonaktifkan.
     *
     * Dipakai validasi penyaring dasbor: prodi lama boleh saja berjenjang yang
     * kini dinonaktifkan, dan menyaringnya harus tetap sah — kalau tidak,
     * datanya ada di dasbor tapi tidak bisa dipilih.
     *
     * @return array<string>
     */
    public function allCodes(): array
    {
        return DB::connection(self::CONN)->table('degrees')
            ->orderBy('sort_order')
            ->pluck('code')
            ->all();
    }

    public function allWithUsage(): Collection
    {
        return collect(
            DB::connection(self::CONN)->table('degrees as d')
                ->leftJoin('programs as p', 'p.degree', '=', 'd.code')
                ->groupBy('d.id', 'd.code', 'd.label', 'd.sort_order', 'd.is_seeded', 'd.is_active')
                ->orderBy('d.sort_order')
                ->select([
                    'd.id', 'd.code', 'd.label', 'd.sort_order', 'd.is_seeded', 'd.is_active',
                    DB::raw('COUNT(p.id) as program_count'),
                ])
                ->get()
        );
    }

    public function find(int $id): ?object
    {
        return DB::connection(self::CONN)->table('degrees')->where('id', $id)->first();
    }

    public function findByCode(string $code): ?object
    {
        return DB::connection(self::CONN)->table('degrees')->where('code', $code)->first();
    }

    public function insert(array $attributes): int
    {
        $now = now();

        return DB::connection(self::CONN)->table('degrees')
            ->insertGetId($attributes + ['created_at' => $now, 'updated_at' => $now]);
    }

    public function update(int $id, array $attributes): void
    {
        DB::connection(self::CONN)->table('degrees')->where('id', $id)
            ->update($attributes + ['updated_at' => now()]);
    }

    public function delete(int $id): void
    {
        DB::connection(self::CONN)->table('degrees')->where('id', $id)->delete();
    }

    /** Jumlah program studi yang memakai kode jenjang ini. */
    public function programCount(string $code): int
    {
        return DB::connection(self::CONN)->table('programs')->where('degree', $code)->count();
    }

    /** Nilai `sort_order` berikutnya, supaya jenjang baru jatuh di paling bawah. */
    public function nextSortOrder(): int
    {
        return ((int) DB::connection(self::CONN)->table('degrees')->max('sort_order')) + 10;
    }
}
