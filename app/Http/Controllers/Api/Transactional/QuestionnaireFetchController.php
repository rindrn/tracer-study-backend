<?php

namespace App\Http\Controllers\Api\Transactional;

use App\Http\Controllers\Controller;
use App\Services\Transactional\QuestionnaireFetchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuestionnaireFetchController extends Controller
{
    public function __construct(
        private readonly QuestionnaireFetchService $service,
    ) {}

    /**
     * GET /api/tracer-study/forms?kode_prodi=TI3&graduation_year=2024&nim=xxx
     *
     * Mengambil daftar kuesioner aktif (Pusat + Jurusan terkait).
     * Jika nim diberikan, tambahkan status pengisian per kuesioner.
     *
     * Dua field dikirim, dan bedanya penting:
     *
     *   - `responded_questionnaire_ids` — kuesioner yang sudah dikirim.
     *     Inilah yang dipakai FE untuk menyembunyikan bagian yang sudah
     *     selesai dan menyisakan yang belum.
     *   - `has_responded` — bernilai true hanya kalau SELURUH kuesioner
     *     aktif sudah dikirim. Ini yang boleh menutup formulir.
     *
     * Sebelumnya `has_responded` berarti "ada salah satu yang sudah dikirim",
     * sehingga kuesioner prodi yang diterbitkan setelah alumni mengirim
     * kuesioner umum tidak pernah bisa diisi. Lihat catatan panjang di
     * QuestionnaireFetchService::respondedQuestionnaireIds().
     */
    public function getActiveForms(Request $request): JsonResponse
    {
        $graduationYear = $request->query('graduation_year') ? (int) $request->query('graduation_year') : null;
        $data = $this->service->getActiveForms($request->query('kode_prodi'), $graduationYear);

        if (empty($data)) {
            return response()->json([
                'success'                     => true,
                'data'                        => [],
                'has_responded'               => false,
                'responded_questionnaire_ids' => [],
                'message'                     => 'Tidak ada kuesioner aktif.',
            ]);
        }

        $respondedIds = [];
        $nim = $request->query('nim');
        if ($nim) {
            $respondedIds = $this->service->respondedQuestionnaireIds($nim, $data);
        }

        return response()->json([
            'success'                     => true,
            'data'                        => $data,
            'has_responded'               => count($respondedIds) === count($data),
            'responded_questionnaire_ids' => $respondedIds,
        ]);
    }
}
