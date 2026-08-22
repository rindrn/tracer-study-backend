<?php

namespace App\Services\Analytical;

use App\DTOs\Analytical\SebaranInstansi\SebaranInstansiJenisDTO;
use App\DTOs\Analytical\SebaranInstansi\SebaranInstansiTingkatDTO;
use App\DTOs\Analytical\SebaranInstansi\SebaranInstansiBandingkanDTO;
use App\DTOs\Analytical\SebaranInstansi\SebaranInstansiLokasiDTO;
use App\DTOs\Analytical\SebaranInstansi\SebaranInstansiDrillDownDTO;
use App\Repositories\Analytical\SebaranInstansiRepository;
use App\Traits\WithCache;
use Illuminate\Support\Collection;

class SebaranInstansiService
{
    use WithCache;

    /**
     * TTL dalam detik.
     * Samakan dengan interval ETL agar cache tidak stale lebih dari satu siklus.
     * Default: 1 jam. Ganti ke 3600 * 24 * 7 kalau ETL mingguan.
     */
    private const TTL = 3600;

    /**
     * DrillDown tidak di-cache — hasilnya sangat bervariasi
     * (page, search, filter per-request) dan jarang di-hit ulang
     * dengan parameter yang sama persis.
     */
    private const CACHE_DRILLDOWN = false;

    public function __construct(
        private readonly SebaranInstansiRepository $repo,
    ) {}

    // ──────────────────────────────────────────────────────────────

    public function getJenis(array $params): SebaranInstansiJenisDTO
    {
        $key = $this->key('sebaran_instansi:jenis', $params);

        $cached = $this->remember($key, function () use ($params) {
            $raw   = $this->repo->getJenisData(
                jenjang:        $params['jenjang']         ?? null,
                jurusan:        $params['jurusan']         ?? null,
                idProdiIn:      $params['id_prodi_in'] ?? null,
                namaProdi:      $params['nama_prodi']      ?? null,
                tahunLulus:     $params['tahun_lulus']     ?? null,
                mingguSnapshot: $params['minggu_snapshot'] ?? null,
            );

            $total = $raw->sum('count');

            return [
                'data'  => $raw->map(fn($r) => [
                    'jenis' => $r['jenis'],
                    'count' => $r['count'],
                    'pct'   => $total > 0 ? round($r['count'] / $total * 100, 1) : 0.0,
                ])->values()->toArray(),
                'total' => $total,
            ];
        }, self::TTL, ['analytics-dashboard']);

        return new SebaranInstansiJenisDTO(
            data:    $cached['data'],
            total:   $cached['total'],
            filters: $this->activeFilters($params),
        );
    }

    // ──────────────────────────────────────────────────────────────

    public function getTingkat(array $params): SebaranInstansiTingkatDTO
    {
        $key = $this->key('sebaran_instansi:tingkat', $params);

        $cached = $this->remember($key, function () use ($params) {
            $perProdiRaw = $this->repo->getTingkatData(
                jenjang:        $params['jenjang']         ?? null,
                jurusan:        $params['jurusan']         ?? null,
                idProdiIn:      $params['id_prodi_in'] ?? null,
                namaProdi:      $params['nama_prodi']      ?? null,
                tahunLulus:     $params['tahun_lulus']     ?? null,
                mingguSnapshot: $params['minggu_snapshot'] ?? null,
            );

            $perTahunRaw = $this->repo->getTingkatPerTahun(
                jenjang:        $params['jenjang']         ?? null,
                jurusan:        $params['jurusan']         ?? null,
                idProdiIn:      $params['id_prodi_in'] ?? null,
                namaProdi:      $params['nama_prodi']      ?? null,
                tahunLulus:     $params['tahun_lulus']     ?? null,
                mingguSnapshot: $params['minggu_snapshot'] ?? null,
            );

            $data = [];
            foreach ($perProdiRaw->groupBy('nama_prodi') as $namaProdi => $rows) {
                $first      = $rows->first();
                $totalProdi = $rows->sum('count');

                $data[] = [
                    'nama_prodi' => $namaProdi,
                    'jenjang'    => $first['jenjang'],
                    'tingkat'    => $this->mergeByLabel($rows, 'label_tingkat', $totalProdi),
                ];
            }

            $groupedBar = [];
            foreach ($perTahunRaw->groupBy('tahun_lulus') as $tahun => $rows) {
                $totalTahun    = $rows->sum('count');
                $countLokal    = $rows->firstWhere('label_tingkat', 'Lokal')['count']         ?? 0;
                $countNasional = $rows->firstWhere('label_tingkat', 'Nasional')['count']      ?? 0;
                $countInter    = $rows->firstWhere('label_tingkat', 'Internasional')['count'] ?? 0;

                $groupedBar[] = [
                    'tahun_lulus'       => $tahun,
                    'lokal'             => $countLokal,
                    'lokal_pct'         => $totalTahun > 0 ? round($countLokal    / $totalTahun * 100, 1) : 0.0,
                    'nasional'          => $countNasional,
                    'nasional_pct'      => $totalTahun > 0 ? round($countNasional / $totalTahun * 100, 1) : 0.0,
                    'internasional'     => $countInter,
                    'internasional_pct' => $totalTahun > 0 ? round($countInter    / $totalTahun * 100, 1) : 0.0,
                ];
            }

            return ['data' => $data, 'groupedBar' => $groupedBar];
        }, self::TTL, ['analytics-dashboard']);

        return new SebaranInstansiTingkatDTO(
            data:       $cached['data'],
            groupedBar: $cached['groupedBar'],
            filters:    $this->activeFilters($params),
        );
    }

    // ──────────────────────────────────────────────────────────────

    public function getBandingkan(array $params): SebaranInstansiBandingkanDTO
    {
        $key = $this->key('sebaran_instansi:bandingkan', $params);

        $cached = $this->remember($key, function () use ($params) {
            $prodiFilter = is_string($params['prodi'] ?? null)
                ? [$params['prodi']]
                : ($params['prodi'] ?? []);

            $jenisRaw   = $this->repo->getBandingkanJenis(
                prodiFilter:    $prodiFilter,
                jenjang:        $params['jenjang']         ?? null,
                jurusan:        $params['jurusan']         ?? null,
                idProdiIn:      $params['id_prodi_in'] ?? null,
                tahunLulus:     $params['tahun_lulus']     ?? null,
                mingguSnapshot: $params['minggu_snapshot'] ?? null,
            );

            $tingkatRaw = $this->repo->getBandingkanTingkat(
                prodiFilter:    $prodiFilter,
                jenjang:        $params['jenjang']         ?? null,
                jurusan:        $params['jurusan']         ?? null,
                idProdiIn:      $params['id_prodi_in'] ?? null,
                tahunLulus:     $params['tahun_lulus']     ?? null,
                mingguSnapshot: $params['minggu_snapshot'] ?? null,
            );

            $jenisGrouped   = $jenisRaw->groupBy('nama_prodi');
            $tingkatGrouped = $tingkatRaw->groupBy('nama_prodi');
            $allProdi       = $jenisGrouped->keys()->merge($tingkatGrouped->keys())->unique()->sort()->values();

            $data      = [];
            $prodiList = [];

            foreach ($allProdi as $namaProdi) {
                $jenisRows   = $jenisGrouped->get($namaProdi, collect());
                $tingkatRows = $tingkatGrouped->get($namaProdi, collect());
                $first       = $jenisRows->first() ?? $tingkatRows->first();
                $total       = max($jenisRows->sum('count'), $tingkatRows->sum('count'));

                $data[] = [
                    'nama_prodi' => $namaProdi,
                    'jenjang'    => $first['jenjang'] ?? '',
                    'total'      => $total,
                    'jenis'      => $this->mergeByLabel($jenisRows, 'label_jenis', $total),
                    'tingkat'    => $this->mergeByLabel($tingkatRows, 'label_tingkat', $total),
                ];

                $prodiList[] = $namaProdi;
            }

            return ['data' => $data, 'prodiList' => $prodiList];
        }, self::TTL, ['analytics-dashboard']);

        return new SebaranInstansiBandingkanDTO(
            data:      $cached['data'],
            prodiList: $cached['prodiList'],
            filters:   $this->activeFilters($params),
        );
    }

    // ──────────────────────────────────────────────────────────────

    public function getLokasi(array $params): SebaranInstansiLokasiDTO
    {
        $limit = min(50, max(5, (int) ($params['limit'] ?? 15)));
        $key   = $this->key('sebaran_instansi:lokasi', array_merge($params, ['limit' => $limit]));

        $cached = $this->remember($key, function () use ($params, $limit) {
            return [
                'topKota'     => $this->repo->getTopKota(
                    jenjang:        $params['jenjang']         ?? null,
                    jurusan:        $params['jurusan']         ?? null,
                    idProdiIn:      $params['id_prodi_in'] ?? null,
                    namaProdi:      $params['nama_prodi']      ?? null,
                    tahunLulus:     $params['tahun_lulus']     ?? null,
                    mingguSnapshot: $params['minggu_snapshot'] ?? null,
                    limit:          $limit,
                )->values()->toArray(),
                'topProvinsi' => $this->repo->getTopProvinsi(
                    jenjang:        $params['jenjang']         ?? null,
                    jurusan:        $params['jurusan']         ?? null,
                    idProdiIn:      $params['id_prodi_in'] ?? null,
                    namaProdi:      $params['nama_prodi']      ?? null,
                    tahunLulus:     $params['tahun_lulus']     ?? null,
                    mingguSnapshot: $params['minggu_snapshot'] ?? null,
                    limit:          $limit,
                )->values()->toArray(),
            ];
        }, self::TTL, ['analytics-dashboard']);

        return new SebaranInstansiLokasiDTO(
            topKota:     $cached['topKota'],
            topProvinsi: $cached['topProvinsi'],
            filters:     $this->activeFilters($params),
        );
    }

    // ──────────────────────────────────────────────────────────────
    // DrillDown tidak di-cache (terlalu variatif per request)
    // ──────────────────────────────────────────────────────────────

    public function getDrillDown(array $params): SebaranInstansiDrillDownDTO
    {
        $page    = max(1, (int) ($params['page']     ?? 1));
        $perPage = min(100, max(5, (int) ($params['per_page'] ?? 15)));

        // Parse jenis_instansi: bisa string CSV atau array
        $jenisInstansi = $params['jenis_instansi'] ?? null;
        if (is_string($jenisInstansi) && $jenisInstansi !== '') {
            $jenisInstansi = array_map('trim', explode(',', $jenisInstansi));
            $jenisInstansi = array_values(array_filter($jenisInstansi));
        }
        if (empty($jenisInstansi)) {
            $jenisInstansi = null;
        }

        $tingkatInstansi = $params['tingkat_instansi'] ?? null;
        if ($tingkatInstansi === '') $tingkatInstansi = null;

        $result = $this->repo->getDetailAlumni(
            jenisInstansi:   $jenisInstansi,
            tingkatInstansi: $tingkatInstansi,
            jenjang:         $params['jenjang']         ?? null,
            idProdiIn:       $params['id_prodi_in'] ?? null,
            namaProdi:       $params['nama_prodi']      ?? null,
            tahunLulus:      $params['tahun_lulus']     ?? null,
            mingguSnapshot:  $params['minggu_snapshot'] ?? null,
            search:          $params['search']          ?? null,
            page:            $page,
            perPage:         $perPage,
        );

        return new SebaranInstansiDrillDownDTO(
            data:            $result['data'],
            jenisInstansi: is_array($jenisInstansi) ? implode(', ', $jenisInstansi) : $jenisInstansi,
            tingkatInstansi: $tingkatInstansi ?: null,
            page:            $page,
            perPage:         $perPage,
            totalOnPage:     $result['total_on_page'],
            filters:         $this->activeFilters($params, [
                'jenjang', 'nama_prodi', 'tahun_lulus', 'minggu_snapshot',
            ]),
        );
    }

    // ──────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────

    /**
     * Buat cache key deterministik dari prefix + params.
     * Parameter di-sort supaya urutan berbeda tetap menghasilkan key yang sama.
     */
    private function key(string $prefix, array $params): string
    {
        // Buang key yang tidak relevan untuk cache (page, search, per_page)
        $relevant = array_diff_key($params, array_flip(['page', 'per_page', 'search']));
        ksort($relevant);
        return $prefix . ':' . md5(json_encode($relevant));
    }

    /**
     * Gabungkan baris yang punya label sama tapi terpisah karena grouping
     * di query (mis. nama_prodi yang sama dipakai 2 jenjang berbeda,
     * D3/D4, sehingga label instansi/tingkat muncul dobel per nama_prodi).
     * Tanpa ini FE menimpa (bukan menjumlahkan) baris berlabel sama saat
     * membangun row chart, hasilnya data hilang/kosong.
     */
    private function mergeByLabel(Collection $rows, string $labelKey, int $total): array
    {
        return $rows
            ->groupBy($labelKey)
            ->map(fn($group, $label) => [
                'label' => $label,
                'count' => $group->sum('count'),
                'pct'   => $total > 0 ? round($group->sum('count') / $total * 100, 1) : 0.0,
            ])
            ->sortByDesc('count')
            ->values()
            ->toArray();
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