<?php

namespace App\Services\ETL;

use App\Repositories\ETL\OlapLoadRepository;
use App\Repositories\ETL\OltpExtractRepository;
use App\Repositories\ETL\SemanticMappingRepository;
use Illuminate\Support\Facades\Log;

/**
 * ETL untuk dim_kesesuaian_level. SCD Type 1 (overwrite + append
 * pattern) -- SAMA PERSIS pola StatusAlumniDimService.
 *
 * Business key id_kesesuaian_level = "{questionnaire_id}:{question_code}:{option_code}",
 * dengan question_code SEKARANG di-resolve dinamis dari semantic_role
 * 'kesesuaian_level' via SemanticMappingRepository -- BUKAN hardcode 'f15'
 * lagi (const QUESTION_CODE lama sudah DIHAPUS). Tetap dipisah PER
 * QUESTIONNAIRE, alasan sama dengan KesesuaianBidangDimService.
 *
 * Dijalankan SEBELUM fact dibangun, sama seperti StatusAlumniDimService,
 * supaya semua opsi yang relevan di batch ini sudah ter-sync ke dim
 * sebelum AlumniFactBuilderService butuh resolve SK-nya.
 */
class KesesuaianLevelDimService
{
    private const ROLE_KEY = 'kesesuaian_level';

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

                $idKesesuaianLevel = $this->deriveBusinessKey($questionnaireId, $questionCode, $opt->option_code);
                $existingSk = $this->olapRepo->getKesesuaianLevelSk($idKesesuaianLevel);

                $this->olapRepo->upsertKesesuaianLevel($idKesesuaianLevel, $opt->option_label);

                $existingSk === null ? $inserted++ : $updated++;
            }
        }

        return ['processed' => $processed, 'inserted' => $inserted, 'updated' => $updated];
    }

    /**
     * Resolve id_kesesuaian_level untuk satu jawaban mentah, dipakai
     * AlumniFactBuilderService. $questionCode adalah kode AKTIF hasil
     * resolve role 'kesesuaian_level' untuk questionnaire ini (caller
     * sudah resolve lewat SemanticMappingRepository::questionCodeFor()).
     */
    public function resolveIdKesesuaianLevel(int $questionnaireId, string $questionCode, string $rawOptionCode): string
    {
        return $this->deriveBusinessKey($questionnaireId, $questionCode, $rawOptionCode);
    }

    private function deriveBusinessKey(int $questionnaireId, string $questionCode, string $optionCode): string
    {
        return "{$questionnaireId}:{$questionCode}:{$optionCode}";
    }
}
