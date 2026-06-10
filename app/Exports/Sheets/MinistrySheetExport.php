<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Illuminate\Support\Collection;

class MinistrySheetExport implements FromCollection, WithHeadings, WithTitle, WithStyles, WithColumnWidths
{
    protected $data;
    protected $questions;
    protected $optionsMap;
    protected $questionMeta;

    public function __construct(Collection $data, array $questions, array $optionsMap = [], array $questionMeta = [])
    {
        $this->data = $data;
        $this->questions = $questions;
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
                'Email'         => $alumni->email,
                'Telepon'       => $alumni->phone,
                'Tahun Lulus'   => $alumni->graduation_year,
                'Program Studi' => $alumni->program_name ?? '-',
                'Jurusan'       => $alumni->jurusan_name ?? '-',
                'NIK'           => "'" . ($alumni->nik ?? ''),
                'NPWP'          => "'" . ($alumni->npwp ?? ''),
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
        $headers = ['NIM', 'Nama', 'Email', 'Telepon', 'Tahun Lulus', 'Program Studi', 'Jurusan', 'NIK', 'NPWP'];
        foreach ($this->questions as $q) {
            $headers[] = $q['label'];
        }
        return $headers;
    }

    public function columnWidths(): array
    {
        $widths = [
            'A' => 20, 'B' => 25, 'C' => 30, 'D' => 18,
            'E' => 12, 'F' => 25, 'G' => 25, 'H' => 20, 'I' => 20,
        ];
        $startCol = 9; // J = index 9
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
        return 'Data Pertanyaan Wajib';
    }

    private function resolveLabel(string $code, $raw): string
    {
        if ($raw === null || $raw === '' || $raw === '-') {
            return '-';
        }

        // These codes should stay as raw number (DIKTI format)
        if (in_array($code, ['f301', 'f302', 'f303'], true)) {
            return (string) $raw;
        }

        $meta = $this->questionMeta[$code] ?? null;
        $type = $meta['type'] ?? null;
        $metadata = $meta['metadata'] ?? null;

        // Boolean type stays 0/1
        if ($type === 'boolean') {
            return (string) $raw;
        }

        // Number with scale_min/scale_max in metadata = scale question
        if ($type === 'number' && $metadata && isset($metadata['scale_min'])) {
            // Try options from DB first
            if (isset($this->optionsMap[$code][$raw])) {
                return $this->optionsMap[$code][$raw];
            }
            // Generate label from scale pattern
            return $this->scaleLabel($code, $raw, $metadata);
        }

        // Pure number (no scale metadata) — stays as number
        if ($type === 'number') {
            return (string) $raw;
        }

        // Single/multiple choice — map via options
        if (isset($this->optionsMap[$code][$raw])) {
            return $this->optionsMap[$code][$raw];
        }

        return (string) $raw;
    }

    private function scaleLabel(string $code, $value, array $metadata): string
    {
        // Kompetensi (f17xx): has "competency" or "dimension" key
        if (isset($metadata['competency']) || isset($metadata['dimension'])) {
            $labels = ['1' => 'Sangat Rendah', '2' => 'Rendah', '3' => 'Cukup', '4' => 'Tinggi', '5' => 'Sangat Tinggi'];
            return $labels[(string) $value] ?? (string) $value;
        }
        // Penekanan metode (f2x): has "method" key
        if (isset($metadata['method'])) {
            $labels = ['1' => 'Sangat Besar', '2' => 'Besar', '3' => 'Cukup Besar', '4' => 'Kurang Besar', '5' => 'Tidak Sama Sekali'];
            return $labels[(string) $value] ?? (string) $value;
        }
        // Generic scale fallback
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
