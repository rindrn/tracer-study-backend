<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\Exportable;

class AlumniImportTemplateExport implements WithMultipleSheets
{
    use Exportable;

    public function sheets(): array
    {
        return [
            new Sheets\AlumniTemplateSheet(),
            new Sheets\ProdiReferensiSheet(),
        ];
    }
}
