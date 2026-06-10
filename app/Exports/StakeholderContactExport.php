<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Illuminate\Support\Collection;

class StakeholderContactExport implements FromCollection, WithHeadings, WithStyles, WithColumnWidths
{
    public function __construct(private Collection $data) {}

    public function collection()
    {
        return $this->data->map(fn ($r) => [
            'NIM' => "'" . $r->nim,
            'Nama Alumni' => $r->alumni_name,
            'Tahun Lulus' => $r->graduation_year,
            'Tipe Kontak' => $r->contact_type,
            'Nama Kontak' => $r->contact_name,
            'Email Kontak' => $r->contact_email,
            'Status Alumni' => $r->alumni_status,
        ]);
    }

    public function headings(): array
    {
        return ['NIM', 'Nama Alumni', 'Tahun Lulus', 'Tipe Kontak', 'Nama Kontak', 'Email Kontak', 'Status Alumni'];
    }

    public function columnWidths(): array
    {
        return ['A' => 20, 'B' => 25, 'C' => 12, 'D' => 15, 'E' => 25, 'F' => 30, 'G' => 15];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('1:1')->getFont()->setBold(true);
        $sheet->freezePane('A2');
        return [];
    }
}
