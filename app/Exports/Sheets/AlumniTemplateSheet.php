<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class AlumniTemplateSheet implements FromCollection, WithHeadings, WithTitle, WithStyles, WithColumnWidths
{
    public function collection()
    {
        return collect([]);
    }

    /**
     * Sembilan kolom pertama sengaja disamakan urutan dan maknanya dengan
     * IDENTITY_HEADERS di MinistrySheetExport, supaya berkas yang diekspor
     * ke kementerian dan berkas yang diimpor kembali sebangun (DATA-03).
     *
     * Program Studi dan Jurusan menyusul di belakang sebagai kolom bantu
     * pembaca: importir tidak memakainya, prodi ditentukan oleh kolom
     * Kode PDDIKTI/Prodi -- yang menerima kode PDDIKTI maupun singkatan
     * internal kampus (lihat AlumniImport::collection()).
     *
     * Kolom Status dihapus — seluruh alumni hasil impor otomatis aktif.
     */
    public function headings(): array
    {
        return [
            'Kode PT', 'Kode PDDIKTI/Prodi', 'NIM', 'Nama', 'No. HP', 'Surel',
            'Tahun Lulus', 'NIK', 'NPWP',
            'Program Studi', 'Jurusan',
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 12, 'B' => 20, 'C' => 20, 'D' => 32, 'E' => 18, 'F' => 32,
            'G' => 14, 'H' => 22, 'I' => 22,
            'J' => 36, 'K' => 28,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:K1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FF1F2937']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFDBEAFE']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(24);
        $sheet->freezePane('A2');
        return [];
    }

    public function title(): string
    {
        return 'Template Import Alumni';
    }
}
