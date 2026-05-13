<?php

namespace App\Repositories\Analytical;

use Illuminate\Support\Facades\DB;

class Kpi7Repository
{
    private string $conn       = 'oltp';
    private string $detailView = 'tracer_oltp.vw_kpi_7_entrepreneurship_details';
    private string $trendView  = 'tracer_oltp.vw_kpi_7_entrepreneurship_trend';
    private string $summaryView= 'tracer_oltp.vw_kpi_7_entrepreneurship_summary';

    // ── CHART ─────────────────────────────────────────────────

    public function getTrend(?int $programId = null): \Illuminate\Support\Collection
    {
        $query = DB::connection($this->conn)
            ->table($this->detailView);

        if ($programId) {
            // filter by program jika ada relasi — skip jika view tidak ada kolom ini
        }

        return $query
            ->selectRaw('graduation_year AS year, COUNT(*) AS value')
            ->groupBy('graduation_year')
            ->orderBy('graduation_year')
            ->get();
    }

    public function getPieDistribution(): \Illuminate\Support\Collection
    {
        return DB::connection($this->conn)
            ->table($this->summaryView)
            ->selectRaw('entrepreneurship_role AS label, total_alumni AS value')
            ->get();
    }

    // ── DETAIL ────────────────────────────────────────────────

    public function getDetails(
        ?int    $year,
        ?string $position,
        int     $perPage,
        int     $page,
    ): array {
        $base = DB::connection($this->conn)
            ->table($this->detailView);

        if ($year) {
            $base->where('graduation_year', $year);
        }

        if ($position) {
            $base->where('entrepreneurship_role', $position);
        }

        $total = (clone $base)->count();
        $rows  = (clone $base)
            ->orderByDesc('graduation_year')
            ->limit($perPage)
            ->offset(($page - 1) * $perPage)
            ->get();

        return [
            'rows'      => $rows,
            'total'     => $total,
            'per_page'  => $perPage,
            'page'      => $page,
            'last_page' => (int) ceil($total / max($perPage, 1)),
        ];
    }

    // ── THRESHOLD ─────────────────────────────────────────────

    public function getThreshold(?int $lamId, ?int $year): ?object
    {
        $query = DB::connection($this->conn)
            ->table('vw_thresholds_complete')
            ->where('threshold_name', 'like', '%Wirausaha%');

        if ($lamId) {
            $query->where('lam_id', $lamId);
        }

        if ($year) {
            $query->where('lam_version_year', $year);
        }

        return $query->first();
    }

    // ── EXPORT ────────────────────────────────────────────────

    public function getAllForExport(?int $year, ?string $position): \Illuminate\Support\Collection
    {
        $query = DB::connection($this->conn)
            ->table($this->detailView);

        if ($year) {
            $query->where('graduation_year', $year);
        }

        if ($position) {
            $query->where('entrepreneurship_role', $position);
        }

        return $query->orderByDesc('graduation_year')->get();
    }
}