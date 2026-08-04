<?php

namespace App\Repositories\Analytical;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ResponseRateRepository
{
    /**
     * Definisi tiga bucket status pengisian — dipakai bar, pie, tren, dan
     * drill-down supaya tidak bisa lepas sinkron satu sama lain.
     *
     * Sebelumnya "Sedang Mengisi" dihitung dari r.status = 'ongoing', nilai
     * yang TIDAK PERNAH ada: enum kolomnya hanya started/submitted/verified.
     * Akibatnya irisan itu selamanya 0, dan pengisian yang belum selesai
     * (status 'started') ikut terhitung sebagai "Belum Mengisi".
     *
     * Kosakata parameter API sengaja dipertahankan supaya frontend tidak perlu
     * ikut berubah — perhatikan bahwa 'started' di API berarti BELUM mulai:
     *
     *   API 'submitted' → Selesai        → status submitted/verified
     *   API 'ongoing'   → Sedang Mengisi → status 'started' (draf berjalan)
     *   API 'started'   → Belum Mengisi  → tidak punya baris responses sama sekali
     */
    private const SQL_SELESAI = "r.status IN ('submitted','verified')";
    private const SQL_SEDANG  = "r.status = 'started'";
    private const SQL_BELUM   = "r.status IS NULL";

    // ──────────────────────────────────────────────────────────────
    //  1. BAR
    // ──────────────────────────────────────────────────────────────

    public function getBarData(
        ?string $jenjang        = null,
        ?string $namaProdi      = null,
        ?string $graduationYear = null,
    ): Collection {
        $query = DB::table('alumni_profiles as ap')
            ->join('programs as p', 'ap.program_id', '=', 'p.id')
            ->leftJoin('responses as r', 'r.alumni_id', '=', 'ap.id')
            ->select([
                'p.id as program_id',
                'p.name as nama_prodi',
                'p.degree as jenjang',
                DB::raw('COUNT(ap.id) as total'),
                DB::raw('SUM(CASE WHEN ' . self::SQL_SELESAI . ' THEN 1 ELSE 0 END) as count_submitted'),
                DB::raw('SUM(CASE WHEN ' . self::SQL_SEDANG  . ' THEN 1 ELSE 0 END) as count_ongoing'),
                DB::raw('SUM(CASE WHEN ' . self::SQL_BELUM   . ' THEN 1 ELSE 0 END) as count_started'),
            ])
            ->groupBy('p.id', 'p.name', 'p.degree');

        $this->applyCommonFilters($query, $jenjang, $namaProdi, $graduationYear);

        return collect($query->get())->map(fn($r) => [
            'nama_prodi'     => $r->nama_prodi,
            'jenjang'        => $r->jenjang,
            'total'          => (int) $r->total,
            'count_submitted' => (int) $r->count_submitted,
            'count_ongoing'   => (int) $r->count_ongoing,
            'count_started'   => (int) $r->count_started,
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    //  2. PIE
    // ──────────────────────────────────────────────────────────────

    public function getPieData(
        ?string $jenjang        = null,
        ?string $namaProdi      = null,
        ?string $graduationYear = null,
    ): array {
        $query = DB::table('alumni_profiles as ap')
            ->join('programs as p', 'ap.program_id', '=', 'p.id')
            ->leftJoin('responses as r', 'r.alumni_id', '=', 'ap.id')
            ->select([
                DB::raw('COUNT(ap.id) as total'),
                DB::raw('SUM(CASE WHEN ' . self::SQL_SELESAI . ' THEN 1 ELSE 0 END) as count_submitted'),
                DB::raw('SUM(CASE WHEN ' . self::SQL_SEDANG  . ' THEN 1 ELSE 0 END) as count_ongoing'),
                DB::raw('SUM(CASE WHEN ' . self::SQL_BELUM   . ' THEN 1 ELSE 0 END) as count_started'),
            ]);

        $this->applyCommonFilters($query, $jenjang, $namaProdi, $graduationYear);

        $r = $query->first();

        return [
            'total'           => (int) ($r->total            ?? 0),
            'count_submitted' => (int) ($r->count_submitted  ?? 0),
            'count_ongoing'   => (int) ($r->count_ongoing    ?? 0),
            'count_started'   => (int) ($r->count_started    ?? 0),
        ];
    }

    // ──────────────────────────────────────────────────────────────
    //  3. TREND
    // ──────────────────────────────────────────────────────────────

    public function getTrendData(
        ?string $jenjang        = null,
        ?string $namaProdi      = null,
        ?string $graduationYear = null,
    ): Collection {
        $query = DB::table('alumni_profiles as ap')
            ->join('programs as p', 'ap.program_id', '=', 'p.id')
            ->leftJoin('responses as r', 'r.alumni_id', '=', 'ap.id')
            ->select([
                'ap.graduation_year',
                DB::raw('COUNT(ap.id) as total'),
                DB::raw('SUM(CASE WHEN ' . self::SQL_SELESAI . ' THEN 1 ELSE 0 END) as count_submitted'),
                DB::raw('SUM(CASE WHEN ' . self::SQL_SEDANG  . ' THEN 1 ELSE 0 END) as count_ongoing'),
                DB::raw('SUM(CASE WHEN ' . self::SQL_BELUM   . ' THEN 1 ELSE 0 END) as count_started'),
            ])
            ->groupBy('ap.graduation_year');

        $this->applyCommonFilters($query, $jenjang, $namaProdi, $graduationYear);

        return collect($query->orderBy('ap.graduation_year', 'asc')->get())->map(fn($r) => [
            'graduation_year' => $r->graduation_year,
            'total'           => (int) $r->total,
            'count_submitted' => (int) $r->count_submitted,
            'count_ongoing'   => (int) $r->count_ongoing,
            'count_started'   => (int) $r->count_started,
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    //  4. DRILL-DOWN
    // ──────────────────────────────────────────────────────────────

    public function getDetailAlumniByStatus(
        string  $status,
        ?string $jenjang        = null,
        ?string $namaProdi      = null,
        ?string $graduationYear = null,
        ?string $search         = null,
        int     $page           = 1,
        int     $perPage        = 15,
    ): array {
        $query = DB::table('alumni_profiles as ap')
            ->join('programs as p', 'ap.program_id', '=', 'p.id')
            ->leftJoin('responses as r', 'r.alumni_id', '=', 'ap.id')
            ->select([
                'ap.id as alumni_id',
                'ap.name as nama',
                'ap.nim as nim',
                'ap.graduation_year as graduation_year',
                'p.name as nama_prodi',
                'p.degree as jenjang',
                'r.status as raw_status',
                'r.started_at as started_at',
                'r.submitted_at as submitted_at',
            ]);

        // Bucket yang sama persis dengan bar/pie/tren — lihat konstanta di atas.
        // Ingat: 'started' di sini berarti BELUM mulai (tidak ada baris response),
        // sedangkan draf yang sedang berjalan ada di 'ongoing'.
        match ($status) {
            'submitted' => $query->whereRaw(self::SQL_SELESAI),
            'ongoing'   => $query->whereRaw(self::SQL_SEDANG),
            'started'   => $query->whereRaw(self::SQL_BELUM),
        };

        $this->applyCommonFilters($query, $jenjang, $namaProdi, $graduationYear);

        if ($search !== null && $search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('ap.name', 'like', "%{$search}%")
                  ->orWhere('ap.nim', 'like', "%{$search}%");
            });
        }

        $totalCount = $query->clone()->count();

        $rows = $query
            ->orderBy('ap.name', 'asc')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        $data = collect($rows)->map(fn($r) => [
            'nama'            => $r->nama,
            'nim'             => $r->nim,
            'nama_prodi'      => $r->nama_prodi,
            'jenjang'         => $r->jenjang,
            'graduation_year' => $r->graduation_year,
            'status'          => $this->resolveStatusLabel($r->raw_status),
            'started_at'      => $r->started_at,
            'submitted_at'    => $r->submitted_at,
        ])->toArray();

        return [
            'data'          => $data,
            'page'          => $page,
            'per_page'      => $perPage,
            'total_on_page' => count($data),
            'total_count'   => $totalCount,
        ];
    }

    // ──────────────────────────────────────────────────────────────
    //  PRIVATE HELPERS
    // ──────────────────────────────────────────────────────────────

    private function applyCommonFilters(
        $query,
        ?string $jenjang,
        ?string $namaProdi,
        ?string $graduationYear,
    ): void {
        if ($jenjang !== null && $jenjang !== '') {
            $query->where('p.degree', $jenjang);
        }
        if ($namaProdi !== null && $namaProdi !== '') {
            $query->where('p.name', $namaProdi);
        }
        if ($graduationYear !== null && $graduationYear !== '') {
            $query->where('ap.graduation_year', $graduationYear);
        }
    }

    /**
     * Label kolom Status di tabel drill-down. Membaca responses.status mentah,
     * jadi 'started' di sini adalah nilai DB (draf berjalan) — bukan kosakata
     * parameter API yang artinya belum mulai.
     */
    private function resolveStatusLabel(?string $rawStatus): string
    {
        return match ($rawStatus) {
            'submitted', 'verified' => 'Selesai',
            'started'               => 'Sedang Mengisi',
            default                 => 'Belum Mengisi', // NULL — tidak ada baris response
        };
    }
}