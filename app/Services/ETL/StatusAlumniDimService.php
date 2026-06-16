<?php

namespace App\Services\ETL;

use App\Repositories\ETL\OlapLoadRepository;
use App\Repositories\ETL\OltpExtractRepository;

/**
 * ETL untuk dim_status_alumni. SCD Type 1 (overwrite + append pattern).
 * Business key id_status_alumni di-derive dari (question_code='f8',
 * option_code), lintas semua questionnaire_id.
 */
class StatusAlumniDimService
{
    private const QUESTION_CODE = 'f8';

    public function __construct(
        private readonly OltpExtractRepository $oltpRepo,
        private readonly OlapLoadRepository $olapRepo,
    ) {}

    /**
     * @param int[] $questionnaireIds questionnaire_id yang relevan di batch ini
     * @return array{processed:int, inserted:int, updated:int}
     */
    public function sync(array $questionnaireIds): array
    {
        $processed = 0;
        $inserted = 0;
        $updated = 0;

        foreach (array_unique($questionnaireIds) as $questionnaireId) {
            $options = $this->oltpRepo->getOptionsForQuestionnaire($questionnaireId)
                ->where('question_code', self::QUESTION_CODE);

            foreach ($options as $opt) {
                $processed++;

                $idStatusAlumni = $this->deriveBusinessKey(
                    $questionnaireId,
                    $opt->option_code
                );
                $existingSk = $this->olapRepo->getStatusAlumniSk($idStatusAlumni);

                $this->olapRepo->upsertStatusAlumni($idStatusAlumni, $opt->option_label);

                $existingSk === null ? $inserted++ : $updated++;
            }
        }

        return ['processed' => $processed, 'inserted' => $inserted, 'updated' => $updated];
    }

    public function resolveIdStatusAlumni(
        int $questionnaireId,
        string $rawOptionCode
    ): string {
        return $this->deriveBusinessKey(
            $questionnaireId,
            $rawOptionCode
        );
    }

    private function deriveBusinessKey(
        int $questionnaireId,
        string $optionCode
    ): string {
        return $questionnaireId . ':f8:' . $optionCode;
    }
}