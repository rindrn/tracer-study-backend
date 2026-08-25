<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Repositories\Transactional\StakeholderContactRepository;
use App\Services\Transactional\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StakeholderContactController extends Controller
{
    public function __construct(
        private readonly StakeholderContactRepository $repo,
        private readonly AuditLogService $audit,
    ) {}

    /**
     * Netralkan awalan formula (`=`,`+`,`-`,`@`) di nilai bebas-teks sebelum
     * ditulis ke CSV — mitigasi CSV/formula-injection standar OWASP. Tanpa
     * ini, nilai seperti `=cmd|'/c calc'!A1` dieksekusi mentah oleh Excel/
     * LibreOffice saat berkas dibuka.
     */
    private function csvSafe(?string $value): string
    {
        $value = (string) $value;
        if (preg_match('/^[=+\-@]/', $value)) {
            return "'" . $value;
        }
        return $value;
    }

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
            'jurusan'         => $request->query('jurusan'),
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

        // Ekspor memindahkan data pribadi (nama & email kontak penilai)
        // keluar dari sistem — dicatat setara dengan `export.ministry`,
        // sesuai kewajiban `config/privacy.php` (`audit_read_actions`).
        $this->audit->record('export.stakeholder_contacts', [
            'entity_type' => 'stakeholder_contacts',
            'context'     => [
                'format'  => $format,
                'filters' => $this->filters($request),
                'rows'    => count($data),
            ],
        ]);

        if ($format === 'xlsx') {
            // Dua lembar: "Kontak" (per pasangan, untuk mail merge) dan
            // "Email Unik" (tanpa alamat kembar, untuk kiriman seragam).
            $export = new \App\Exports\StakeholderContactExport($data);
            return \Maatwebsite\Excel\Facades\Excel::download($export, "kontak_penilai_{$stamp}.xlsx");
        }

        // CSV tetap datar satu lembar — pemakainya biasanya menempel langsung
        // ke perkakas lain, dan format ini tidak mengenal banyak lembar.
        //
        // Ditulis lewat fputcsv() (bukan sprintf manual) supaya tanda kutip
        // ganda yang tersemat di nilai bebas-teks (mis. nama kontak) di-escape
        // otomatis sesuai RFC 4180, dan setiap kolom bebas-teks dinetralkan
        // dulu lewat csvSafe() supaya awalan formula (`=`,`+`,`-`,`@`) tidak
        // dieksekusi mentah oleh Excel/LibreOffice saat berkas dibuka.
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, ['NIM', 'Nama Alumni', 'Tahun Lulus', 'Kode Prodi', 'Program Studi', 'Tipe Kontak', 'Nama Kontak', 'Email Kontak', 'Status Alumni']);
        foreach ($data as $row) {
            fputcsv($handle, [
                $this->csvSafe($row->nim),
                $this->csvSafe($row->alumni_name),
                $row->graduation_year,
                $this->csvSafe($row->program_code ?? '-'),
                $this->csvSafe($row->program_name ?? '-'),
                $this->csvSafe($row->contact_type),
                $this->csvSafe($row->contact_name),
                $this->csvSafe($row->contact_email),
                $this->csvSafe($row->alumni_status),
            ]);
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"kontak_penilai_{$stamp}.csv\"",
            // Panjang isi disetel EKSPLISIT supaya peramban tahu total bita
            // yang akan diterimanya. Tanpa itu, unduhan hanya bisa melaporkan
            // "sudah menerima sekian bita" tanpa penyebut, sehingga tidak ada
            // persentase yang dapat ditampilkan. Berkasnya dirakit utuh di
            // memori lebih dahulu, jadi panjangnya memang sudah diketahui.
            'Content-Length' => (string) strlen($csv),
        ]);
    }
}
