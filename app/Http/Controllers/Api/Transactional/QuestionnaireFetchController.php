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
     * Jika nim diberikan, tambahkan flag has_responded.
     */
    public function getActiveForms(Request $request): JsonResponse
    {
        $graduationYear = $request->query('graduation_year') ? (int) $request->query('graduation_year') : null;
        $data = $this->service->getActiveForms($request->query('kode_prodi'), $graduationYear);

        if (empty($data)) {
            return response()->json([
                'success'       => true,
                'data'          => [],
                'has_responded' => false,
                'message'       => 'Tidak ada kuesioner aktif.',
            ]);
        }

        $hasResponded = false;
        $nim = $request->query('nim');
        if ($nim) {
            $hasResponded = $this->service->hasAlumniResponded($nim, $data);
        }

        return response()->json([
            'success'       => true,
            'data'          => $data,
            'has_responded' => $hasResponded,
        ]);
    }
}
