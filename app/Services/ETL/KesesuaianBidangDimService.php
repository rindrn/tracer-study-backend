<?php

namespace App\Services\ETL;

use App\Repositories\ETL\OlapLoadRepository;
use App\Repositories\ETL\OltpExtractRepository;
use App\Repositories\ETL\SemanticMappingRepository;
use Illuminate\Support\Facades\Log;

/**
 * ETL untuk dim_kesesuaian_bidang. SCD Type 1 (overwrite + append
 * pattern) -- SAMA PERSIS pola StatusAlumniDimService.
 *
 * Business key id_kesesuaian_bidang = "{questionnaire_id}:{question_code}:{option_code}",
 * dengan question_code SEKARANG di-resolve dinamis dari semantic_role
 * 'relevansi_bidang' via SemanticMappingRepository -- BUKAN hardcode 'f14'
 * lagi (const QUESTION_CODE lama sudah DIHAPUS). Tetap dipisah PER
 * QUESTIONNAIRE (keputusan eksplisit user, sudah ada sebelum perubahan
 * ini): jika questionnaire baru mengubah skala atau redaksi label opsi,
 * opsi itu otomatis MASUK sebagai baris baru -- BUKAN dianggap "tidak
 * dikenal" lalu jatuh ke sentinel "Tidak Ada Data".
 *
 * Dijalankan SEBELUM fact dibangun, sama seperti StatusAlumniDimService,
 * supaya semua opsi yang relevan di batch ini sudah ter-sync ke dim
 * sebelum AlumniFactBuilderService butuh resolve SK-nya.
 */
class KesesuaianBidangDimService
{
    private const ROLE_KEY = 'relevansi_bidang';

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

                $idKesesuaianBidang = $this->deriveBusinessKey($questionnaireId, $questionCode, $opt->option_code);
                $existingSk = $this->olapRepo->getKesesuaianBidangSk($idKesesuaianBidang);

                $this->olapRepo->upsertKesesuaianBidang($idKesesuaianBidang, $opt->option_label);
                $existingSk === null ? $inserted++ : $updated++;
            }
        }

        return ['processed' => $processed, 'inserted' => $inserted, 'updated' => $updated];
    }

    /**
     * Resolve id_kesesuaian_bidang untuk satu jawaban mentah, dipakai
     * AlumniFactBuilderService. $questionCode adalah kode AKTIF hasil
     * resolve role 'relevansi_bidang' untuk questionnaire ini (caller
     * sudah resolve lewat SemanticMappingRepository::questionCodeFor()).
     */
    public function resolveIdKesesuaianBidang(int $questionnaireId, string $questionCode, string $rawOptionCode): string
    {
        return $this->deriveBusinessKey($questionnaireId, $questionCode, $rawOptionCode);
    }

    private function deriveBusinessKey(int $questionnaireId, string $questionCode, string $optionCode): string
    {
        return "{$questionnaireId}:{$questionCode}:{$optionCode}";
    }
}
