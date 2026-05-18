<?php

namespace App\Exports\Sheets;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ProdiReferensiSheet implements FromCollection, WithHeadings, WithTitle, WithStyles
{
    public function collection()
    {
        return DB::connection('oltp')->table('programs')
            ->select('code', 'name', 'degree', 'jurusan')
            ->orderBy('jurusan')
            ->orderBy('code')
            ->get()
            ->map(fn ($p) => [$p->code, $p->name, $p->degree, $p->jurusan]);
    }

    public function headings(): array
    {
        return ['Kode Prodi', 'Program Studi', 'Jenjang', 'Jurusan'];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:D1')->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF3F4F6']],
        ]);
        $sheet->freezePane('A2');
        return [];
    }

    public function title(): string
    {
        return 'Referensi Kode Prodi';
    }
}
