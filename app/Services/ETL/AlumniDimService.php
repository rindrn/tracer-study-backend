<?php

namespace App\Services\ETL;

use App\Repositories\ETL\OlapLoadRepository;
use App\Repositories\ETL\OltpExtractRepository;

/**
 * ETL untuk dim_alumni. SCD Type 1 (overwrite).
 * Business key: nim (UNIQUE di OLTP alumni_profiles).
 */
class AlumniDimService
{
    public function __construct(
        private readonly OltpExtractRepository $oltpRepo,
        private readonly OlapLoadRepository $olapRepo,
    ) {}

    /**
     * @param int[] $alumniIds alumni_id (PK OLTP) yang muncul di batch ini
     * @return array{processed:int, inserted:int, updated:int}
     */
    public function sync(array $alumniIds): array
    {
        $alumniRows = $this->oltpRepo->getAlumniByIds(array_unique($alumniIds));

        $processed = 0;
        $inserted = 0;
        $updated = 0;

        foreach ($alumniRows as $alumni) {
            $processed++;

            $existingSk = $this->olapRepo->getAlumniSkByNim($alumni->nim);

            $this->olapRepo->upsertAlumni([
                'nim'                          => $alumni->nim,
                'nama'                         => $alumni->name,
                'tahun_lulus'                  => (string) $alumni->graduation_year,
                'label_sumber_biaya_dipolban'  => null, // diisi dari jawaban kuesioner terkait sumber biaya jika relevan
            ]);

            $existingSk === null ? $inserted++ : $updated++;
        }

        return ['processed' => $processed, 'inserted' => $inserted, 'updated' => $updated];
    }
}