<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Berkas kontak penilai untuk email blast Tim Tracer.
 *
 * Dua lembar, dua kegunaan — keduanya berasal dari data yang sama dan
 * mengikuti penyaring yang sedang aktif di halaman:
 *
 *   "Kontak"     satu baris per pasangan penilai-alumni, untuk surel yang
 *                dipersonalisasi lewat mail merge.
 *   "Email Unik" satu baris per alamat, untuk kiriman seragam tanpa membuat
 *                satu orang menerima surel berkali-kali.
 */
class StakeholderContactExport implements WithMultipleSheets
{
    use Exportable;

    public function __construct(private Collection $data) {}

    public function sheets(): array
    {
        return [
            new Sheets\StakeholderContactSheet($this->data),
            new Sheets\StakeholderUniqueEmailSheet($this->data),
        ];
    }
}
