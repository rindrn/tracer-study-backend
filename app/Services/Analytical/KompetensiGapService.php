<?php

namespace App\Services\Analytical;

use App\DTOs\Analytical\KompetensiGap\KompetensiGapDTO;
use App\DTOs\Analytical\KompetensiGap\KompetensiGapBandingkanDTO;
use App\DTOs\Analytical\KompetensiGap\KompetensiGapDrillDownDTO;
use App\Repositories\Analytical\KompetensiGapRepository;
use App\Traits\WithCache;
use Illuminate\Support\Collection;

class KompetensiGapService
{
    use WithCache;

    private const TTL = 3600;

    public function __construct(
        private readonly KompetensiGapRepository $repo,
    ) {}

    public function getGap(array $params): KompetensiGapDTO
    {
        $key = $this->key('kompetensi_gap:gap', $params);

        $data = $this->remember($key, function () use ($params) {
            $raw = $this->repo->getGapData(
                jenjang:        $params['jenjang']         ?? null,
                jurusan:        $params['jurusan']         ?? null,
                namaProdi:      $params['nama_prodi']      ?? null,
                tahunLulus:     $params['tahun_lulus']     ?? null,
                mingguSnapshot: $params['minggu_snapshot'] ?? null,
            );
            return $this->joinKategori($raw)->values()->toArray();
        }, self::TTL, ['analytics-dashboard']);

        return new KompetensiGapDTO(
            data:    $data,
            filters: $this->activeFilters($params),
        );
    }

    public function getBandingkan(array $params): KompetensiGapBandingkanDTO
    {
        $key = $this->key('kompetensi_gap:bandingkan', $params);

        $cached = $this->remember($key, function () use ($params) {
            $prodiFilter = is_string($params['prodi'] ?? null)
                ? [$params['prodi']]
                : ($params['prodi'] ?? []);

            $raw       = $this->repo->getBandingkanData(
                prodiFilter:    $prodiFilter,
                jenjang:        $params['jenjang']         ?? null,
                jurusan:        $params['jurusan']         ?? null,
                tahunLulus:     $params['tahun_lulus']     ?? null,
                mingguSnapshot: $params['minggu_snapshot'] ?? null,
            );

            $data      = [];
            $prodiList = [];

            foreach ($raw->groupBy('nama_prodi') as $namaProdi => $rows) {
                $first = $rows->first();
                $data[] = [
                    'nama_prodi' => $namaProdi,
                    'jenjang'    => $first['jenjang'],
                    'indikator'  => $this->joinKategori($rows)->values()->toArray(),
                ];
                $prodiList[] = $namaProdi;
            }

            return ['data' => $data, 'prodiList' => array_values(array_unique($prodiList))];
        }, self::TTL, ['analytics-dashboard']);

        return new KompetensiGapBandingkanDTO(
            data:      $cached['data'],
            prodiList: $cached['prodiList'],
            filters:   $this->activeFilters($params),
        );
    }

    // ── DrillDown tidak di-cache ──────────────────────────────────

    public function getDrillDown(array $params): KompetensiGapDrillDownDTO
    {
        $page    = max(1, (int) ($params['page']     ?? 1));
        $perPage = min(100, max(5, (int) ($params['per_page'] ?? 15)));
        $grupGap = $params['grup_gap'] ?? '';

        $result = $this->repo->getDetailAlumni(
            grupGap:        $grupGap,
            jenjang:        $params['jenjang']         ?? null,
            jurusan:        $params['jurusan']         ?? null,
            namaProdi:      $params['nama_prodi']      ?? null,
            tahunLulus:     $params['tahun_lulus']     ?? null,
            mingguSnapshot: $params['minggu_snapshot'] ?? null,
            search:         $params['search']          ?? null,
            page:           $page,
            perPage:        $perPage,
        );

        return new KompetensiGapDrillDownDTO(
            data:        $result['data'],
            page:        $page,
            perPage:     $perPage,
            totalOnPage: $result['total_on_page'],
            filters:     $this->activeFilters(
                array_merge($params, ['grup_gap' => $grupGap]),
                ['grup_gap', 'jenjang', 'jurusan', 'nama_prodi', 'tahun_lulus', 'minggu_snapshot'],
            ),
        );
    }

    // ── Helpers ───────────────────────────────────────────────────

    private function joinKategori(Collection $rows): Collection
    {
        return $rows->groupBy('grup_gap')->map(function (Collection $items, string $grupGap) {
            $rowA = $items->firstWhere('kategori', 'Kompetensi_A');
            $rowB = $items->firstWhere('kategori', 'Kompetensi_B');

            $label     = $rowA['label']     ?? $rowB['label']     ?? '';
            $kodeField = $rowA['kode_field'] ?? $rowB['kode_field'] ?? '';

            $skorLulus      = isset($rowA) ? round($rowA['avg_skor'], 2) : null;
            $skorDibutuhkan = isset($rowB) ? round($rowB['avg_skor'], 2) : null;
            $gap            = (isset($rowA) && isset($rowB))
                ? round($skorDibutuhkan - $skorLulus, 2)
                : null;

            return [
                'kode_field'      => $kodeField,
                'grup_gap'        => $grupGap,
                'label'           => $label,
                'skor_lulus'      => $skorLulus,
                'skor_dibutuhkan' => $skorDibutuhkan,
                'gap'             => $gap,
                'count_responden' => $rowA['count'] ?? $rowB['count'] ?? 0,
            ];
        });
    }

    private function key(string $prefix, array $params): string
    {
        $relevant = array_diff_key($params, array_flip(['page', 'per_page', 'search']));
        ksort($relevant);
        return $prefix . ':' . md5(json_encode($relevant));
    }

    private function activeFilters(array $params, array $keys = []): array
    {
        $allKeys = ['jenjang', 'jurusan', 'nama_prodi', 'tahun_lulus', 'minggu_snapshot'];
        $keys    = empty($keys) ? $allKeys : $keys;
        return array_filter(
            array_intersect_key($params, array_flip($keys)),
            fn($v) => $v !== null && $v !== '' && $v !== [],
        );
    }
}