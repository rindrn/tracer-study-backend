<?php

namespace App\Services\Analytical;

use App\DTOs\Analytical\Keterserapan\KeterserapanBarDTO;
use App\DTOs\Analytical\Keterserapan\KeterserapanPieDTO;
use App\DTOs\Analytical\Keterserapan\KeterserapanDrillDownDTO;
use App\DTOs\Analytical\Keterserapan\KeterserapanBandingkanDTO;
use App\Repositories\Analytical\KeterserapanRepository;
use App\Repositories\Config\KpiCategoryMappingRepository;
use App\Traits\WithCache;
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
 */
class KeterserapanService
{
    use WithCache;

    private const TTL = 3600;

    /**
     * Dulu hardcode const STATUS_TERSERAP -- SEKARANG dibaca dinamis dari
     * public.kpi_category_mapping (semantic_role='status_pekerjaan',
     * digunakan_oleh='iku2_keterserapan', kpi_category='terserap'). Cache
     * di memory per instance service (short-lived per request), TIDAK
     * pakai Redis di sini -- data ini sudah dilindungi TTL cache di level
     * getBar()/getPie() dkk, memoize sekali per request sudah cukup.
     */
    private ?array $statusTerserapCache = null;

    public function __construct(
        private readonly KeterserapanRepository $repo,
        private readonly KpiCategoryMappingRepository $kpiMappingRepo,
    ) {}

    /** @return string[] label status yang termasuk kategori "terserap" (IKU 2 Kemendikbud). */
    private function statusTerserap(): array
    {
        return $this->statusTerserapCache ??= $this->kpiMappingRepo->optionLabelsFor(
            'status_pekerjaan',
            'iku2_keterserapan',
            'terserap'
        );
    }

    // ── BAR ───────────────────────────────────────────────────────

    public function getBar(array $params): KeterserapanBarDTO
    {
        $key = $this->key('keterserapan:bar', $params);

        $cached = $this->remember($key, function () use ($params) {
            $raw = $this->repo->getDistribusiStatusPerTahun(
                jenjang:        $params['jenjang']         ?? null,
                jurusan:        $params['jurusan']         ?? null,
                namaProdi:      $params['nama_prodi']      ?? null,
                mingguSnapshot: $params['minggu_snapshot'] ?? null,
            );

            return [
                'rows'           => $this->pivotKeterserapanPerTahun($raw),
                'availableTahun' => $this->repo->getAvailableTahunLulus(),
            ];
        }, self::TTL, ['analytics-dashboard']);

        return new KeterserapanBarDTO(
            rows:           $cached['rows'],
            availableTahun: $cached['availableTahun'],
            filters:        $this->activeFilters($params, ['jenjang', 'jurusan', 'nama_prodi', 'minggu_snapshot']),
            statusTerserap: $this->statusTerserap(),
        );
    }

    // ── PIE ───────────────────────────────────────────────────────

    public function getPie(array $params): KeterserapanPieDTO
    {
        $key = $this->key('keterserapan:pie', $params);

        $cached = $this->remember($key, function () use ($params) {
            $raw   = $this->repo->getDistribusiStatusSnapshot(
                jenjang:        $params['jenjang']         ?? null,
                jurusan:        $params['jurusan']         ?? null,
                namaProdi:      $params['nama_prodi']      ?? null,
                tahunLulus:     $params['tahun_lulus']     ?? null,
                mingguSnapshot: $params['minggu_snapshot'] ?? null,
            );
            $total = $raw->sum('count');

            return [
                'slices' => $raw->map(fn($r) => [
                    'status' => $r['status'],
                    'count'  => $r['count'],
                    'pct'    => $total > 0 ? round($r['count'] / $total * 100, 1) : 0.0,
                ])->values()->toArray(),
                'total' => $total,
            ];
        }, self::TTL, ['analytics-dashboard']);

        return new KeterserapanPieDTO(
            slices:  $cached['slices'],
            total:   $cached['total'],
            filters: $this->activeFilters($params),
        );
    }

    // ── BANDINGKAN ────────────────────────────────────────────────

    public function getBandingkan(array $params): KeterserapanBandingkanDTO
    {
        $key = $this->key('keterserapan:bandingkan', $params);

        $cached = $this->remember($key, function () use ($params) {
            $prodiFilter = is_string($params['prodi'] ?? null)
                ? [$params['prodi']]
                : ($params['prodi'] ?? []);

            return $this->repo->getDistribusiStatusPerProdi(
                prodiFilter:    $prodiFilter,
                jenjang:        $params['jenjang']         ?? null,
                jurusan:        $params['jurusan']         ?? null,
                tahunLulus:     $params['tahun_lulus']     ?? null,
                mingguSnapshot: $params['minggu_snapshot'] ?? null,
            );
        }, self::TTL, ['analytics-dashboard']);

        return new KeterserapanBandingkanDTO(
            chart:     $cached['chart'],
            table:     $cached['table'],
            prodiList: $cached['prodi_list'],
            filters:   $this->activeFilters($params),
        );
    }

    // ── DRILL-DOWN (tidak di-cache) ───────────────────────────────

    public function getDrillDown(array $params): KeterserapanDrillDownDTO
    {
        if (!empty($params['tahun_lulus'])) {
            return $this->getDrillDownTahun($params);
        }
        return $this->getDrillDownStatus($params);
    }

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

    private function getDrillDownTahun(array $params): KeterserapanDrillDownDTO
    {
        $page         = max(1, (int) ($params['page']     ?? 1));
        $perPage      = min(100, max(5, (int) ($params['per_page'] ?? 15)));
        $statusFilter = $this->resolveStatusFilter($params['status'] ?? null);

        $result = $this->repo->getDetailAlumniByTahun(
            tahunLulus:     $params['tahun_lulus']     ?? '',
            statusFilter:   $statusFilter,
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

    // ── Helpers ───────────────────────────────────────────────────

    private function resolveStatusFilter(?string $status): mixed
    {
        return match ($status) {
            null, '', 'semua' => null,
            'terserap'        => $this->statusTerserap(),
            'tidak'           => 'Belum memungkinkan bekerja',
            default           => $status,
        };
    }

    private function pivotKeterserapanPerTahun(Collection $raw): array
    {
        $result = [];

        foreach ($raw->groupBy('tahun_lulus') as $tahun => $rows) {
            $total     = $rows->sum('count');
            $breakdown = $rows->map(fn($r) => [
                'status'   => $r['status'],
                'count'    => $r['count'],
                'pct'      => $total > 0 ? round($r['count'] / $total * 100, 1) : 0.0,
                'kategori' => in_array($r['status'], $this->statusTerserap(), true) ? 'terserap' : 'tidak',
            ])->sortBy('status')->values()->toArray();

            $countTerserap = collect($breakdown)->where('kategori', 'terserap')->sum('count');
            $countTidak    = $total - $countTerserap;

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

    private function key(string $prefix, array $params): string
    {
        $relevant = array_diff_key($params, array_flip(['page', 'per_page', 'search']));
        ksort($relevant);
        return $prefix . ':' . md5(json_encode($relevant));
    }

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