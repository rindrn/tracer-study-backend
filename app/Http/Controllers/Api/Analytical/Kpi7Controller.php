<?php

namespace App\Http\Controllers\Api\Analytical;

use App\Http\Controllers\Controller;
use App\Services\Analytical\Kpi7Service;
use App\Services\Analytical\Kpi7ExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class Kpi7Controller extends Controller
{
    public function __construct(
        private readonly Kpi7Service       $service,
        private readonly Kpi7ExportService $exportService,
    ) {}

    // GET /api/dashboard/kpi/7/chart?year=2025&program_id=1&lam_id=1
    public function chart(Request $request): JsonResponse
    {
        $data = $this->service->getChart($request->query());

        return response()->json([
            'success' => true,
            'data'    => $data->toArray(),
        ]);
    }

    // GET /api/dashboard/kpi/7/details?year=2024&position=Owner&page=1&per_page=10
    public function details(Request $request): JsonResponse
    {
        $data = $this->service->getDetails($request->query());

        return response()->json([
            'success' => true,
            'data'    => $data->toArray(),
        ]);
    }

    // GET /api/dashboard/kpi/7/export?format=csv&year=2024
    // GET /api/dashboard/kpi/7/export?format=excel&position=Owner
    public function export(Request $request)
    {
        $format  = $request->query('format', 'csv');
        $filters = $request->except('format');

        return match ($format) {
            'excel' => $this->exportService->exportExcel($filters),
            default => $this->exportService->exportCsv($filters),
        };
    }
}