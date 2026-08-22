<?php

namespace App\Http\Controllers\Api\Analytical;

use App\Support\Degree;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Analytical\Concerns\EnforcesProdiScope;
use App\Services\Analytical\SebaranInstansiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SebaranInstansiController extends Controller
{
    use EnforcesProdiScope;

    public function __construct(
        private readonly SebaranInstansiService $service,
    ) {}

    public function jenis(Request $request): JsonResponse
    {
        $request->validate([
            'jenjang'         => Degree::filterRule(),
            'jurusan'         => 'nullable|string|max:100',
            'nama_prodi'      => 'nullable|string|max:100',
            'tahun_lulus'     => 'nullable|string|max:5',
            'minggu_snapshot' => 'nullable|string|max:10',
        ]);
        $p = $this->scopedParams($request);
 
        try {
            $dto = $this->service->getJenis($p);
            return response()->json(['success' => true, 'data' => $dto->toArray()]);
        } catch (\RuntimeException $e) {
            return $this->serviceError($e);
        }
    }

    public function tingkat(Request $request): JsonResponse
    {
        $request->validate([
            'jenjang'         => Degree::filterRule(),
            'jurusan'         => 'nullable|string|max:100',
            'nama_prodi'      => 'nullable|string|max:100',
            'tahun_lulus'     => 'nullable|string|max:5',
            'minggu_snapshot' => 'nullable|string|max:10',
        ]);
        $p = $this->scopedParams($request);
 
        try {
            $dto = $this->service->getTingkat($p);
            return response()->json(['success' => true, 'data' => $dto->toArray()]);
        } catch (\RuntimeException $e) {
            return $this->serviceError($e);
        }
    }

    public function bandingkan(Request $request): JsonResponse
    {
        $request->validate([
            'prodi'           => 'nullable|array',
            'prodi.*'         => 'string|max:100',
            'jenjang'         => Degree::filterRule(),
            'jurusan'         => 'nullable|string|max:100',
            'tahun_lulus'     => 'nullable|string|max:5',
            'minggu_snapshot' => 'nullable|string|max:10',
        ]);
        $p = $this->scopedParams($request);
 
        try {
            $dto = $this->service->getBandingkan($p);
            return response()->json(['success' => true, 'data' => $dto->toArray()]);
        } catch (\RuntimeException $e) {
            return $this->serviceError($e);
        }
    }


    public function lokasi(Request $request): JsonResponse
    {
        $request->validate([
            'jenjang'         => Degree::filterRule(),
            'jurusan'         => 'nullable|string|max:100',
            'nama_prodi'      => 'nullable|string|max:100',
            'tahun_lulus'     => 'nullable|string|max:5',
            'minggu_snapshot' => 'nullable|string|max:10',
            'limit'           => 'nullable|integer|min:5|max:50',
        ]);
        $p = $this->scopedParams($request);
 
        try {
            $dto = $this->service->getLokasi($p);
            return response()->json(['success' => true, 'data' => $dto->toArray()]);
        } catch (\RuntimeException $e) {
            return $this->serviceError($e);
        }
    }

    public function drillDown(Request $request): JsonResponse
    {
        $request->validate([
            'jenis_instansi'   => 'nullable|array',
            'jenis_instansi.*' => 'string|max:100',
            // Label asli di gudang data adalah kalimat penuh — mis.
            // 'Lokal/Wilayah/Wiraswasta tidak berbadan hukum' dan
            // 'Multinasional/Internasional'. Whitelist lama
            // (Lokal,Nasional,Internasional) menolak nilai yang sah dan
            // meloloskan nilai yang tidak pernah cocok saat difilter 'equals'.
            'tingkat_instansi' => 'nullable|string|max:100',
            'jenjang'          => Degree::filterRule(),
            'nama_prodi'       => 'nullable|string|max:100',
            'tahun_lulus'      => 'nullable|string|max:5',
            'minggu_snapshot'  => 'nullable|string|max:10',
            'search'           => 'nullable|string|max:100',
            'page'             => 'nullable|integer|min:1',
            'per_page'         => 'nullable|integer|min:5|max:100',
        ]);
        $p = $this->scopedParams($request);
 
        if (empty($p['jenis_instansi']) && empty($p['tingkat_instansi'])) {
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
            $dto = $this->service->getDrillDown($p);
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