<?php

namespace App\Exports\Support;

use Maatwebsite\Excel\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

/**
 * Pengikat nilai bawaan seluruh ekspor Excel.
 *
 * DefaultValueBinder menjadikan teks berawalan "=" sebagai RUMUS, sehingga isian
 * bebas alumni/kontak penilai seperti `=cmd|'/c calc'!A1` tereksekusi saat
 * berkas dibuka (CSV/formula injection, OWASP). Teks semacam itu — termasuk
 * awalan "+", "-", "@", tab, dan CR — di sini selalu ditulis sebagai teks biasa.
 * Angka dan tanggal tetap diperlakukan seperti biasa.
 */
class SafeStringValueBinder extends DefaultValueBinder
{
    public function bindValue(Cell $cell, $value): bool
    {
        if (is_string($value) && preg_match('/^[=+\-@\t\r]/', $value) && !is_numeric($value)) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);
            return true;
        }

        return parent::bindValue($cell, $value);
    }
}
