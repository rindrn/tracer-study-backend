<?php

namespace App\Http\Controllers\Api\Analytical;

use App\Http\Controllers\Api\Analytical\Concerns\EnforcesProdiScope;
use App\Http\Controllers\Controller;
use App\Services\Analytical\Kpi13Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Kpi13Controller — Perbandingan KPI Lintas Program Studi
 *
 * Routes (di dalam auth:sanctum group):
 *   GET /api/dashboard/kpi/13/chart
 *   GET /api/dashboard/kpi/13/export
 *
 * Taruh di: app/Http/Controllers/Api/Analytical/Kpi13Controller.php
 */
class Kpi13Controller extends Controller
{
    use EnforcesProdiScope;

    public function __construct(
        private readonly Kpi13Service $service,
    ) {}

    // ──────────────────────────────────────────────────────────────

    /**
     * GET /api/dashboard/kpi/13/chart
     *
     * Query params (semua opsional, kaprodi/kajur dipaksa ke cakupannya):
     *   tahun_lulus (alias lama: tahun), minggu_snapshot (default snapshot
     *   terbaru), jenjang, jurusan, nama_prodi
     *
     * Response:
     * {
     *   "success": true,
     *   "data": {
     *     "kpi": { "id": 13, "name": "Perbandingan KPI Lintas Program Studi" },
     *     "filters": { "tahun_lulus": "2023", "minggu_snapshot": "4" },
     *     "available_years": ["2024","2023","2022"],
     *     "data": [
     *       {
     *         "id_prodi": 12,
     *         "prodi": "D-3 Teknik Elektronika",
     *         "jenjang": "D3",
     *         "jurusan": "Teknik Elektro",
     *         "total_alumni": 120,
     *         "keterserapan": 86.5,
     *         "wirausaha": 6.7,
     *         "masa_tunggu": 76.2,
     *         "kesesuaian": 82.1,
     *         "pendapatan": 54.3,
     *         "threshold_status": { "keterserapan": null, "wirausaha": null, "masa_tunggu": null, "kesesuaian": "unggul", "pendapatan": null },
     *         "thresholds": { "kesesuaian": { "lam": "LAM Teknik D3", "baik": 60, "unggul": 80, "param": null }, ... },
     *         "raw": { "alumni": 120, "terserap": 104, "bekerja": 98, "cepat": 75, "sesuai": 78, "tidak_sesuai": 17, ... }
     *       }
     *     ]
     *   }
     * }
     */
    public function chart(Request $request): JsonResponse
    {
        try {
            $dto = $this->service->getChart($this->scopedParams($request));

            return response()->json([
                'success' => true,
                'data'    => $dto->toArray(),
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }
    }

    // ──────────────────────────────────────────────────────────────

    /**
     * GET /api/dashboard/kpi/13/export
     *
     * Query params:
     *   ?format=csv|excel   (default: csv)
     *   ?tahun=2023
     */
    public function export(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        try {
            $format  = $request->query('format', 'csv');
            $filters = $this->scopedParams($request);
            unset($filters['format']);
            $rows    = $this->service->getExportRows($filters);

            return match ($format) {
                'excel' => $this->toExcel($rows, $filters),
                default => $this->toCsv($rows),
            };
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }
    }

    // ──────────────────────────────────────────────────────────────
    //  Export helpers
    // ──────────────────────────────────────────────────────────────

    private function toCsv(array $rows): \Illuminate\Http\Response
    {
        $headers = ['Program Studi', 'Jurusan', 'Total Alumni', 'Keterserapan (%)', 'Wirausaha (%)', 'Masa Tunggu Cepat (%)', 'Kesesuaian Bidang (%)', 'Pendapatan ≥ Ambang UMP (%)'];
        $lines   = [implode(',', $headers)];

        foreach ($rows as $r) {
            $lines[] = implode(',', [
                "\"{$r['prodi']}\"",
                "\"{$r['jurusan']}\"",
                $r['total_alumni'],
                $r['keterserapan'],
                $r['wirausaha'],
                $r['masa_tunggu'],
                $r['kesesuaian'],
                $r['pendapatan'],
            ]);
        }

        $filename = 'kpi13_' . now()->format('Ymd_His') . '.csv';

        return response(implode("\n", $lines), 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    private function toExcel(array $rows, array $filters): \Symfony\Component\HttpFoundation\Response
    {
        // Fallback ke CSV jika PhpSpreadsheet belum di-install
        if (! class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class)) {
            return $this->toCsv($rows);
        }

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet()->setTitle('KPI 13');

        $sheet->fromArray(
            [['Program Studi', 'Jurusan', 'Total Alumni', 'Keterserapan (%)', 'Wirausaha (%)', 'Masa Tunggu Cepat (%)', 'Kesesuaian Bidang (%)', 'Pendapatan ≥ Ambang UMP (%)']],
            null, 'A1'
        );
        $sheet->fromArray(
            array_map(fn($r) => [$r['prodi'], $r['jurusan'], $r['total_alumni'], $r['keterserapan'], $r['wirausaha'], $r['masa_tunggu'], $r['kesesuaian'], $r['pendapatan']], $rows),
            null, 'A2'
        );

        $filename = 'kpi13_' . now()->format('Ymd_His') . '.xlsx';
        $tmpPath  = sys_get_temp_dir() . '/' . $filename;

        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save($tmpPath);

        return response()->download($tmpPath, $filename)->deleteFileAfterSend();
    }
}