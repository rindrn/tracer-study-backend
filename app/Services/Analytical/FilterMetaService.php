<?php

namespace App\Services\Analytical;

use App\DTOs\Analytical\Meta\FilterMetaDTO;
use App\Repositories\Analytical\FilterMetaRepository;

/**
 * FilterMetaService
 *
 * Fetch semua opsi filter global dalam satu batch.
 * Semua query dijalankan sekuensial — data kecil, tidak perlu async.
 *
 * Taruh di: app/Services/Analytical/FilterMetaService.php
 */
class FilterMetaService
{
    public function __construct(
        private readonly FilterMetaRepository $repo,
    ) {}

    public function getFilterOptions(): FilterMetaDTO
    {
        return new FilterMetaDTO(
            tahunLulus:     $this->repo->getTahunLulus(),
            snapshot:       $this->repo->getSnapshot(),
            jenjang:        $this->repo->getJenjang(),
            jurusan:        $this->repo->getJurusan(),
            prodi:          $this->repo->getProdi(),
        );
    }
}