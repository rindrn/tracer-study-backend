<?php

namespace App\Services\Analytical;

use Illuminate\Support\Facades\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class Kpi7ExportService
{
    public function __construct(
        private readonly Kpi7Service $kpi7Service,
    ) {}

    public function exportCsv(array $filters): StreamedResponse
    {
        $data = $this->kpi7Service->getExportData($filters);

        $filename = 'kpi7_wirausaha_' . now()->format('Ymd_His') . '.csv';

        return Response::streamDownload(function () use ($data) {
            $handle = fopen('php://output', 'w');

            // Header kolom
            fputcsv($handle, [
                'No', 'Nama', 'NIM', 'Program Studi', 'Jenjang',
                'Tahun Lulus', 'Status', 'Peran Wirausaha', 'Gaji (IDR)',
            ]);

            foreach ($data as $i => $row) {
                fputcsv($handle, [
                    $i + 1,
                    $row['name'],
                    $row['nim'],
                    $row['program_studi'],
                    $row['degree'],
                    $row['graduation_year'],
                    $row['status'],
                    $row['position'] ?? '-',
                    $row['salary']   ?? '-',
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function exportExcel(array $filters): StreamedResponse
    {
        $data     = $this->kpi7Service->getExportData($filters);
        $filename = 'kpi7_wirausaha_' . now()->format('Ymd_His') . '.xlsx';

        return Response::streamDownload(function () use ($data) {
            // Header baris
            $rows   = [];
            $rows[] = [
                'No', 'Nama', 'NIM', 'Program Studi', 'Jenjang',
                'Tahun Lulus', 'Status', 'Peran Wirausaha', 'Gaji (IDR)',
            ];

            foreach ($data as $i => $row) {
                $rows[] = [
                    $i + 1,
                    $row['name'],
                    $row['nim'],
                    $row['program_studi'],
                    $row['degree'],
                    $row['graduation_year'],
                    $row['status'],
                    $row['position'] ?? '-',
                    $row['salary']   ?? '-',
                ];
            }

            // Generate XML Excel sederhana (SpreadsheetML)
            echo $this->generateXmlExcel($rows);
        }, $filename, [
            'Content-Type'        => 'application/vnd.ms-excel',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    private function generateXmlExcel(array $rows): string
    {
        $xml  = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet">';
        $xml .= '<Worksheet ss:Name="KPI 7 Wirausaha">';
        $xml .= '<Table>';

        foreach ($rows as $row) {
            $xml .= '<Row>';
            foreach ($row as $cell) {
                $type  = is_numeric($cell) ? 'Number' : 'String';
                $value = htmlspecialchars((string) $cell);
                $xml  .= "<Cell><Data ss:Type=\"{$type}\">{$value}</Data></Cell>";
            }
            $xml .= '</Row>';
        }

        $xml .= '</Table></Worksheet></Workbook>';
        return $xml;
    }
}