<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Illuminate\Support\Collection;

class ProdiSheetExport implements FromCollection, WithHeadings, WithTitle, WithStyles
{
    protected $data;
    protected $questions; // Array of ['code' => ..., 'label' => ...]
    protected $sheetTitle;

    public function __construct(Collection $data, array $questions, string $sheetTitle = 'Data Khusus Prodi')
    {
        $this->data = $data;
        $this->questions = $questions;
        $this->sheetTitle = $sheetTitle;
    }

    public function collection()
    {
        $exportData = [];

        foreach ($this->data as $alumni) {
            $row = [
                'NIM'           => $alumni->nim,
                'Nama'          => $alumni->name,
                'Program Studi' => $alumni->program_name ?? '-',
            ];

            // Mapping jawaban berdasarkan urutan pertanyaan lokal/prodi
            foreach ($this->questions as $q) {
                $code = $q['code'];
                $row[$q['label']] = $alumni->answers[$code] ?? '-';
            }

            $exportData[] = $row;
        }

        return collect($exportData);
    }

    public function headings(): array
    {
        $headers = [
            'NIM',
            'Nama',
            'Program Studi',
        ];

        foreach ($this->questions as $q) {
            $headers[] = $q['label'];
        }

        return $headers;
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('1:1')->getFont()->setBold(true);
        $sheet->freezePane('A2');

        return [];
    }

    public function title(): string
    {
        // Excel sheet names max 31 chars
        return mb_substr($this->sheetTitle, 0, 31);
    }
}
