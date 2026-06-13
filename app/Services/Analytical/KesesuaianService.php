<?php

namespace App\Services\Analytical;

use App\DTOs\Analytical\Kesesuaian\KesesuaianBarDTO;
use App\DTOs\Analytical\Kesesuaian\KesesuaianPieDTO;
use App\DTOs\Analytical\Kesesuaian\KesesuaianAlasanDTO;
use App\DTOs\Analytical\Kesesuaian\KesesuaianDrillDownDTO;
use App\Repositories\Analytical\KesesuaianRepository;

class KesesuaianService
{
    public function __construct(
        private readonly KesesuaianRepository $repo,
    ) {}

    public function getBar(array $params): KesesuaianBarDTO
    {
        $raw = $this->repo->getBarData(
            jenjang:        $params['jenjang']         ?? null,
            jurusan:        $params['jurusan']         ?? null,
            namaProdi:      $params['nama_prodi']      ?? null,
            tahunLulus:     $params['tahun_lulus']     ?? null,
            mingguSnapshot: $params['minggu_snapshot'] ?? null,
        );

        $data = $raw->map(function ($r) {
            $total        = $r['count_alumni'];
            $pctSesuai    = $total > 0 ? round($r['count_sesuai_bidang']       / $total * 100, 1) : 0.0;
            $pctTidak     = $total > 0 ? round($r['count_tidak_sesuai_bidang'] / $total * 100, 1) : 0.0;

            return [
                'nama_prodi'               => $r['nama_prodi'],
                'jenjang'                  => $r['jenjang'],
                'tahun_lulus'              => $r['tahun_lulus'],
                'count_alumni'             => $r['count_alumni'],
                'count_sesuai_bidang'      => $r['count_sesuai_bidang'],
                'count_tidak_sesuai_bidang'=> $r['count_tidak_sesuai_bidang'],
                'pct_sesuai'               => $pctSesuai,
                'pct_tidak_sesuai'         => $pctTidak,
            ];
        })->values()->toArray();

        return new KesesuaianBarDTO(
            data:    $data,
            filters: $this->activeFilters($params),
        );
    }

    public function getPie(array $params): KesesuaianPieDTO
    {
        $raw = $this->repo->getPieData(
            jenjang:        $params['jenjang']         ?? null,
            jurusan:        $params['jurusan']         ?? null,
            namaProdi:      $params['nama_prodi']      ?? null,
            tahunLulus:     $params['tahun_lulus']     ?? null,
            mingguSnapshot: $params['minggu_snapshot'] ?? null,
        );

        $total = $raw->sum('count');

        $data = $raw->map(fn($r) => [
            'label' => $r['label'],
            'count' => $r['count'],
            'pct'   => $total > 0 ? round($r['count'] / $total * 100, 1) : 0.0,
        ])->values()->toArray();

        return new KesesuaianPieDTO(
            data:    $data,
            total:   $total,
            filters: $this->activeFilters($params),
        );
    }

    public function getAlasan(array $params): KesesuaianAlasanDTO
    {
        $raw = $this->repo->getAlasanData(
            jenjang:        $params['jenjang']         ?? null,
            jurusan:        $params['jurusan']         ?? null,
            namaProdi:      $params['nama_prodi']      ?? null,
            tahunLulus:     $params['tahun_lulus']     ?? null,
            mingguSnapshot: $params['minggu_snapshot'] ?? null,
        );

        return new KesesuaianAlasanDTO(
            data:    $raw->values()->toArray(),
            filters: $this->activeFilters($params),
        );
    }

    public function getDrillDown(array $params): KesesuaianDrillDownDTO
    {
        $page         = max(1, (int) ($params['page']          ?? 1));
        $perPage      = min(100, max(5, (int) ($params['per_page'] ?? 15)));
        $kesesuaianSk = (int) $params['kesesuaian_sk'];

        $result = $this->repo->getDetailAlumni(
            kesesuaianSk:   $kesesuaianSk,
            jenjang:        $params['jenjang']         ?? null,
            namaProdi:      $params['nama_prodi']      ?? null,
            tahunLulus:     $params['tahun_lulus']     ?? null,
            mingguSnapshot: $params['minggu_snapshot'] ?? null,
            search:         $params['search']          ?? null,
            page:           $page,
            perPage:        $perPage,
        );

        return new KesesuaianDrillDownDTO(
            data:            $result['data'],
            kesesuaianSk:    $kesesuaianSk,
            kesesuaianLabel: $this->skToLabel($kesesuaianSk),
            page:            $page,
            perPage:         $perPage,
            totalOnPage:     $result['total_on_page'],
            filters:         $this->activeFilters($params, [
                'jenjang', 'nama_prodi', 'tahun_lulus', 'minggu_snapshot',
            ]),
        );
    }

    private function skToLabel(int $sk): string
    {
        return match ($sk) {
            1 => 'Sangat Erat',
            2 => 'Erat',
            3 => 'Cukup Erat',
            4 => 'Kurang Erat',
            5 => 'Tidak Sama Sekali',
            default => 'Tidak Diketahui',
        };
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