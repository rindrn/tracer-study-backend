<?php

namespace App\Services\ETL;

use App\Repositories\ETL\OlapLoadRepository;
use App\Repositories\ETL\OltpExtractRepository;

/**
 * ETL untuk dim_ump. SCD Type 1 (overwrite) -- tidak ada valid_from/to
 * di schema, dan secara semantik nilai UMP per tahun memang nilai final
 * (tidak ada "versi" UMP untuk tahun yang sama).
 *
 * Business key: id_ump = tracer_oltp.ref_ump.id (1:1 mapping langsung,
 * karena ref_ump sudah punya UNIQUE constraint pada (tahun, province_id)
 * yang menjamin tidak ada duplikat makna).
 *
 * Sumber data ref_ump sendiri berasal dari fitur UMP management yang
 * sudah ada (Laravel service fetch BPS API + import Excel/CSV) --
 * ETL ini HANYA membaca ref_ump yang sudah final, tidak memanggil BPS
 * API langsung.
 */
class UmpDimService
{
    public function __construct(
        private readonly OltpExtractRepository $oltpRepo,
        private readonly OlapLoadRepository $olapRepo,
    ) {}

    /**
     * @return array{processed:int, inserted:int, updated:int}
     */
    public function sync(): array
    {
        $umpRows = $this->oltpRepo->getAllUmp();

        $processed = 0;
        $inserted = 0;
        $updated = 0;

        foreach ($umpRows as $ump) {
            $processed++;

            $existingSk = $this->olapRepo->getUmpSk($ump->id);

            $this->olapRepo->upsertUmp(
                idUmp: $ump->id,
                tahun: (string) $ump->tahun,
                namaProvinsi: $ump->nama_provinsi,
                nilaiUmp: (float) $ump->nilai_ump,
            );

            $existingSk === null ? $inserted++ : $updated++;
        }

        return ['processed' => $processed, 'inserted' => $inserted, 'updated' => $updated];
    }
}