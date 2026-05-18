<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\Exportable;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

/**
 * Template Excel kosong untuk import data alumni oleh admin / kepala tracer.
 *
 * File hasil berisi hanya header + satu baris contoh (optional) supaya pengguna
 * tahu format yang diharapkan. Upload + parse akan diimplementasikan di round
 * berikutnya — template ini cukup sebagai referensi format.
 *
 * Header kolom sesuai spesifikasi stakeholder:
 *   NIM | Nama | Email | Telepon | Tahun Lulus | Program Studi | Jurusan | Status
 */
class AlumniImportTemplateExport implements
    FromCollection,
    WithHeadings,
    WithTitle,
    WithStyles,
    WithColumnWidths
{
    use Exportable;

    /** Tidak ada data rows — template kosong, cukup header. */
    public function collection()
    {
        return collect([]);
    }

    public function headings(): array
    {
        return [
            'NIM',
            'Nama',
            'Email',
            'Telepon',
            'Tahun Lulus',
            'Program Studi',
            'Jurusan',
            'Status',
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 20, // NIM
            'B' => 32, // Nama
            'C' => 32, // Email
            'D' => 18, // Telepon
            'E' => 14, // Tahun Lulus
            'F' => 32, // Program Studi
            'G' => 28, // Jurusan
            'H' => 12, // Status
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Styling header row 1: bold + background biru muda + center
        $headerStyle = [
            'font' => [
                'bold' => true,
                'color' => ['argb' => 'FF1F2937'], // slate-800
            ],
            'fill' => [
                'fillType'   => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FFDBEAFE'], // blue-100
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
            ],
        ];

        $sheet->getStyle('A1:H1')->applyFromArray($headerStyle);
        $sheet->getRowDimension(1)->setRowHeight(24);
        $sheet->freezePane('A2');

        return [];
    }

    public function title(): string
    {
        return 'Template Import Alumni';
    }
}
