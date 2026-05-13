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
     * GET /api/tracer-study/forms?kode_prodi=TI3
     *
     * Mengambil daftar kuesioner aktif (Pusat + Jurusan terkait).
     */
    public function getActiveForms(Request $request): JsonResponse
    {
        $data = $this->service->getActiveForms($request->query('kode_prodi'));

        if (empty($data)) {
            return response()->json([
                'success' => true,
                'data'    => [],
                'message' => 'Tidak ada kuesioner aktif.',
            ]);
        }

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }
}
