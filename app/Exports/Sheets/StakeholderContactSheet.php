<?php

namespace App\Exports\Sheets;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Lembar "Kontak" — satu baris per pasangan penilai dan alumni.
 *
 * Bentuk inilah yang dipakai kalau surelnya dipersonalisasi: mail merge perlu
 * tahu alumni mana yang menyebut si penilai, supaya surelnya bisa berbunyi
 * "Anda dicatat sebagai atasan dari <nama alumni>". Alamat yang sama bisa
 * muncul lebih dari sekali di sini — untuk daftar tanpa kembar, pakai lembar
 * "Email Unik".
 */
class StakeholderContactSheet implements FromCollection, WithHeadings, WithTitle, WithStyles, WithColumnWidths
{
    public function __construct(private Collection $data) {}

    public function collection(): Collection
    {
        return $this->data->map(fn ($r) => [
            // Awalan kutip tunggal menahan Excel memangkas nol di depan NIM.
            "'" . $r->nim,
            $r->alumni_name,
            $r->graduation_year,
            $r->program_code ?? '-',
            $r->program_name ?? '-',
            $r->contact_type,
            $r->contact_name,
            $r->contact_email,
            $r->alumni_status,
        ]);
    }

    public function headings(): array
    {
        return ['NIM', 'Nama Alumni', 'Tahun Lulus', 'Kode Prodi', 'Program Studi', 'Tipe Kontak', 'Nama Kontak', 'Email Kontak', 'Status Alumni'];
    }

    public function columnWidths(): array
    {
        return ['A' => 20, 'B' => 25, 'C' => 12, 'D' => 14, 'E' => 30, 'F' => 15, 'G' => 25, 'H' => 32, 'I' => 15];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:I1')->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF3F4F6']],
        ]);
        $sheet->freezePane('A2');

        return [];
    }

    public function title(): string
    {
        return 'Kontak';
    }
}
