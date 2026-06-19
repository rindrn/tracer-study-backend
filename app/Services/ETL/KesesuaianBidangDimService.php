<?php

namespace App\Services\ETL;

use App\Repositories\ETL\OlapLoadRepository;
use App\Repositories\ETL\OltpExtractRepository;

/**
 * ETL untuk dim_kesesuaian_bidang. SCD Type 1 (overwrite + append
 * pattern) -- SAMA PERSIS pola StatusAlumniDimService, BUKAN hardcode
 * mapping manual seperti versi sebelumnya.
 *
 * Business key id_kesesuaian_bidang = "{questionnaire_id}:f14:{option_code}",
 * dipisah PER QUESTIONNAIRE (keputusan eksplisit user, sama alasannya
 * dengan dim_status_alumni): jika questionnaire baru mengubah skala
 * (misal jadi 1-7) atau redaksi label f14, opsi itu otomatis MASUK
 * sebagai baris baru -- BUKAN dianggap "tidak dikenal" lalu jatuh ke
 * sentinel "Tidak Ada Data". Ini memperbaiki risiko kehilangan data
 * alumni secara diam-diam yang melekat di pendekatan hardcode
 * sebelumnya (pendekatan label-as-business-key yang sudah DIHAPUS).
 *
 * Dijalankan SEBELUM fact dibangun, sama seperti StatusAlumniDimService,
 * supaya semua opsi f14 yang relevan di batch ini sudah ter-sync ke
 * dim sebelum AlumniFactBuilderService butuh resolve SK-nya.
 */
class KesesuaianBidangDimService
{
    private const QUESTION_CODE = 'f14';

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

                $idKesesuaianBidang = $this->deriveBusinessKey($questionnaireId, $opt->option_code);
                $existingSk = $this->olapRepo->getKesesuaianBidangSk($idKesesuaianBidang);

                $this->olapRepo->upsertKesesuaianBidang($idKesesuaianBidang, $opt->option_label);
                $existingSk === null ? $inserted++ : $updated++;
            }
        }

        return ['processed' => $processed, 'inserted' => $inserted, 'updated' => $updated];
    }

    /**
     * Resolve id_kesesuaian_bidang untuk satu jawaban f14 mentah, dipakai
     * oleh AlumniFactBuilderService saat membangun fact row. $rawOptionCode
     * adalah option_code MENTAH (sebelum di-resolve ke label), sama
     * pola resolveIdStatusAlumni().
     */
    public function resolveIdKesesuaianBidang(int $questionnaireId, string $rawOptionCode): string
    {
        return $this->deriveBusinessKey($questionnaireId, $rawOptionCode);
    }

    private function deriveBusinessKey(int $questionnaireId, string $optionCode): string
    {
        return "{$questionnaireId}:" . self::QUESTION_CODE . ":{$optionCode}";
    }
}