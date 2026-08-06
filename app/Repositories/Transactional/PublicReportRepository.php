<?php
// app/Repositories/Transactional/PublicReportRepository.php
namespace App\Repositories\Transactional;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Laporan Tracer Study tahunan yang dipublikasikan ke masyarakat umum.
 *
 * Dua pintu baca yang sengaja dipisah:
 *   - listAll()       : untuk admin, termasuk yang belum dipublikasikan.
 *   - listPublished() : untuk halaman publik, hanya yang sudah terbit.
 *
 * Pemisahan ini disengaja supaya tidak ada satu method berparameter boolean
 * yang kalau dipanggil salah di controller publik membocorkan draf laporan.
 */
class PublicReportRepository
{
    private const CONN = 'oltp';
    private const TABLE = 'public_reports';

    /** Kolom yang aman dikirim ke publik -- file_path TIDAK termasuk. */
    private const PUBLIC_COLUMNS = [
        'id', 'title', 'description', 'report_year',
        'file_name', 'file_size', 'mime_type',
        'published_at', 'download_count',
    ];

    // ═══════════════════════════════════════════════════════════
    // READ
    // ═══════════════════════════════════════════════════════════

    /** Semua laporan, terbaru dulu. Dipakai halaman admin. */
    public function listAll(): Collection
    {
        return collect(
            DB::connection(self::CONN)->table(self::TABLE . ' as r')
                ->leftJoin('users as u', 'u.id', '=', 'r.uploaded_by')
                ->select('r.*', 'u.name as uploaded_by_name')
                ->orderByDesc('r.report_year')
                ->orderByDesc('r.id')
                ->get()
        );
    }

    /**
     * Laporan terbit saja, dibatasi rentang tahun kalau diberikan.
     *
     * @param array{start?: int, end?: int} $yearRange
     */
    public function listPublished(array $yearRange = []): Collection
    {
        $query = DB::connection(self::CONN)->table(self::TABLE)
            ->select(self::PUBLIC_COLUMNS)
            ->where('is_published', true);

        if (isset($yearRange['start'])) {
            $query->where('report_year', '>=', $yearRange['start']);
        }
        if (isset($yearRange['end'])) {
            $query->where('report_year', '<=', $yearRange['end']);
        }

        return collect($query->orderByDesc('report_year')->orderByDesc('id')->get());
    }

    public function findById(int $id): ?object
    {
        return DB::connection(self::CONN)->table(self::TABLE)->where('id', $id)->first();
    }

    /** Laporan terbit saja -- dipakai route unduhan publik. */
    public function findPublishedById(int $id): ?object
    {
        return DB::connection(self::CONN)->table(self::TABLE)
            ->where('id', $id)
            ->where('is_published', true)
            ->first();
    }

    // ═══════════════════════════════════════════════════════════
    // WRITE
    // ═══════════════════════════════════════════════════════════

    public function insert(array $data): int
    {
        $now = Carbon::now();

        return DB::connection(self::CONN)->table(self::TABLE)->insertGetId(
            array_merge($data, ['created_at' => $now, 'updated_at' => $now])
        );
    }

    public function updateById(int $id, array $data): bool
    {
        return DB::connection(self::CONN)->table(self::TABLE)
            ->where('id', $id)
            ->update(array_merge($data, ['updated_at' => Carbon::now()])) > 0;
    }

    public function deleteById(int $id): bool
    {
        return DB::connection(self::CONN)->table(self::TABLE)->where('id', $id)->delete() > 0;
    }

    /**
     * Naikkan penghitung unduhan.
     *
     * increment() dilakukan di database, bukan baca-tambah-tulis di PHP, supaya
     * dua unduhan bersamaan tidak saling menimpa.
     */
    public function incrementDownloadCount(int $id): void
    {
        DB::connection(self::CONN)->table(self::TABLE)->where('id', $id)->increment('download_count');
    }
}
