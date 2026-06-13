<?php

namespace App\Http\Controllers\Api\Analytical;

use App\Http\Controllers\Controller;
use App\Services\Analytical\SebaranInstansiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SebaranInstansiController extends Controller
{
    public function __construct(
        private readonly SebaranInstansiService $service,
    ) {}

    public function jenis(Request $request): JsonResponse
    {
        $params = $request->validate([
            'jenjang'         => 'nullable|string|in:D3,D4',
            'jurusan'         => 'nullable|string|max:100',
            'nama_prodi'      => 'nullable|string|max:100',
            'tahun_lulus'     => 'nullable|string|max:5',
            'minggu_snapshot' => 'nullable|string|max:10',
        ]);

        try {
            $dto = $this->service->getJenis($params);
            return response()->json(['success' => true, 'data' => $dto->toArray()]);
        } catch (\RuntimeException $e) {
            return $this->serviceError($e);
        }
    }

    public function tingkat(Request $request): JsonResponse
    {
        $params = $request->validate([
            'jenjang'         => 'nullable|string|in:D3,D4',
            'jurusan'         => 'nullable|string|max:100',
            'nama_prodi'      => 'nullable|string|max:100',
            'tahun_lulus'     => 'nullable|string|max:5',
            'minggu_snapshot' => 'nullable|string|max:10',
        ]);

        try {
            $dto = $this->service->getTingkat($params);
            return response()->json(['success' => true, 'data' => $dto->toArray()]);
        } catch (\RuntimeException $e) {
            return $this->serviceError($e);
        }
    }

    public function bandingkan(Request $request): JsonResponse
    {
        $params = $request->validate([
            'prodi'           => 'nullable|array',
            'prodi.*'         => 'string|max:100',
            'jenjang'         => 'nullable|string|in:D3,D4',
            'jurusan'         => 'nullable|string|max:100',
            'tahun_lulus'     => 'nullable|string|max:5',
            'minggu_snapshot' => 'nullable|string|max:10',
        ]);

        try {
            $dto = $this->service->getBandingkan($params);
            return response()->json(['success' => true, 'data' => $dto->toArray()]);
        } catch (\RuntimeException $e) {
            return $this->serviceError($e);
        }
    }


    public function lokasi(Request $request): JsonResponse
    {
        $params = $request->validate([
            'jenjang'         => 'nullable|string|in:D3,D4',
            'jurusan'         => 'nullable|string|max:100',
            'nama_prodi'      => 'nullable|string|max:100',
            'tahun_lulus'     => 'nullable|string|max:5',
            'minggu_snapshot' => 'nullable|string|max:10',
            'limit'           => 'nullable|integer|min:5|max:50',
        ]);

        try {
            $dto = $this->service->getLokasi($params);
            return response()->json(['success' => true, 'data' => $dto->toArray()]);
        } catch (\RuntimeException $e) {
            return $this->serviceError($e);
        }
    }

    public function drillDown(Request $request): JsonResponse
    {
        $params = $request->validate([
            'jenis_instansi'   => 'nullable|string|max:100',
            'tingkat_instansi' => 'nullable|string|in:Lokal,Nasional,Internasional',
            'jenjang'          => 'nullable|string|in:D3,D4',
            'nama_prodi'       => 'nullable|string|max:100',
            'tahun_lulus'      => 'nullable|string|max:5',
            'minggu_snapshot'  => 'nullable|string|max:10',
            'search'           => 'nullable|string|max:100',
            'page'             => 'nullable|integer|min:1',
            'per_page'         => 'nullable|integer|min:5|max:100',
        ]);

        // Validasi: minimal salah satu harus diisi
        if (empty($params['jenis_instansi']) && empty($params['tingkat_instansi'])) {
            return response()->json([
                'success' => false,
                'message' => 'Salah satu dari jenis_instansi atau tingkat_instansi wajib diisi.',
                'errors'  => [
                    'jenis_instansi'   => ['Wajib diisi jika tingkat_instansi kosong.'],
                    'tingkat_instansi' => ['Wajib diisi jika jenis_instansi kosong.'],
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $dto = $this->service->getDrillDown($params);
            return response()->json(['success' => true, 'data' => $dto->toArray()]);
        } catch (\RuntimeException $e) {
            return $this->serviceError($e);
        }
    }

    private function serviceError(\RuntimeException $e): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
        ], Response::HTTP_SERVICE_UNAVAILABLE);
    }
}