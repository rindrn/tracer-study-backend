<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\Transactional\ReportService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    public function __construct(
        private readonly ReportService $service,
    ) {}

    /**
     * GET /api/admin/reports/export-alumni?tahun_lulus={tahun}&questionnaire_id={id}&format={label|code}
     *
     * format menentukan tampilan nilai jawaban:
     *   - label (default) -> teks jawaban ("Bekerja", "Sangat Tinggi", "Ya"),
     *     untuk dibaca manusia.
     *   - code            -> option_code/angka mentah ("1", "5", "0"), format
     *     yang dibutuhkan portal Kementerian.
     * Sebelumnya pilihan ini ditentukan diam-diam dari role (head_tracer
     * selalu dapat kode mentah) -- diganti parameter eksplisit karena role
     * tidak menentukan apakah file mau dibaca atau diunggah.
     *
     * questionnaire_id juga menentukan CAKUPAN SHEET: kuesioner Kementerian
     * menghasilkan sheet "Data Kementrian" saja, kuesioner tambahan prodi
     * menghasilkan sheet "Data Khusus {PRODI}" saja. Lihat ReportService.
     *
     * tahun_lulus WAJIB diisi -- UI Unduh Data Alumni tidak lagi
     * menyediakan opsi "Semua Tahun". Alasan: tanpa filter tahun,
     * kolom header sheet "Data Kementrian" dihitung dari union semua
     * question_code yang muncul di seluruh data, yang bisa mencampur
     * pertanyaan dari questionnaire_id berbeda (periode kuesioner
     * berbeda bisa punya field tambahan/berbeda) dalam satu sheet --
     * membingungkan pembaca laporan. Mewajibkan tahun_lulus tidak
     * MENJAMIN satu questionnaire_id tunggal secara struktural, tapi
     * sangat mengurangi kemungkinan campuran tersebut di praktik nyata
     * institusi ini (lihat diskusi sebelumnya soal independensi
     * questionnaires.period_year vs alumni_profiles.graduation_year).
     */
    public function exportAlumniResponses(Request $request): BinaryFileResponse|JsonResponse
    {
        $validated = $request->validate([
            'tahun_lulus'      => ['required', 'integer', 'min:2000', 'max:2100'],
            'questionnaire_id' => ['nullable', 'integer'],
            'format'           => ['nullable', 'in:label,code'],
            // Hanya dipakai (dan divalidasi terhadap scope) untuk ketua_fakultas
            // -- lihat ReportService::buildAlumniResponsesExport().
            'jurusan'          => ['nullable', 'string', 'max:100'],
        ]);

        set_time_limit(300);
        ini_set('memory_limit', '512M');

        $export = $this->service->buildAlumniResponsesExport(
            user:            $request->user(),
            questionnaireId: $validated['questionnaire_id'] ?? null,
            tahunLulus:      $validated['tahun_lulus'],
            rawCode:         ($validated['format'] ?? 'label') === 'code',
            jurusan:         $validated['jurusan'] ?? null,
        );

        return Excel::download(
            $export,
            "Laporan_Tracer_Study_{$validated['tahun_lulus']}_" . date('YmdHis') . '.xlsx',
        );
    }
}