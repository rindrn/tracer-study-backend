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
    protected $prodiQuestions;

    public function __construct(Collection $alumniData, array $ministryQuestions, array $prodiQuestions)
    {
        $this->alumniData = $alumniData;
        $this->ministryQuestions = $ministryQuestions;
        $this->prodiQuestions = $prodiQuestions;
    }

    public function sheets(): array
    {
        return [
            new MinistrySheetExport($this->alumniData, $this->ministryQuestions),
            new ProdiSheetExport($this->alumniData, $this->prodiQuestions),
        ];
    }
}
