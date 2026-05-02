<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Illuminate\Support\Collection;

class MinistrySheetExport implements FromCollection, WithHeadings, WithTitle
{
    protected $data;
    protected $questions;

    public function __construct(Collection $data, array $questions)
    {
        $this->data = $data;
        $this->questions = $questions; // List of question codes e.g. f8, f502
    }

    public function collection()
    {
        $exportData = [];

        foreach ($this->data as $alumni) {
            $row = [
                'NIM' => $alumni->nim,
                'Nama' => $alumni->name,
                'Email' => $alumni->email,
                'Telepon' => $alumni->phone,
                'Tahun Lulus' => $alumni->graduation_year,
                'NIK' => $alumni->nik,
                'NPWP' => $alumni->npwp,
            ];

            // Mapping jawaban berdasarkan urutan pertanyaan kementrian
            foreach ($this->questions as $code) {
                // Asumsi answers adalah dictionary key-value
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
            'Email',
            'Telepon',
            'Tahun Lulus',
            'NIK',
            'NPWP'
        ];

        // Header pertanyaan Kementrian (e.g. F8, F502, dll)
        foreach ($this->questions as $code) {
            $headers[] = strtoupper($code);
        }

        return $headers;
    }

    public function title(): string
    {
        return 'Data Kementrian';
    }
}
