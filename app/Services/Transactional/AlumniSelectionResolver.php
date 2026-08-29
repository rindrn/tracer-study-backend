<?php

namespace App\Services\Transactional;

use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Mengubah payload seleksi alumni dari frontend (checkbox tabel "Manajemen
 * Email") jadi kandidat baris siap-potong (id/nim/name/email) -- dipakai
 * bersama oleh AlumniCredentialService::issueForSelection() ("Terbitkan
 * Akun") dan fitur reminder, supaya logika hybrid-selection ("pilih semua
 * sesuai filter" + pengecualian manual, ATAU daftar NIM eksplisit) hanya
 * hidup di SATU tempat.
 *
 * TIDAK tahu apa-apa soal email/kredensial/bcrypt -- murni resolusi query.
 * Pemanggil yang menentukan apa yang dilakukan atas baris yang dikembalikan.
 */
class AlumniSelectionResolver
{
    private const CONN = 'oltp';

    /**
     * Satu potong kandidat, diurut NIM menaik dan dipotong kursor -- pola
     * yang sama seperti AlumniCredentialService::findTargets(), supaya
     * pemanggil bisa memakai kursor `after_nim` yang identik untuk kedua
     * mode seleksi.
     *
     * @param  array{
     *   mode: 'explicit'|'filtered',
     *   nims?: list<string>,
     *   filters?: array{graduation_year?: int|null, jurusan?: string|null, program_id?: int|null, only_without_credentials?: bool},
     *   excluded_nims?: list<string>,
     * } $selection
     * $extra: kait opsional untuk pemanggil yang butuh syarat TAMBAHAN di
     * luar seleksi murni -- mis. fitur reminder menambahkan "sudah punya
     * akun DAN belum selesai kuesioner" lewat AlumniProfileRepository,
     * tanpa membuat resolver ini tahu apa-apa soal kuesioner. Dipanggil
     * atas builder yang sama sebelum diurut/dipotong.
     *
     * @return Collection<int, object{id:int, nim:string, name:?string, email:?string}>
     */
    public function resolveChunk(array $selection, ?string $afterNim, int $limit, ?Closure $extra = null): Collection
    {
        $query = $this->buildQuery($selection);
        if ($extra) {
            $extra($query);
        }

        if (!empty($afterNim)) {
            $query->where('nim', '>', $afterNim);
        }

        return collect(
            $query->orderBy('nim')->limit($limit)->get(['id', 'nim', 'name', 'email'])
        );
    }

    /** Sisa kandidat setelah $afterNim -- dihitung ulang, bukan dikurangi dari cacah awal (lihat alasan yang sama di AlumniCredentialService::countRemaining). */
    public function countRemaining(array $selection, string $afterNim, ?Closure $extra = null): int
    {
        $query = $this->buildQuery($selection);
        if ($extra) {
            $extra($query);
        }

        return $query->where('nim', '>', $afterNim)->count();
    }

    private function buildQuery(array $selection): Builder
    {
        $mode = $selection['mode'] ?? 'filtered';

        return $mode === 'explicit'
            ? $this->buildExplicitQuery($selection['nims'] ?? [])
            : $this->buildFilteredQuery($selection['filters'] ?? [], $selection['excluded_nims'] ?? []);
    }

    /**
     * Mode "pilih baris manual" -- daftar NIM tetap, ukurannya ditentukan
     * checkbox yang dicentang petugas di tabel, bukan filter. Tetap
     * diperlakukan sama seperti mode filtered (kursor+limit) supaya
     * pemanggil punya SATU jalur kode, bukan karena himpunannya biasanya
     * besar.
     *
     * @param  list<string> $nims
     */
    private function buildExplicitQuery(array $nims): Builder
    {
        return DB::connection(self::CONN)->table('alumni_profiles')
            ->where('is_active', true)
            ->whereIn('nim', $nims);
    }

    /**
     * Mode "pilih semua sesuai filter, minus pengecualian manual" -- WHERE
     * clause-nya SAMA PERSIS dengan AlumniCredentialService::baseQuery()
     * (yang mendelegasikan ke sini juga, lihat kelas itu), ditambah
     * `excluded_nims` yang cuma berarti di mode ini.
     *
     * @param  array{graduation_year?: int|null, jurusan?: string|null, program_id?: int|null, only_without_credentials?: bool} $filters
     * @param  list<string> $excludedNims
     */
    public function buildFilteredQuery(array $filters, array $excludedNims = []): Builder
    {
        $query = DB::connection(self::CONN)->table('alumni_profiles')
            ->where('is_active', true);

        if (!empty($filters['graduation_year'])) {
            $query->where('graduation_year', (int) $filters['graduation_year']);
        }

        // Jurusan disaring lewat subkueri ke `programs`, bukan JOIN -- lihat
        // alasan yang sama di AlumniCredentialService::baseQuery() (kolom
        // tanpa awalan nama tabel jadi ambigu kalau di-JOIN).
        if (!empty($filters['jurusan'])) {
            $jurusan = (string) $filters['jurusan'];
            $query->whereIn('program_id', function ($sub) use ($jurusan) {
                $sub->from('programs')->select('id')->where('jurusan', $jurusan);
            });
        }

        if (!empty($filters['program_id'])) {
            $query->where('program_id', (int) $filters['program_id']);
        }

        if (!empty($filters['only_without_credentials'])) {
            $query->whereNull('password_issued_at');
        }

        if (!empty($excludedNims)) {
            $query->whereNotIn('nim', $excludedNims);
        }

        return $query;
    }
}
