<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Illuminate\Support\Collection;

class ProdiSheetExport implements FromCollection, WithHeadings, WithTitle
{
    protected $data;
    protected $questions;

    public function __construct(Collection $data, array $questions)
    {
        $this->data = $data;
        $this->questions = $questions; // Array of Custom Question Codes
    }

    public function collection()
    {
        $exportData = [];

        foreach ($this->data as $alumni) {
            $row = [
                'NIM' => $alumni->nim,
                'Nama' => $alumni->name,
            ];

            // Mapping jawaban berdasarkan urutan pertanyaan lokal/prodi
            foreach ($this->questions as $code) {
                $row[$code] = $alumni->answers[$code] ?? '-';
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
        ];

        foreach ($this->questions as $code) {
            $headers[] = $code;
        }

        return $headers;
    }

    public function title(): string
    {
        return 'Data Khusus Prodi';
    }
}
