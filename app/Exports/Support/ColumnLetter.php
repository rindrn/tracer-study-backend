<?php

namespace App\Exports\Support;

/**
 * Konversi indeks kolom berbasis-0 ke huruf kolom Excel (0 => A, 25 => Z,
 * 26 => AA). Sheet export punya 80-an kolom, jadi bagian dua-huruf bukan
 * kasus teoretis.
 */
final class ColumnLetter
{
    public static function at(int $index): string
    {
        $letter = '';
        $index++;

        while ($index > 0) {
            $index--;
            $letter = chr(ord('A') + ($index % 26)) . $letter;
            $index = intdiv($index, 26);
        }

        return $letter;
    }
}
