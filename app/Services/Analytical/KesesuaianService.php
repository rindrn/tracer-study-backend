<?php

namespace App\Services\Analytical;

use App\DTOs\Analytical\Kesesuaian\KesesuaianBarDTO;
use App\DTOs\Analytical\Kesesuaian\KesesuaianPieDTO;
use App\DTOs\Analytical\Kesesuaian\KesesuaianAlasanDTO;
use App\DTOs\Analytical\Kesesuaian\KesesuaianDrillDownDTO;
use App\DTOs\Analytical\Kesesuaian\KesesuaianBandingkanDTO;
use App\Repositories\Analytical\KesesuaianRepository;
use App\Traits\WithCache;

class KesesuaianService
{
    use WithCache;

    private const TTL = 3600;

    public function __construct(
        private readonly KesesuaianRepository $repo,
    ) {}

    public function getBar(array $params): KesesuaianBarDTO
    {
        $key = $this->key('kesesuaian:bar', $params);

        $data = $this->remember($key, function () use ($params) {
            return $this->repo->getBarData(
                jenjang:        $params['jenjang']         ?? null,
                jurusan:        $params['jurusan']         ?? null,
                idProdiIn:      $params['id_prodi_in'] ?? null,
                namaProdi:      $params['nama_prodi']      ?? null,
                tahunLulus:     $params['tahun_lulus']     ?? null,
                mingguSnapshot: $params['minggu_snapshot'] ?? null,
            )->map(function ($r) {
                $total     = $r['count_alumni'];
                $pctSesuai = $total > 0 ? round($r['count_sesuai_bidang']        / $total * 100, 1) : 0.0;
                $pctTidak  = $total > 0 ? round($r['count_tidak_sesuai_bidang']  / $total * 100, 1) : 0.0;

                return [
                    'nama_prodi'                => $r['nama_prodi'],
                    'jenjang'                   => $r['jenjang'],
                    'tahun_lulus'               => $r['tahun_lulus'],
                    'count_alumni'              => $r['count_alumni'],
                    'count_sesuai_bidang'       => $r['count_sesuai_bidang'],
                    'count_tidak_sesuai_bidang' => $r['count_tidak_sesuai_bidang'],
                    'pct_sesuai'                => $pctSesuai,
                    'pct_tidak_sesuai'          => $pctTidak,
                ];
            })->values()->toArray();
        }, self::TTL, ['analytics-dashboard']);

        return new KesesuaianBarDTO(
            data:    $data,
            filters: $this->activeFilters($params),
        );
    }

    public function getPie(array $params): KesesuaianPieDTO
    {
        $key = $this->key('kesesuaian:pie', $params);

        $cached = $this->remember($key, function () use ($params) {
            $raw   = $this->repo->getPieData(
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
                    'label' => $r['label'],
                    'count' => $r['count'],
                    'pct'   => $total > 0 ? round($r['count'] / $total * 100, 1) : 0.0,
                ])->values()->toArray(),
                'total' => $total,
            ];
        }, self::TTL, ['analytics-dashboard']);

        return new KesesuaianPieDTO(
            data:    $cached['data'],
            total:   $cached['total'],
            filters: $this->activeFilters($params),
        );
    }

    public function getAlasan(array $params): KesesuaianAlasanDTO
    {
        $key = $this->key('kesesuaian:alasan', $params);

        $data = $this->remember($key, function () use ($params) {
            return $this->repo->getAlasanData(
                jenjang:        $params['jenjang']         ?? null,
                jurusan:        $params['jurusan']         ?? null,
                idProdiIn:      $params['id_prodi_in'] ?? null,
                namaProdi:      $params['nama_prodi']      ?? null,
                tahunLulus:     $params['tahun_lulus']     ?? null,
                mingguSnapshot: $params['minggu_snapshot'] ?? null,
            )->values()->toArray();
        }, self::TTL, ['analytics-dashboard']);

        return new KesesuaianAlasanDTO(
            data:    $data,
            filters: $this->activeFilters($params),
        );
    }

    public function getBandingkan(array $params): KesesuaianBandingkanDTO
    {
        $prodiFilter = $params['prodi'] ?? [];
        if (is_string($prodiFilter)) {
            $prodiFilter = [$prodiFilter];
        }

        $raw = $this->repo->getBandingkanData(
            prodiFilter:    $prodiFilter,
            jenjang:        $params['jenjang']         ?? null,
            jurusan:        $params['jurusan']         ?? null,
            idProdiIn:      $params['id_prodi_in'] ?? null,
            tahunLulus:     $params['tahun_lulus']     ?? null,
            mingguSnapshot: $params['minggu_snapshot'] ?? null,
        );

        $data      = [];
        $prodiList = [];

        foreach ($raw as $r) {
            $total    = $r['count_alumni'];
            $sesuai   = $r['count_sesuai_bidang'];
            $tidakSesuai = $r['count_tidak_sesuai_bidang'];

            $data[] = [
                'nama_prodi'               => $r['nama_prodi'],
                'jenjang'                  => $r['jenjang'],
                'total'                    => $total,
                'count_sesuai_bidang'      => $sesuai,
                'count_tidak_sesuai_bidang'=> $tidakSesuai,
                'pct_sesuai'               => $total > 0 ? round($sesuai      / $total * 100, 1) : 0.0,
                'pct_tidak_sesuai'         => $total > 0 ? round($tidakSesuai / $total * 100, 1) : 0.0,
            ];

            $prodiList[] = $r['nama_prodi'];
        }

        return new KesesuaianBandingkanDTO(
            data:      $data,
            prodiList: array_values(array_unique($prodiList)),
            filters:   $this->activeFilters($params),
        );
    }

    // ── DrillDown tidak di-cache ──────────────────────────────────

    public function getDrillDown(array $params): KesesuaianDrillDownDTO
    {
        $page    = max(1, (int) ($params['page']     ?? 1));
        $perPage = min(100, max(5, (int) ($params['per_page'] ?? 15)));

        $labelKesesuaian = null;
        $rawLabel        = $params['kesesuaian_label'] ?? null;

        if (!empty($rawLabel)) {
            if (is_string($rawLabel)) {
                $labelKesesuaian = array_values(array_filter(array_map('trim', explode(',', $rawLabel))));
            } elseif (is_array($rawLabel)) {
                $labelKesesuaian = array_values(array_filter(array_map('trim', $rawLabel)));
            }
            if (empty($labelKesesuaian)) $labelKesesuaian = null;
        }

        $labelPertanyaan = isset($params['label_pertanyaan']) && $params['label_pertanyaan'] !== ''
            ? $params['label_pertanyaan'] : null;

        if ($labelPertanyaan !== null) {
            $result = $this->repo->getDetailAlumniByAlasan(
                labelPertanyaan: $labelPertanyaan,
                jenjang:         $params['jenjang']         ?? null,
                idProdiIn:       $params['id_prodi_in'] ?? null,
                namaProdi:       $params['nama_prodi']      ?? null,
                tahunLulus:      $params['tahun_lulus']     ?? null,
                mingguSnapshot:  $params['minggu_snapshot'] ?? null,
                search:          $params['search']          ?? null,
                page:            $page,
                perPage:         $perPage,
            );

            return new KesesuaianDrillDownDTO(
                data:            $result['data'],
                kesesuaianSk:    null,
                kesesuaianLabel: null,
                labelPertanyaan: $labelPertanyaan,
                page:            $page,
                perPage:         $perPage,
                totalOnPage:     $result['total_on_page'],
                filters:         $this->activeFilters(
                    array_merge($params, ['label_pertanyaan' => $labelPertanyaan]),
                    ['label_pertanyaan', 'jenjang', 'nama_prodi', 'tahun_lulus', 'minggu_snapshot'],
                ),
            );
        }

        $result = $this->repo->getDetailAlumni(
            labelKesesuaian: $labelKesesuaian,
            jenjang:         $params['jenjang']         ?? null,
            idProdiIn:       $params['id_prodi_in'] ?? null,
            namaProdi:       $params['nama_prodi']      ?? null,
            tahunLulus:      $params['tahun_lulus']     ?? null,
            mingguSnapshot:  $params['minggu_snapshot'] ?? null,
            search:          $params['search']          ?? null,
            page:            $page,
            perPage:         $perPage,
        );

        return new KesesuaianDrillDownDTO(
            data:            $result['data'],
            kesesuaianSk:    null,
            kesesuaianLabel: is_array($labelKesesuaian)
                                ? implode(', ', $labelKesesuaian)
                                : $labelKesesuaian,
            labelPertanyaan: null,
            page:            $page,
            perPage:         $perPage,
            totalOnPage:     $result['total_on_page'],
            filters:         $this->activeFilters($params, [
                'jenjang', 'nama_prodi', 'tahun_lulus', 'minggu_snapshot',
            ]),
        );
    }

    // ── Helpers ───────────────────────────────────────────────────

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