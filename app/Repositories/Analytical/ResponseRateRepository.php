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

    /**
     * Hitungan di lapisan luar — dijalankan atas hasil perAlumniQuery(), jadi
     * satuannya ALUMNI, bukan baris responses.
     *
     * Urutan prioritasnya penting: satu alumni bisa punya beberapa baris
     * responses (kuesioner global + kuesioner prodi), dan bisa saja campur —
     * mis. global sudah dikirim tapi kuesioner prodi yang baru terbit masih
     * draf. Alumni seperti itu dihitung sebagai "Selesai" sekali saja, tidak
     * bocor ke dua bucket, sehingga ketiga angka selalu berjumlah total.
     */
    private const OUTER_COUNTS = 'COUNT(*) as total,'
        . ' SUM(CASE WHEN n_selesai > 0 THEN 1 ELSE 0 END) as count_submitted,'
        . ' SUM(CASE WHEN n_selesai = 0 AND n_sedang > 0 THEN 1 ELSE 0 END) as count_ongoing,'
        . ' SUM(CASE WHEN n_selesai = 0 AND n_sedang = 0 THEN 1 ELSE 0 END) as count_started';

    /**
     * Query dasar: SATU BARIS PER ALUMNI.
     *
     * Sebelumnya seluruh angka response rate dihitung langsung dari hasil
     * LEFT JOIN ke responses, sehingga alumni yang menjawab kuesioner global
     * DAN kuesioner prodi terhitung dua kali — "Total Kuesioner" menunjukkan
     * 583 untuk 505 alumni, dan response rate ikut salah di pembilang maupun
     * penyebutnya. Meringkas per alumni dulu di sini menghilangkan itu.
     */
    private function perAlumniQuery(
        ?string $jenjang,
        ?string $namaProdi,
        ?string $graduationYear,
        array   $extraSelect = [],
        array   $groupBy     = [],
    ) {
        $query = DB::table('alumni_profiles as ap')
            ->join('programs as p', 'ap.program_id', '=', 'p.id')
            ->leftJoin('responses as r', 'r.alumni_id', '=', 'ap.id')
            ->select(array_merge(['ap.id as alumni_id'], $extraSelect, [
                DB::raw('SUM(CASE WHEN ' . self::SQL_SELESAI . ' THEN 1 ELSE 0 END) as n_selesai'),
                DB::raw('SUM(CASE WHEN ' . self::SQL_SEDANG  . ' THEN 1 ELSE 0 END) as n_sedang'),
            ]))
            ->groupBy(array_merge(['ap.id'], $groupBy));

        $this->applyCommonFilters($query, $jenjang, $namaProdi, $graduationYear);

        return $query;
    }

    // ──────────────────────────────────────────────────────────────
    //  1. BAR
    // ──────────────────────────────────────────────────────────────

    public function getBarData(
        ?string $jenjang        = null,
        ?string $namaProdi      = null,
        ?string $graduationYear = null,
    ): Collection {
        $inner = $this->perAlumniQuery(
            $jenjang, $namaProdi, $graduationYear,
            extraSelect: ['p.id as program_id', 'p.name as nama_prodi', 'p.degree as jenjang'],
            groupBy:     ['p.id', 'p.name', 'p.degree'],
        );

        $query = DB::query()->fromSub($inner, 't')
            ->select(['nama_prodi', 'jenjang'])
            ->selectRaw(self::OUTER_COUNTS)
            ->groupBy('program_id', 'nama_prodi', 'jenjang');

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
        $inner = $this->perAlumniQuery($jenjang, $namaProdi, $graduationYear);

        $r = DB::query()->fromSub($inner, 't')->selectRaw(self::OUTER_COUNTS)->first();

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
        $inner = $this->perAlumniQuery(
            $jenjang, $namaProdi, $graduationYear,
            extraSelect: ['ap.graduation_year'],
            groupBy:     ['ap.graduation_year'],
        );

        $query = DB::query()->fromSub($inner, 't')
            ->select(['graduation_year'])
            ->selectRaw(self::OUTER_COUNTS)
            ->groupBy('graduation_year');

        return collect($query->orderBy('graduation_year', 'asc')->get())->map(fn($r) => [
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
        // SATU BARIS PER ALUMNI. Sebelumnya daftar ini menampilkan hasil join
        // apa adanya, sehingga alumni yang punya kuesioner global + prodi
        // muncul dua kali dengan isi identik — dan total_count-nya pun
        // menghitung baris, bukan orang.
        $inner = DB::table('alumni_profiles as ap')
            ->join('programs as p', 'ap.program_id', '=', 'p.id')
            ->leftJoin('responses as r', 'r.alumni_id', '=', 'ap.id')
            ->select([
                'ap.id as alumni_id',
                'ap.name as nama',
                'ap.nim as nim',
                'ap.graduation_year as graduation_year',
                'p.name as nama_prodi',
                'p.degree as jenjang',
                DB::raw('SUM(CASE WHEN ' . self::SQL_SELESAI . ' THEN 1 ELSE 0 END) as n_selesai'),
                DB::raw('SUM(CASE WHEN ' . self::SQL_SEDANG  . ' THEN 1 ELSE 0 END) as n_sedang'),
                // Waktu paling relevan dari seluruh kuesioner milik alumni ini.
                DB::raw('MIN(r.started_at) as started_at'),
                DB::raw('MAX(r.submitted_at) as submitted_at'),
            ])
            ->groupBy('ap.id', 'ap.name', 'ap.nim', 'ap.graduation_year', 'p.name', 'p.degree');

        $this->applyCommonFilters($inner, $jenjang, $namaProdi, $graduationYear);

        if ($search !== null && $search !== '') {
            $inner->where(function ($q) use ($search) {
                $q->where('ap.name', 'like', "%{$search}%")
                  ->orWhere('ap.nim', 'like', "%{$search}%");
            });
        }

        // Bucket yang sama persis dengan bar/pie/tren, dengan prioritas yang
        // sama pula (lihat OUTER_COUNTS): Selesai menang atas Sedang Mengisi.
        // Ingat: 'started' di API berarti BELUM mulai, draf ada di 'ongoing'.
        $query = DB::query()->fromSub($inner, 't');
        match ($status) {
            'submitted' => $query->whereRaw('n_selesai > 0'),
            'ongoing'   => $query->whereRaw('n_selesai = 0 AND n_sedang > 0'),
            'started'   => $query->whereRaw('n_selesai = 0 AND n_sedang = 0'),
        };

        $totalCount = $query->clone()->count();

        $rows = $query
            ->orderBy('nama', 'asc')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        $data = collect($rows)->map(fn($r) => [
            'nama'            => $r->nama,
            'nim'             => $r->nim,
            'nama_prodi'      => $r->nama_prodi,
            'jenjang'         => $r->jenjang,
            'graduation_year' => $r->graduation_year,
            'status'          => match (true) {
                $r->n_selesai > 0 => 'Selesai',
                $r->n_sedang  > 0 => 'Sedang Mengisi',
                default           => 'Belum Mengisi',
            },
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

}