<?php

namespace App\Repositories\Analytical;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ResponseRateRepository
{
    // ──────────────────────────────────────────────────────────────
    //  1. BAR — agregat per prodi (Kpi1ParticipationChart)
    // ──────────────────────────────────────────────────────────────

    /**
     * Hitung jumlah alumni per prodi per status.
     * LEFT JOIN supaya alumni tanpa row responses tetap terhitung (belum_mengisi).
     *
     * @return Collection<array{nama_prodi, jenjang, total, count_selesai, count_on_going, count_belum_mengisi}>
     */
    public function getBarData(
        ?string $jenjang         = null,
        ?string $namaProdi       = null,
        ?string $graduationYear  = null,
    ): Collection {
        $query = DB::table('alumni_profiles as ap')
            ->join('programs as p', 'ap.program_id', '=', 'p.id')
            ->leftJoin('responses as r', 'r.alumni_id', '=', 'ap.id')
            ->select([
                'p.id as program_id',
                'p.name as nama_prodi',
                'p.degree as jenjang',
                DB::raw('COUNT(ap.id) as total'),
                DB::raw("SUM(CASE WHEN r.status = 'submitted' THEN 1 ELSE 0 END) as count_selesai"),
                DB::raw("SUM(CASE WHEN r.status = 'ongoing' THEN 1 ELSE 0 END) as count_on_going"),
                DB::raw("SUM(CASE WHEN r.status = 'started' OR r.status IS NULL THEN 1 ELSE 0 END) as count_belum_mengisi"),
            ])
            ->groupBy('p.id', 'p.name', 'p.degree');

        $this->applyCommonFilters($query, $jenjang, $namaProdi, $graduationYear);

        return collect($query->get())->map(fn($r) => [
            'nama_prodi'          => $r->nama_prodi,
            'jenjang'             => $r->jenjang,
            'total'               => (int) $r->total,
            'count_selesai'       => (int) $r->count_selesai,
            'count_on_going'      => (int) $r->count_on_going,
            'count_belum_mengisi' => (int) $r->count_belum_mengisi,
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    //  2. PIE — agregat keseluruhan 3 status (Kpi2CompletionStatusChart)
    // ──────────────────────────────────────────────────────────────

    /**
     * Hitung total alumni per status, AGGREGATE seluruh institusi
     * (atau sesuai filter), TANPA breakdown per prodi.
     *
     * @return array{count_selesai: int, count_on_going: int, count_belum_mengisi: int, total: int}
     */
    public function getPieData(
        ?string $jenjang         = null,
        ?string $namaProdi       = null,
        ?string $graduationYear  = null,
    ): array {
        $query = DB::table('alumni_profiles as ap')
            ->join('programs as p', 'ap.program_id', '=', 'p.id')
            ->leftJoin('responses as r', 'r.alumni_id', '=', 'ap.id')
            ->select([
                DB::raw('COUNT(ap.id) as total'),
                DB::raw("SUM(CASE WHEN r.status = 'submitted' THEN 1 ELSE 0 END) as count_selesai"),
                DB::raw("SUM(CASE WHEN r.status = 'ongoing' THEN 1 ELSE 0 END) as count_on_going"),
                DB::raw("SUM(CASE WHEN r.status = 'started' OR r.status IS NULL THEN 1 ELSE 0 END) as count_belum_mengisi"),
            ]);

        $this->applyCommonFilters($query, $jenjang, $namaProdi, $graduationYear);

        $r = $query->first();

        return [
            'total'               => (int) ($r->total               ?? 0),
            'count_selesai'       => (int) ($r->count_selesai        ?? 0),
            'count_on_going'      => (int) ($r->count_on_going       ?? 0),
            'count_belum_mengisi' => (int) ($r->count_belum_mengisi  ?? 0),
        ];
    }

    // ──────────────────────────────────────────────────────────────
    //  3. TREND — response rate per graduation_year (Kpi3ParticipationTrendChart)
    // ──────────────────────────────────────────────────────────────

    /**
     * Hitung response rate (% sudah submit) per graduation_year,
     * untuk chart tren antar periode.
     *
     * @return Collection<array{graduation_year, total, count_selesai, count_on_going, count_belum_mengisi}>
     */
    public function getTrendData(
        ?string $jenjang         = null,
        ?string $namaProdi       = null,
        ?string $graduationYear  = null,
    ): Collection {
        $query = DB::table('alumni_profiles as ap')
            ->join('programs as p', 'ap.program_id', '=', 'p.id')
            ->leftJoin('responses as r', 'r.alumni_id', '=', 'ap.id')
            ->select([
                'ap.graduation_year as graduation_year',
                DB::raw('COUNT(ap.id) as total'),
                DB::raw("SUM(CASE WHEN r.status = 'submitted' THEN 1 ELSE 0 END) as count_selesai"),
                DB::raw("SUM(CASE WHEN r.status = 'ongoing' THEN 1 ELSE 0 END) as count_on_going"),
                DB::raw("SUM(CASE WHEN r.status = 'started' OR r.status IS NULL THEN 1 ELSE 0 END) as count_belum_mengisi"),
            ])
            ->groupBy('ap.graduation_year');

        $this->applyCommonFilters($query, $jenjang, $namaProdi, $graduationYear);

        return collect($query->orderBy('ap.graduation_year', 'asc')->get())->map(fn($r) => [
            'graduation_year'     => $r->graduation_year,
            'total'               => (int) $r->total,
            'count_selesai'       => (int) $r->count_selesai,
            'count_on_going'      => (int) $r->count_on_going,
            'count_belum_mengisi' => (int) $r->count_belum_mengisi,
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    //  4. DRILL-DOWN — alumni individual per status
    // ──────────────────────────────────────────────────────────────

    /**
     * List alumni berdasarkan status yang diklik.
     * Dipakai oleh ketiga chart (Kpi1, Kpi2, Kpi3) — semua drill-down
     * ke endpoint yang sama, hanya beda parameter filter yang dikirim FE.
     *
     * @return array{data: array, page: int, per_page: int, total_on_page: int, total_count: int}
     */
    public function getDetailAlumniByStatus(
        string  $status,
        ?string $jenjang         = null,
        ?string $namaProdi       = null,
        ?string $graduationYear  = null,
        ?string $search          = null,
        int     $page            = 1,
        int     $perPage         = 15,
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

        $query = $this->applyStatusFilter($query, $status);
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

    /**
     * Filter umum yang dipakai di semua query: jenjang, nama_prodi, graduation_year.
     */
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
     * Terapkan filter WHERE sesuai status yang diminta.
     * Murni berdasarkan nilai kolom r.status.
     */
    private function applyStatusFilter($query, string $status)
    {
        return match ($status) {
            'selesai'       => $query->where('r.status', 'submitted'),
            'on_going'      => $query->where('r.status', 'ongoing'),
            'belum_mengisi' => $query->where(function ($q) {
                                   $q->where('r.status', 'started')
                                     ->orWhereNull('r.status');
                               }),
            default => $query,
        };
    }

    /**
     * Resolve label status manusiawi dari nilai kolom r.status.
     */
    private function resolveStatusLabel(?string $rawStatus): string
    {
        return match ($rawStatus) {
            'submitted' => 'Selesai',
            'ongoing'   => 'On Going',
            default     => 'Belum Mengisi', // 'started' atau null
        };
    }
}