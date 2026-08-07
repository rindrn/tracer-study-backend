<?php

namespace App\Services\ETL;

use App\Repositories\ETL\OlapLoadRepository;
use App\Repositories\ETL\OltpExtractRepository;

/**
 * ETL untuk dim_prodi. SCD Type 2 (terkonfirmasi: ada valid_from,
 * valid_to, flag_prodi di schema asli).
 */
class ProdiDimService
{
    public function __construct(
        private readonly OltpExtractRepository $oltpRepo,
        private readonly OlapLoadRepository $olapRepo,
    ) {}

    /**
     * @return array{processed:int, inserted:int, updated:int}
     */
    public function sync(\DateTimeInterface $snapshotDate): array
    {
        $programs = $this->oltpRepo->getAllPrograms();

        $processed = 0;
        $inserted = 0;
        $updated = 0;

        foreach ($programs as $program) {
            $processed++;

            $active = $this->olapRepo->getActiveProdiVersion($program->id);

            $newAttributes = [
                'id_prodi'   => $program->id,
                'kode_prodi' => $program->code,
                'nama_prodi' => $program->name,
                'jurusan'    => $program->jurusan,
                'jenjang'    => $program->degree,
            ];

            if ($active === null) {
                $this->olapRepo->insertNewProdiVersion($newAttributes, $snapshotDate);
                $inserted++;
                continue;
            }

            $hasChanged = $active->kode_prodi !== $newAttributes['kode_prodi']
                || $active->nama_prodi !== $newAttributes['nama_prodi']
                || $active->jurusan !== $newAttributes['jurusan']
                || $active->jenjang !== $newAttributes['jenjang'];

            if ($hasChanged) {
                $this->olapRepo->closeProdiVersion($active->prodi_sk, $snapshotDate);
                $this->olapRepo->insertNewProdiVersion($newAttributes, $snapshotDate);
                $inserted++;
            }
        }

        return ['processed' => $processed, 'inserted' => $inserted, 'updated' => $updated];
    }
}