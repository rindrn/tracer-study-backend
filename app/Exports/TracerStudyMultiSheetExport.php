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
    protected $prodiQuestionsGrouped;
    protected $optionsMap;
    protected $questionMeta;

    public function __construct(Collection $alumniData, array $ministryQuestions, array $prodiQuestionsGrouped, array $optionsMap = [], array $questionMeta = [])
    {
        $this->alumniData = $alumniData;
        $this->ministryQuestions = $ministryQuestions;
        $this->prodiQuestionsGrouped = $prodiQuestionsGrouped;
        $this->optionsMap = $optionsMap;
        $this->questionMeta = $questionMeta;
    }

    public function sheets(): array
    {
        $sheets = [];

        // Sheet 1: Data Kementrian (semua alumni)
        $sheets[] = new MinistrySheetExport($this->alumniData, $this->ministryQuestions, $this->optionsMap, $this->questionMeta);

        // Sheet 2-N: Satu sheet per prodi yang punya data
        foreach ($this->prodiQuestionsGrouped as $prodiCode => $prodiData) {
            $prodiAlumni = $prodiData['alumni'] ?? collect();
            $prodiQuestions = $prodiData['questions'] ?? [];

            if ($prodiAlumni->isNotEmpty() && !empty($prodiQuestions)) {
                $sheetTitle = "Pertanyaan Tambahan {$prodiCode}";
                $sheets[] = new ProdiSheetExport($prodiAlumni, $prodiQuestions, $sheetTitle, $this->optionsMap, $this->questionMeta);
            }
        }

        // Fallback: if no per-prodi sheets, add a single empty prodi sheet
        if (count($sheets) === 1) {
            $sheets[] = new ProdiSheetExport(collect(), [], 'Pertanyaan Tambahan');
        }

        return $sheets;
    }
}
