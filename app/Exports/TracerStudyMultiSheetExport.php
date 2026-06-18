<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use App\Exports\Sheets\MinistrySheetExport;
use App\Exports\Sheets\ProdiSheetExport;

/**
 * PERUBAHAN dari versi sebelumnya: constructor sekarang menerima
 * MinistrySheetExport yang SUDAH DIBANGUN oleh ReportService (lengkap
 * dengan query builder + dependency-nya), bukan Collection+array mentah
 * yang dibangun ulang di sini. Ini karena MinistrySheetExport sekarang
 * butuh dependency tambahan (ResponseRepository, QuestionnaireRepository)
 * untuk resolve jawaban per-chunk -- lebih bersih dirakit di Service
 * (yang sudah punya akses ke repository-repository itu) daripada di
 * kelas Export ini.
 *
 * Sheet per-prodi TETAP dirakit di sini dari $prodiQuestionsGrouped,
 * tidak berubah dari versi sebelumnya.
 */
class TracerStudyMultiSheetExport implements WithMultipleSheets
{
    use Exportable;

    protected MinistrySheetExport $ministrySheet;
    protected array $prodiQuestionsGrouped;

    public function __construct(MinistrySheetExport $ministrySheet, array $prodiQuestionsGrouped)
    {
        $this->ministrySheet = $ministrySheet;
        $this->prodiQuestionsGrouped = $prodiQuestionsGrouped;
    }

    public function sheets(): array
    {
        $sheets = [$this->ministrySheet];

        foreach ($this->prodiQuestionsGrouped as $prodiCode => $prodiData) {
            $prodiAlumni = $prodiData['alumni'] ?? collect();
            $prodiQuestions = $prodiData['questions'] ?? [];

            if ($prodiAlumni->isNotEmpty() && !empty($prodiQuestions)) {
                $sheetTitle = "Data Khusus {$prodiCode}";
                $sheets[] = new ProdiSheetExport($prodiAlumni, $prodiQuestions, $sheetTitle);
            }
        }

        if (count($sheets) === 1) {
            $sheets[] = new ProdiSheetExport(collect(), [], 'Data Khusus Prodi');
        }

        return $sheets;
    }
}