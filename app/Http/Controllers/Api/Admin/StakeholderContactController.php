<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Repositories\Transactional\StakeholderContactRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StakeholderContactController extends Controller
{
    public function __construct(private readonly StakeholderContactRepository $repo) {}

    public function index(Request $request): JsonResponse
    {
        $result = $this->repo->paginate([
            'graduation_year' => $request->query('graduation_year') ? (int) $request->query('graduation_year') : null,
            'alumni_status' => $request->query('alumni_status'),
            'search' => $request->query('search'),
        ], (int) $request->query('per_page', 100));

        return response()->json(['success' => true, 'data' => $result]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'alumni_id' => 'required|integer',
            'questionnaire_id' => 'required|integer',
            'alumni_status' => 'required|string',
            'contacts' => 'required|array|min:1',
            'contacts.*.contact_type' => 'required|string',
            'contacts.*.contact_name' => 'required|string|max:150',
            'contacts.*.contact_email' => 'required|email|max:150',
        ]);

        $this->repo->bulkUpsert(
            $request->input('alumni_id'),
            $request->input('questionnaire_id'),
            array_map(fn ($c) => [...$c, 'alumni_status' => $request->input('alumni_status')], $request->input('contacts'))
        );

        return response()->json(['success' => true, 'message' => 'Kontak stakeholder berhasil disimpan.'], 201);
    }

    public function export(Request $request)
    {
        $data = $this->repo->getAll([
            'graduation_year' => $request->query('graduation_year') ? (int) $request->query('graduation_year') : null,
            'alumni_status' => $request->query('alumni_status'),
        ]);

        $format = $request->query('format', 'csv');

        if ($format === 'xlsx') {
            $export = new \App\Exports\StakeholderContactExport($data);
            return \Maatwebsite\Excel\Facades\Excel::download($export, 'stakeholder_contacts.xlsx');
        }

        $csv = "NIM,Nama Alumni,Tahun Lulus,Tipe Kontak,Nama Kontak,Email Kontak,Status Alumni\n";
        foreach ($data as $row) {
            $csv .= "\"{$row->nim}\",\"{$row->alumni_name}\",{$row->graduation_year},\"{$row->contact_type}\",\"{$row->contact_name}\",\"{$row->contact_email}\",\"{$row->alumni_status}\"\n";
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="stakeholder_contacts.csv"',
        ]);
    }
}
