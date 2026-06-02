<?php

namespace App\Services\Analytical;

use App\DTOs\Analytical\Keterserapan\KeterserapanBarDTO;
use App\DTOs\Analytical\Keterserapan\KeterserapanPieDTO;
use App\DTOs\Analytical\Keterserapan\KeterserapanDrillDownDTO;
use App\DTOs\Analytical\Keterserapan\KeterserapanBandingkanDTO;
use App\Repositories\Analytical\KeterserapanRepository;
use Illuminate\Support\Collection;

/**
 * KeterserapanService
 *
 * Orkestrasi data dari KeterserapanRepository:
 *   - getBar()          → bar stacked 100% per tahun lulus
 *   - getPie()          → pie distribusi status snapshot terkini
 *   - getDrillDown()    → drill-down list alumni per status
 *   - getDrillDownTahun() → drill-down list alumni per tahun lulus
 *   - getBandingkan()   → perbandingan keterserapan per prodi
 *   - getProdiList()    → daftar prodi untuk chip filter
 *
 * Semua kalkulasi persentase dan reshape dilakukan di sini atau di Repository.
 * Controller tidak melakukan kalkulasi — cukup delegasi ke Service.
 *
 * Taruh di: app/Services/Analytical/KeterserapanService.php
 */
class KeterserapanService
{
    public function __construct(
        private readonly KeterserapanRepository $repo,
    ) {}

    // ──────────────────────────────────────────────────────────────
    //  BAR — keterserapan (terserap vs tidak) per tahun lulus
    // ──────────────────────────────────────────────────────────────

    /**
     * Label status yang diklasifikasikan sebagai "TERSERAP" sesuai IKU 2 Kemendikbud.
     *
     * IKU 2: Lulusan yang langsung bekerja / melanjutkan studi / berwirausaha
     * dalam jangka waktu ≤1 tahun setelah kelulusan.
     *
     * Label di bawah disesuaikan dengan nilai aktual DimStatusAlumni.label di DW.
     * Jika ETL mengubah label, update konstanta ini saja.
     */
    private const STATUS_TERSERAP = [
        'Bekerja',
        'Wirausaha',
        'Melanjutkan Studi',   // label aktual di DW (bukan "Studi Lanjut")
    ];

    /**
     * Data untuk grafik bar keterserapan per tahun.
     *
     * Output per tahun:
     *   - count_terserap / pct_terserap  : Bekerja + Wirausaha + Studi Lanjut
     *   - count_tidak    / pct_tidak     : semua status lain
     *   - breakdown[]                    : rincian per status untuk tooltip hover
     *
     * Flow:
     *  1. Fetch raw dari repo (group by tahun × status)
     *  2. Pivot + klasifikasikan terserap vs tidak per tahun
     *  3. Rakit DTO
     */
    public function getBar(array $params): KeterserapanBarDTO
    {
        $raw = $this->repo->getDistribusiStatusPerTahun(
            jenjang:        $params['jenjang']         ?? null,
            jurusan:        $params['jurusan']         ?? null,
            namaProdi:      $params['nama_prodi']      ?? null,
            mingguSnapshot: $params['minggu_snapshot'] ?? null,
        );

        $rows = $this->pivotKeterserapanPerTahun($raw);

        return new KeterserapanBarDTO(
            rows:           $rows,
            availableTahun: $this->repo->getAvailableTahunLulus(),
            filters:        $this->activeFilters($params, ['jenjang','jurusan','nama_prodi','minggu_snapshot']),
            statusTerserap: self::STATUS_TERSERAP,
        );
    }

    // ──────────────────────────────────────────────────────────────
    //  PIE — distribusi status snapshot terkini
    // ──────────────────────────────────────────────────────────────

    /**
     * Data untuk grafik pie distribusi status alumni.
     */
    public function getPie(array $params): KeterserapanPieDTO
    {
        $raw = $this->repo->getDistribusiStatusSnapshot(
            jenjang:        $params['jenjang']         ?? null,
            jurusan:        $params['jurusan']         ?? null,
            namaProdi:      $params['nama_prodi']      ?? null,
            tahunLulus:     $params['tahun_lulus']     ?? null,
            mingguSnapshot: $params['minggu_snapshot'] ?? null,
        );

        $total  = $raw->sum('count');
        $slices = $raw->map(fn($r) => [
            'status' => $r['status'],
            'count'  => $r['count'],
            'pct'    => $total > 0 ? round($r['count'] / $total * 100, 1) : 0.0,
        ])->values()->toArray();

        return new KeterserapanPieDTO(
            slices:  $slices,
            total:   $total,
            filters: $this->activeFilters($params),
        );
    }

    // ──────────────────────────────────────────────────────────────
    //  DRILL-DOWN — router utama (dipanggil Controller)
    // ──────────────────────────────────────────────────────────────

    /**
     * Router: ada tahun_lulus → getDrillDownTahun, hanya status → getDrillDownStatus.
     * Bisa juga kombinasi keduanya (klik segmen bar tertentu di tahun tertentu).
     */
    public function getDrillDown(array $params): KeterserapanDrillDownDTO
    {
        if (!empty($params['tahun_lulus'])) {
            return $this->getDrillDownTahun($params);
        }

        return $this->getDrillDownStatus($params);
    }

    // ──────────────────────────────────────────────────────────────
    //  DRILL-DOWN — by status (klik pie)
    // ──────────────────────────────────────────────────────────────

    /**
     * List alumni per status — untuk modal klik segmen pie.
     */
    private function getDrillDownStatus(array $params): KeterserapanDrillDownDTO
    {
        $page    = max(1, (int) ($params['page']     ?? 1));
        $perPage = min(100, max(5, (int) ($params['per_page'] ?? 15)));

        $result = $this->repo->getDetailAlumniByStatus(
            statusLabel:    $params['status']          ?? '',
            jenjang:        $params['jenjang']         ?? null,
            jurusan:        $params['jurusan']         ?? null,
            namaProdi:      $params['nama_prodi']      ?? null,
            tahunLulus:     $params['tahun_lulus']     ?? null,
            mingguSnapshot: $params['minggu_snapshot'] ?? null,
            search:         $params['search']          ?? null,
            page:           $page,
            perPage:        $perPage,
        );

        return new KeterserapanDrillDownDTO(
            data:        $result['data'],
            status:      $params['status'] ?? '',
            page:        $page,
            perPage:     $perPage,
            totalOnPage: $result['total_on_page'],
            filters:     $this->activeFilters($params),
        );
    }

    // ──────────────────────────────────────────────────────────────
    //  DRILL-DOWN — by tahun lulus (klik bar)
    // ──────────────────────────────────────────────────────────────

    /**
     * List alumni per tahun lulus — untuk modal klik bar tren.
     * Status opsional: kosong = semua, "terserap"/"tidak" = shorthand kategori.
     */
    private function getDrillDownTahun(array $params): KeterserapanDrillDownDTO
    {
        $page        = max(1, (int) ($params['page']     ?? 1));
        $perPage     = min(100, max(5, (int) ($params['per_page'] ?? 15)));
        $statusLabel = $this->resolveStatusLabel($params['status'] ?? null);

        $result = $this->repo->getDetailAlumniByTahun(
            tahunLulus:     $params['tahun_lulus']     ?? '',
            statusLabel:    $statusLabel,
            jenjang:        $params['jenjang']         ?? null,
            jurusan:        $params['jurusan']         ?? null,
            namaProdi:      $params['nama_prodi']      ?? null,
            mingguSnapshot: $params['minggu_snapshot'] ?? null,
            search:         $params['search']          ?? null,
            page:           $page,
            perPage:        $perPage,
        );

        return new KeterserapanDrillDownDTO(
            data:        $result['data'],
            status:      $params['status'] ?? 'semua',
            page:        $page,
            perPage:     $perPage,
            totalOnPage: $result['total_on_page'],
            filters:     $this->activeFilters($params, [
                'tahun_lulus', 'status', 'jenjang', 'jurusan', 'nama_prodi', 'minggu_snapshot',
            ]),
        );
    }

    // ──────────────────────────────────────────────────────────────
    //  PRIVATE HELPERS (tambah resolveStatusLabel)
    // ──────────────────────────────────────────────────────────────

    /**
     * Resolve shorthand status dari FE ke label DW, atau null jika semua.
     *
     * 'terserap' → null karena filter multi-label tidak bisa pakai equals.
     *              Repo akan pakai notEquals 'Belum Bekerja' sebagai gantinya.
     * 'tidak'    → 'Belum Bekerja' (label DW)
     * label lain → pakai apa adanya
     */
    private function resolveStatusLabel(?string $status): ?string
    {
        return match ($status) {
            null, '', 'semua' => null,
            'terserap'        => null,   // ditangani repo via excludeStatus
            'tidak'           => 'Belum Bekerja',
            default           => $status,
        };
    }

    // ──────────────────────────────────────────────────────────────
    //  BANDINGKAN PER PRODI
    // ──────────────────────────────────────────────────────────────

    /**
     * Data untuk halaman Bandingkan Prodi — bar stacked + tabel.
     *
     * @param  array<string>  $prodiFilter  Nama prodi yang dipilih di chip filter.
     *                                      Kosong = tampilkan semua.
     */
    public function getBandingkan(array $params): KeterserapanBandingkanDTO
    {
        // chip filter: ?prodi[]=Teknik Informatika&prodi[]=Teknik Kimia
        $prodiFilter = $params['prodi'] ?? [];
        if (is_string($prodiFilter)) {
            $prodiFilter = [$prodiFilter]; // guard: kalau dikirim string tunggal
        }

        $result = $this->repo->getDistribusiStatusPerProdi(
            prodiFilter:    $prodiFilter,
            jenjang:        $params['jenjang']         ?? null,
            jurusan:        $params['jurusan']         ?? null,
            tahunLulus:     $params['tahun_lulus']     ?? null,
            mingguSnapshot: $params['minggu_snapshot'] ?? null,
        );

        return new KeterserapanBandingkanDTO(
            chart:     $result['chart'],
            table:     $result['table'],
            prodiList: $result['prodi_list'],
            filters:   $this->activeFilters($params),
        );
    }

    // ──────────────────────────────────────────────────────────────
    //  PRIVATE HELPERS
    // ──────────────────────────────────────────────────────────────

    /**
     * Pivot + klasifikasikan baris flat menjadi format keterserapan per tahun.
     *
     * Input : [{tahun_lulus, status, count}, ...]
     * Output: [{
     *   tahun_lulus,
     *   total,
     *   count_terserap, pct_terserap,
     *   count_tidak,    pct_tidak,
     *   breakdown: [{status, count, pct, kategori:'terserap'|'tidak'}]
     * }]
     */
    private function pivotKeterserapanPerTahun(Collection $raw): array
    {
        $grouped = $raw->groupBy('tahun_lulus');
        $result  = [];

        foreach ($grouped as $tahun => $rows) {
            $total = $rows->sum('count');

            // Hitung breakdown per status + tandai kategori
            $breakdown = $rows->map(fn($r) => [
                'status'   => $r['status'],
                'count'    => $r['count'],
                'pct'      => $total > 0 ? round($r['count'] / $total * 100, 1) : 0.0,
                'kategori' => in_array($r['status'], self::STATUS_TERSERAP, true)
                              ? 'terserap'
                              : 'tidak',
            ])->sortBy('status')->values()->toArray();

            // Agregat terserap vs tidak
            $countTerserap = collect($breakdown)
                ->where('kategori', 'terserap')
                ->sum('count');

            $countTidak = $total - $countTerserap;

            $result[] = [
                'tahun_lulus'    => $tahun,
                'total'          => $total,
                'count_terserap' => $countTerserap,
                'pct_terserap'   => $total > 0 ? round($countTerserap / $total * 100, 1) : 0.0,
                'count_tidak'    => $countTidak,
                'pct_tidak'      => $total > 0 ? round($countTidak    / $total * 100, 1) : 0.0,
                'breakdown'      => $breakdown,
            ];
        }

        usort($result, fn($a, $b) => $a['tahun_lulus'] <=> $b['tahun_lulus']);

        return $result;
    }

    /**
     * Kumpulkan filter yang aktif (non-null, non-empty) untuk response metadata.
     */
    private function activeFilters(array $params, array $keys = []): array
    {
        $allKeys = ['jenjang', 'jurusan', 'nama_prodi', 'tahun_lulus', 'minggu_snapshot', 'status'];
        $keys    = empty($keys) ? $allKeys : $keys;

        return array_filter(
            array_intersect_key($params, array_flip($keys)),
            fn($v) => $v !== null && $v !== '' && $v !== [],
        );
    }
}