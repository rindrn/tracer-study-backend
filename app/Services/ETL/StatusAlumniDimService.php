<?php

namespace App\Services\ETL;

use App\Repositories\ETL\OlapLoadRepository;
use App\Repositories\ETL\OltpExtractRepository;
use App\Repositories\ETL\SemanticMappingRepository;
use Illuminate\Support\Facades\Log;

/**
 * ETL untuk dim_status_alumni. SCD Type 1 (overwrite + append pattern).
 * Business key id_status_alumni di-derive dari (question_code, option_code),
 * di mana question_code SEKARANG di-resolve dinamis dari semantic_role
 * 'status_pekerjaan' via SemanticMappingRepository -- BUKAN hardcode 'f8'
 * lagi (const QUESTION_CODE lama sudah DIHAPUS).
 */
class StatusAlumniDimService
{
    private const ROLE_KEY = 'status_pekerjaan';

    public function __construct(
        private readonly OltpExtractRepository $oltpRepo,
        private readonly OlapLoadRepository $olapRepo,
        private readonly SemanticMappingRepository $semanticRepo,
    ) {}

    /**
     * @param int[] $questionnaireIds questionnaire_id yang relevan di batch ini
     * @return array{processed:int, inserted:int, updated:int}
     */
    public function sync(array $questionnaireIds, ?string $etlRunId = null): array
    {
        $processed = 0;
        $inserted = 0;
        $updated = 0;

        foreach (array_unique($questionnaireIds) as $questionnaireId) {
            $questionCode = $this->semanticRepo->questionCodeFor($questionnaireId, self::ROLE_KEY);

            if ($questionCode === null) {
                // Soft-fail (posisi yang sama dipakai di seluruh codebase
                // ini): tidak ada mapping AKTIF untuk role ini di
                // questionnaire ini -- lewati questionnaire tsb SAJA,
                // jangan hentikan seluruh run ETL.
                Log::warning('SemanticMapping: tidak ada question_code aktif untuk role narrow, questionnaire dilewati.', [
                    'role'             => self::ROLE_KEY,
                    'questionnaire_id' => $questionnaireId,
                    'etl_run_id'       => $etlRunId,
                ]);
                continue;
            }

            $options = $this->oltpRepo->getOptionsForQuestionnaire($questionnaireId)
                ->where('question_code', $questionCode);

            foreach ($options as $opt) {
                $processed++;

                $idStatusAlumni = $this->deriveBusinessKey($questionnaireId, $questionCode, $opt->option_code);
                $existingSk = $this->olapRepo->getStatusAlumniSk($idStatusAlumni);

                $this->olapRepo->upsertStatusAlumni($idStatusAlumni, $opt->option_label);

                $existingSk === null ? $inserted++ : $updated++;
            }
        }

        return ['processed' => $processed, 'inserted' => $inserted, 'updated' => $updated];
    }

    /**
     * Resolve id_status_alumni untuk satu jawaban mentah, dipakai
     * AlumniFactBuilderService. $questionCode adalah kode AKTIF hasil
     * resolve role 'status_pekerjaan' untuk questionnaire ini (caller sudah
     * resolve lewat SemanticMappingRepository::questionCodeFor(), supaya
     * tidak query dua kali untuk hal yang sama).
     */
    public function resolveIdStatusAlumni(int $questionnaireId, string $questionCode, string $rawOptionCode): string
    {
        return $this->deriveBusinessKey($questionnaireId, $questionCode, $rawOptionCode);
    }

    private function deriveBusinessKey(int $questionnaireId, string $questionCode, string $optionCode): string
    {
        return $questionnaireId . ':' . $questionCode . ':' . $optionCode;
    }
}
