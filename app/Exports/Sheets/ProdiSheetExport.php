<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Illuminate\Support\Collection;
use App\Exports\Support\AnswerValueResolver;
use App\Exports\Support\ColumnLetter;

/**
 * Sheet "Data Khusus {KODE_PRODI}" -- jawaban kuesioner tambahan milik
 * satu prodi.
 *
 * Resolve nilai jawaban didelegasikan ke AnswerValueResolver, sama persis
 * dengan sheet Kementrian. Sebelumnya kelas ini punya resolveLabel()
 * sendiri, tapi map opsi/metadata yang dibutuhkannya TIDAK PERNAH dioper
 * oleh TracerStudyMultiSheetExport (dua argumen terakhir constructor
 * dibiarkan default kosong), sehingga logikanya tidak pernah benar-benar
 * jalan dan semua jawaban keluar sebagai angka mentah.
 */
class ProdiSheetExport extends DefaultValueBinder implements FromCollection, WithHeadings, WithTitle, WithStyles, WithColumnWidths, WithCustomValueBinder
{
    /** Kolom identitas alumni sebelum kolom-kolom pertanyaan. */
    private const IDENTITY_HEADERS = ['NIM', 'Nama', 'Program Studi', 'Tahun Lulus', 'Email', 'Hp'];

    /** Lebar per kolom identitas, urutannya mengikuti IDENTITY_HEADERS. */
    private const IDENTITY_WIDTHS = [18, 30, 28, 12, 28, 16];

    /** NIM dan Hp harus tetap teks -- lihat MinistrySheetExport::TEXT_COLUMNS. */
    private const TEXT_COLUMNS = ['A', 'F'];

    /**
     * @param Collection          $data      alumni + properti ->answers (code => answer_text)
     * @param array               $questions list of ['code' => ..., 'label' => ...]
     */
    public function __construct(
        private readonly Collection $data,
        private readonly array $questions,
        private readonly string $sheetTitle = 'Data Khusus Prodi',
        private readonly ?AnswerValueResolver $valueResolver = null,
    ) {}

    public function collection(): Collection
    {
        $resolver = $this->valueResolver ?? AnswerValueResolver::raw();

        $exportData = [];

        foreach ($this->data as $alumni) {
            $row = [
                'NIM'           => $alumni->nim ?: '-',
                'Nama'          => $alumni->name,
                'Program Studi' => $alumni->program_name ?? '-',
                'Tahun Lulus'   => $alumni->graduation_year ?? '-',
                'Email'         => $alumni->email ?: '-',
                'Hp'            => $alumni->phone ?: '-',
            ];

            foreach ($this->questions as $q) {
                $row[$q['label']] = $resolver->resolve($q['code'], $alumni->answers[$q['code']] ?? null);
            }

            $exportData[] = $row;
        }

        return collect($exportData);
    }

    public function headings(): array
    {
        return array_merge(
            self::IDENTITY_HEADERS,
            array_column($this->questions, 'label'),
        );
    }

    public function columnWidths(): array
    {
        $widths = [];

        foreach (self::IDENTITY_WIDTHS as $i => $width) {
            $widths[ColumnLetter::at($i)] = $width;
        }

        $firstQuestionColumn = count(self::IDENTITY_HEADERS);
        foreach (array_keys($this->questions) as $i) {
            $widths[ColumnLetter::at($firstQuestionColumn + $i)] = 32;
        }

        return $widths;
    }

    public function styles(Worksheet $sheet)
    {
        $lastColumn = ColumnLetter::at(count($this->headings()) - 1);

        $header = $sheet->getStyle("A1:{$lastColumn}1");
        $header->getFont()->setBold(true);
        $header->getAlignment()
            ->setWrapText(true)
            ->setVertical(Alignment::VERTICAL_TOP);

        $sheet->getRowDimension(1)->setRowHeight(75);
        $sheet->freezePane('C2');
        $sheet->setAutoFilter("A1:{$lastColumn}1");

        return [];
    }

    public function bindValue(Cell $cell, $value): bool
    {
        if ($value !== null && $value !== '' && in_array($cell->getColumn(), self::TEXT_COLUMNS, strict: true)) {
            $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    public function title(): string
    {
        return mb_substr($this->sheetTitle, 0, 31);
    }
}
