<?php

namespace App\Repositories\Analytical;

use Illuminate\Support\Facades\DB;

/**
 * SummaryRepository
 *
 * Query LANGSUNG ke database OLTP (TIDAK lewat Cube.js / OLAP) — sama
 * dengan ResponseRateRepository, untuk segmen Summary Cards Overview Page.
 *
 * Tabel:
 *   - programs           (id, name, degree)
 *   - alumni_profiles    (id, program_id, name, nim, graduation_year)
 *   - responses          (id, alumni_id, status, started_at, submitted_at)
 *
 * Definisi status (murni dari kolom r.status, tidak ada hubungan submitted_at):
 *   submitted = Sudah Mengisi
 *   started   = Belum Mengisi (termasuk alumni tanpa row responses / NULL)
 *   ongoing   = Sedang Mengisi
 */
class SummaryRepository
{
    // ──────────────────────────────────────────────────────────────
    //  1. AGREGAT UTAMA
    // ──────────────────────────────────────────────────────────────

    /**
     * @return array{
     *   total: int,
     *   count_submitted: int,
     *   count_not_submitted: int,
     *   avg_fill_hours: float|null,
     *   count_with_duration: int
     * }
     */
    public function getAggregate(
        ?string $jenjang        = null,
        ?string $namaProdi      = null,
        ?string $graduationYear = null,
    ): array {
        $query = DB::table('alumni_profiles as ap')
            ->join('programs as p', 'ap.program_id', '=', 'p.id')
            ->leftJoin('responses as r', 'r.alumni_id', '=', 'ap.id')
            ->select([
                DB::raw('COUNT(ap.id) as total'),
                DB::raw("SUM(CASE WHEN r.status = 'submitted' THEN 1 ELSE 0 END) as count_submitted"),
                DB::raw("SUM(CASE WHEN r.status = 'started' OR r.status IS NULL THEN 1 ELSE 0 END) as count_not_submitted"),
                // Rata-rata durasi pengisian dalam jam — tetap pakai started_at & submitted_at
                // karena ini kolom timestamp, bukan status
                DB::raw("AVG(CASE WHEN r.started_at IS NOT NULL AND r.submitted_at IS NOT NULL
                            THEN EXTRACT(EPOCH FROM (submitted_at - started_at)) / 3600.0
                            ELSE NULL END) as avg_fill_hours"),
                DB::raw("SUM(CASE WHEN r.started_at IS NOT NULL AND r.submitted_at IS NOT NULL
                            THEN 1 ELSE 0 END) as count_with_duration"),
            ]);

        $this->applyCommonFilters($query, $jenjang, $namaProdi, $graduationYear);

        $r = $query->first();

        return [
            'total'               => (int) ($r->total                ?? 0),
            'count_submitted'     => (int) ($r->count_submitted      ?? 0),
            'count_not_submitted' => (int) ($r->count_not_submitted  ?? 0),
            'avg_fill_hours'      => $r->avg_fill_hours !== null ? (float) $r->avg_fill_hours : null,
            'count_with_duration' => (int) ($r->count_with_duration  ?? 0),
        ];
    }

    // ──────────────────────────────────────────────────────────────
    //  2. RESPONSE RATE PER TAHUN — untuk badge trend
    // ──────────────────────────────────────────────────────────────

    /**
     * Response rate per graduation_year, diurutkan ascending.
     * "Responded" = r.status = 'submitted' (murni dari status, bukan submitted_at).
     *
     * @return array<int, array{graduation_year: string, total: int, count_submitted: int, rate: float}>
     */
    public function getRatePerYear(
        ?string $jenjang        = null,
        ?string $namaProdi      = null,
        ?string $graduationYear = null,
    ): array {
        $query = DB::table('alumni_profiles as ap')
            ->join('programs as p', 'ap.program_id', '=', 'p.id')
            ->leftJoin('responses as r', 'r.alumni_id', '=', 'ap.id')
            ->select([
                'ap.graduation_year as graduation_year',
                DB::raw('COUNT(ap.id) as total'),
                DB::raw("SUM(CASE WHEN r.status = 'submitted' THEN 1 ELSE 0 END) as count_submitted"),
            ])
            ->groupBy('ap.graduation_year');

        $this->applyCommonFilters($query, $jenjang, $namaProdi, $graduationYear);

        $rows = $query->orderBy('ap.graduation_year', 'asc')->get();

        return collect($rows)->map(function ($r) {
            $total = (int) $r->total;
            $sub   = (int) $r->count_submitted;

            return [
                'graduation_year' => $r->graduation_year,
                'total'           => $total,
                'count_submitted' => $sub,
                'rate'            => $total > 0 ? round($sub / $total * 100, 1) : 0.0,
            ];
        })->values()->toArray();
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
}