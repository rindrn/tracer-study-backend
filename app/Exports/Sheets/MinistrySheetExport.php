<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Illuminate\Support\Collection;

class MinistrySheetExport implements FromCollection, WithHeadings, WithTitle, WithStyles
{
    protected $data;
    protected $questions; // Array of ['code' => ..., 'label' => ...]

    public function __construct(Collection $data, array $questions)
    {
        $this->data = $data;
        $this->questions = $questions;
    }

    public function collection()
    {
        $exportData = [];

        foreach ($this->data as $alumni) {
            $row = [
                'NIM'           => $alumni->nim,
                'Nama'          => $alumni->name,
                'Email'         => $alumni->email,
                'Telepon'       => $alumni->phone,
                'Tahun Lulus'   => $alumni->graduation_year,
                'Program Studi' => $alumni->program_name ?? '-',
                'Jurusan'       => $alumni->jurusan_name ?? '-',
                'NIK'           => $alumni->nik,
                'NPWP'          => $alumni->npwp,
            ];

            // Mapping jawaban berdasarkan urutan pertanyaan kementrian
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
            'Email',
            'Telepon',
            'Tahun Lulus',
            'Program Studi',
            'Jurusan',
            'NIK',
            'NPWP',
        ];

        // Header pertanyaan Kementrian — sekarang pakai teks lengkap + kode
        foreach ($this->questions as $q) {
            $headers[] = $q['label'];
        }

        return $headers;
    }

    public function styles(Worksheet $sheet)
    {
        // Bold header row + freeze pane
        $sheet->getStyle('1:1')->getFont()->setBold(true);
        $sheet->freezePane('A2');

        return [];
    }

    public function title(): string
    {
        return 'Data Kementrian';
    }
}
