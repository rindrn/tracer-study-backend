<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Repositories\Transactional\StakeholderContactRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StakeholderContactController extends Controller
{
    public function __construct(private readonly StakeholderContactRepository $repo) {}

    /**
     * Penyaring yang berlaku sama untuk tabel maupun unduhan.
     *
     * Disatukan di sini supaya berkas yang terunduh selalu memuat baris yang
     * sama dengan yang sedang dilihat. Sebelumnya ekspor mengabaikan kotak
     * cari, sehingga "unduh apa yang terlihat" tidak benar.
     */
    private function filters(Request $request): array
    {
        return [
            'graduation_year' => $request->query('graduation_year') ? (int) $request->query('graduation_year') : null,
            'alumni_status'   => $request->query('alumni_status'),
            'contact_type'    => $request->query('contact_type'),
            'program_code'    => $request->query('program_code'),
            'search'          => $request->query('search'),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $this->filters($request);

        return response()->json([
            'success' => true,
            'data'    => $this->repo->paginate($filters, (int) $request->query('per_page', 100)),
            'stats'   => $this->repo->stats($filters),
        ]);
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
        $data = $this->repo->getAll($this->filters($request));

        $format = $request->query('format', 'csv');
        $stamp  = now()->format('Ymd_His');

        if ($format === 'xlsx') {
            // Dua lembar: "Kontak" (per pasangan, untuk mail merge) dan
            // "Email Unik" (tanpa alamat kembar, untuk kiriman seragam).
            $export = new \App\Exports\StakeholderContactExport($data);
            return \Maatwebsite\Excel\Facades\Excel::download($export, "kontak_penilai_{$stamp}.xlsx");
        }

        // CSV tetap datar satu lembar — pemakainya biasanya menempel langsung
        // ke perkakas lain, dan format ini tidak mengenal banyak lembar.
        $csv = "NIM,Nama Alumni,Tahun Lulus,Kode Prodi,Program Studi,Tipe Kontak,Nama Kontak,Email Kontak,Status Alumni\n";
        foreach ($data as $row) {
            $csv .= sprintf(
                "\"%s\",\"%s\",%s,\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\"\n",
                $row->nim,
                $row->alumni_name,
                $row->graduation_year,
                $row->program_code ?? '-',
                $row->program_name ?? '-',
                $row->contact_type,
                $row->contact_name,
                $row->contact_email,
                $row->alumni_status,
            );
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"kontak_penilai_{$stamp}.csv\"",
        ]);
    }
}
