<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Illuminate\Support\Collection;

class ProdiSheetExport implements FromCollection, WithHeadings, WithTitle, WithStyles, WithColumnWidths
{
    protected $data;
    protected $questions;
    protected $sheetTitle;
    protected $optionsMap;
    protected $questionMeta;

    public function __construct(Collection $data, array $questions, string $sheetTitle = 'Data Khusus Prodi', array $optionsMap = [], array $questionMeta = [])
    {
        $this->data = $data;
        $this->questions = $questions;
        $this->sheetTitle = $sheetTitle;
        $this->optionsMap = $optionsMap;
        $this->questionMeta = $questionMeta;
    }

    public function collection()
    {
        $exportData = [];

        foreach ($this->data as $alumni) {
            $row = [
                'NIM'           => "'" . ($alumni->nim ?? ''),
                'Nama'          => $alumni->name,
                'Program Studi' => $alumni->program_name ?? '-',
            ];

            foreach ($this->questions as $q) {
                $code = $q['code'];
                $raw = $alumni->answers[$code] ?? null;
                $row[$q['label']] = $this->resolveLabel($code, $raw);
            }

            $exportData[] = $row;
        }

        return collect($exportData);
    }

    public function headings(): array
    {
        $headers = ['NIM', 'Nama', 'Program Studi'];
        foreach ($this->questions as $q) {
            $headers[] = $q['label'];
        }
        return $headers;
    }

    public function columnWidths(): array
    {
        $widths = ['A' => 20, 'B' => 25, 'C' => 25];
        $startCol = 3; // D = index 3
        foreach ($this->questions as $i => $_) {
            $widths[$this->colLetter($startCol + $i)] = 30;
        }
        return $widths;
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('1:1')->getFont()->setBold(true);
        $sheet->getStyle('1:1')->getAlignment()->setWrapText(true);
        $sheet->freezePane('A2');
        $sheet->getRowDimension(1)->setRowHeight(40);
        return [];
    }

    public function title(): string
    {
        return mb_substr($this->sheetTitle, 0, 31);
    }

    private function resolveLabel(string $code, $raw): string
    {
        if ($raw === null || $raw === '' || $raw === '-') {
            return '-';
        }

        if (in_array($code, ['f301', 'f302', 'f303'], true)) {
            return (string) $raw;
        }

        $meta = $this->questionMeta[$code] ?? null;
        $type = $meta['type'] ?? null;
        $metadata = $meta['metadata'] ?? null;

        if ($type === 'boolean') {
            return (string) $raw;
        }

        if ($type === 'number' && $metadata && isset($metadata['scale_min'])) {
            if (isset($this->optionsMap[$code][$raw])) {
                return $this->optionsMap[$code][$raw];
            }
            return $this->scaleLabel($code, $raw, $metadata);
        }

        if ($type === 'number') {
            return (string) $raw;
        }

        if (isset($this->optionsMap[$code][$raw])) {
            return $this->optionsMap[$code][$raw];
        }

        return (string) $raw;
    }

    private function scaleLabel(string $code, $value, array $metadata): string
    {
        if (isset($metadata['competency']) || isset($metadata['dimension'])) {
            $labels = ['1' => 'Sangat Rendah', '2' => 'Rendah', '3' => 'Cukup', '4' => 'Tinggi', '5' => 'Sangat Tinggi'];
            return $labels[(string) $value] ?? (string) $value;
        }
        if (isset($metadata['method'])) {
            $labels = ['1' => 'Sangat Besar', '2' => 'Besar', '3' => 'Cukup Besar', '4' => 'Kurang Besar', '5' => 'Tidak Sama Sekali'];
            return $labels[(string) $value] ?? (string) $value;
        }
        return (string) $value;
    }

    private function colLetter(int $index): string
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
