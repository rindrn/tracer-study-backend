<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\Transactional\ReportService;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    public function __construct(
        private readonly ReportService $service,
    ) {}

    /** GET /api/admin/reports/export-alumni?questionnaire_id={id} */
    public function exportAlumniResponses(Request $request): BinaryFileResponse
    {
        $export = $this->service->buildAlumniResponsesExport(
            user:            $request->user(),
            questionnaireId: $request->query('questionnaire_id') ? (int) $request->query('questionnaire_id') : null,
        );

        return Excel::download(
            $export,
            'Laporan_Tracer_Study_' . date('YmdHis') . '.xlsx',
        );
    }
}
