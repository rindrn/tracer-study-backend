<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use App\Exports\Sheets\MinistrySheetExport;
use App\Exports\Sheets\ProdiSheetExport;
use Illuminate\Support\Collection;

class TracerStudyMultiSheetExport implements WithMultipleSheets
{
    use Exportable;

    protected $alumniData;
    protected $ministryQuestions;
    protected $prodiQuestionsGrouped; // array keyed by prodi code

    /**
     * @param Collection $alumniData
     * @param array      $ministryQuestions  [['code' => ..., 'label' => ...], ...]
     * @param array      $prodiQuestionsGrouped  ['TI3' => ['name' => ..., 'questions' => [...], 'alumni' => Collection], ...]
     */
    public function __construct(Collection $alumniData, array $ministryQuestions, array $prodiQuestionsGrouped)
    {
        $this->alumniData = $alumniData;
        $this->ministryQuestions = $ministryQuestions;
        $this->prodiQuestionsGrouped = $prodiQuestionsGrouped;
    }

    public function sheets(): array
    {
        $sheets = [];

        // Sheet 1: Data Kementrian (semua alumni)
        $sheets[] = new MinistrySheetExport($this->alumniData, $this->ministryQuestions);

        // Sheet 2-N: Satu sheet per prodi yang punya data
        foreach ($this->prodiQuestionsGrouped as $prodiCode => $prodiData) {
            $prodiAlumni = $prodiData['alumni'] ?? collect();
            $prodiQuestions = $prodiData['questions'] ?? [];

            if ($prodiAlumni->isNotEmpty() && !empty($prodiQuestions)) {
                $sheetTitle = "Data Khusus {$prodiCode}";
                $sheets[] = new ProdiSheetExport($prodiAlumni, $prodiQuestions, $sheetTitle);
            }
        }

        // Fallback: if no per-prodi sheets, add a single empty prodi sheet
        if (count($sheets) === 1) {
            $sheets[] = new ProdiSheetExport(collect(), [], 'Data Khusus Prodi');
        }

        return $sheets;
    }
}
