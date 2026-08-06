<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use App\Exports\Sheets\MinistrySheetExport;
use App\Exports\Sheets\ProdiSheetExport;
use App\Exports\Support\AnswerValueResolver;

/**
 * Merakit sheet-sheet workbook export.
 *
 * CAKUPAN SHEET mengikuti kuesioner yang diekspor, ditentukan di
 * ReportService:
 *   - kuesioner global (Kementerian) -> HANYA sheet "Data Kementrian"
 *     ($ministrySheet terisi, $prodiQuestionsGrouped kosong)
 *   - kuesioner tambahan prodi       -> HANYA sheet "Data Khusus {PRODI}"
 *     ($ministrySheet null)
 *   - tanpa questionnaire_id         -> keduanya, seperti perilaku lama
 *
 * Sebelumnya workbook SELALU berisi sheet Kementrian + sheet SEMUA prodi
 * apa pun kuesioner yang diklik, sehingga export "Kuesioner Tambahan
 * Prodi X" ikut membawa data kementerian dan data prodi lain.
 */
class TracerStudyMultiSheetExport implements WithMultipleSheets
{
    use Exportable;

    public function __construct(
        protected ?MinistrySheetExport $ministrySheet,
        protected array $prodiQuestionsGrouped,
        protected ?AnswerValueResolver $prodiValueResolver = null,
    ) {}

    public function sheets(): array
    {
        $sheets = [];

        if ($this->ministrySheet !== null) {
            $sheets[] = $this->ministrySheet;
        }

        foreach ($this->prodiQuestionsGrouped as $prodiCode => $prodiData) {
            $prodiAlumni = $prodiData['alumni'] ?? collect();
            $prodiQuestions = $prodiData['questions'] ?? [];

            if ($prodiAlumni->isNotEmpty() && !empty($prodiQuestions)) {
                $sheets[] = new ProdiSheetExport(
                    $prodiAlumni,
                    $prodiQuestions,
                    "Data Khusus {$prodiCode}",
                    $this->prodiValueResolver,
                );
            }
        }

        // Workbook tanpa sheet sama sekali ditolak PhpSpreadsheet, jadi
        // sediakan satu sheet kosong berisi header saja.
        if ($sheets === []) {
            $sheets[] = new ProdiSheetExport(collect(), [], 'Data Kosong');
        }

        return $sheets;
    }
}
