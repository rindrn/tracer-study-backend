<?php

namespace App\Services\Analytical;

use App\DTOs\Analytical\Meta\FilterMetaDTO;
use App\Repositories\Analytical\FilterMetaRepository;
use App\Traits\WithCache;

/**
 * FilterMetaService
 *
 * Data filter meta (tahun lulus, snapshot, jenjang, jurusan, prodi)
 * sangat jarang berubah — hanya saat ETL jalan atau data baru masuk.
 * TTL di-set 24 jam supaya tidak sering hit Cube.js.
 */
class FilterMetaService
{
    use WithCache;

    // 24 jam — data meta berubah hanya saat ETL
    private const TTL = 3600 * 24;

    public function __construct(
        private readonly FilterMetaRepository $repo,
    ) {}

    public function getFilterOptions(): FilterMetaDTO
    {
        return $this->remember('filter_meta:options', function () {
            return new FilterMetaDTO(
                tahunLulus: $this->repo->getTahunLulus(),
                snapshot:   $this->repo->getSnapshot(),
                jenjang:    $this->repo->getJenjang(),
                jurusan:    $this->repo->getJurusan(),
                prodi:      $this->repo->getProdi(),
            );
        }, self::TTL, ['analytics-dashboard']);
    }
}