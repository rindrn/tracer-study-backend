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
 * Lembar "Email Unik" — satu baris per alamat surel.
 *
 * Satu orang bisa disebut banyak alumni sekaligus; seorang HRD yang disebut
 * sepuluh alumni akan menerima sepuluh surel kalau lembar "Kontak" dipakai
 * mentah-mentah, dan sebagian besar perkakas email blast juga menolak berkas
 * yang memuat alamat kembar. Lembar ini yang dipakai untuk kiriman seragam.
 *
 * Kolom "Jumlah Alumni" dan "Alumni yang Menyebut" dipertahankan supaya
 * penggabungan ini tetap bisa ditelusuri — Tim Tracer bisa melihat kenapa
 * sebuah alamat muncul sekali padahal mewakili beberapa alumni.
 */
class StakeholderUniqueEmailSheet implements FromCollection, WithHeadings, WithTitle, WithStyles, WithColumnWidths
{
    /** Batas nama alumni yang dirinci per baris sebelum diringkas. */
    private const MAX_LISTED_ALUMNI = 5;

    public function __construct(private Collection $data) {}

    public function collection(): Collection
    {
        // Dikelompokkan berdasar surel huruf kecil supaya "Budi@x.id" dan
        // "budi@x.id" tidak dihitung sebagai dua orang.
        return $this->data
            ->groupBy(fn ($r) => mb_strtolower(trim((string) $r->contact_email)))
            ->map(function (Collection $rows, string $email) {
                $alumni = $rows->pluck('alumni_name')->filter()->unique()->values();
                $listed = $alumni->take(self::MAX_LISTED_ALUMNI)->implode(', ');
                if ($alumni->count() > self::MAX_LISTED_ALUMNI) {
                    $listed .= sprintf(' (+%d lainnya)', $alumni->count() - self::MAX_LISTED_ALUMNI);
                }

                return [
                    $email,
                    // Nama yang dipakai adalah tulisan pertama; alumni berbeda
                    // bisa menuliskan orang yang sama dengan ejaan berbeda.
                    $rows->first()->contact_name,
                    $rows->pluck('contact_type')->unique()->sort()->implode(', '),
                    $alumni->count(),
                    $listed,
                ];
            })
            ->sortBy(fn ($row) => $row[0])
            ->values();
    }

    public function headings(): array
    {
        return ['Email Kontak', 'Nama Kontak', 'Tipe Kontak', 'Jumlah Alumni', 'Alumni yang Menyebut'];
    }

    public function columnWidths(): array
    {
        return ['A' => 32, 'B' => 25, 'C' => 18, 'D' => 14, 'E' => 50];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:E1')->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF3F4F6']],
        ]);
        $sheet->freezePane('A2');

        return [];
    }

    public function title(): string
    {
        return 'Email Unik';
    }
}
