<?php

namespace App\Http\Controllers\Api\Analytical;

use App\Http\Controllers\Controller;
use App\Services\Analytical\KesesuaianService;
use App\Http\Controllers\Api\Analytical\Concerns\EnforcesProdiScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class KesesuaianController extends Controller
{
    use EnforcesProdiScope;

    public function __construct(
        private readonly KesesuaianService $service,
    ) {}

    public function bar(Request $request): JsonResponse
    {
        $params = $request->validate([
            'jenjang'         => 'nullable|string|in:D3,D4',
            'jurusan'         => 'nullable|string|max:100',
            'nama_prodi'      => 'nullable|string|max:100',
            'tahun_lulus'     => 'nullable|string|max:5',
            'minggu_snapshot' => 'nullable|string|max:10',
        ]);

        try {
            $dto = $this->service->getBar($params);
            return response()->json(['success' => true, 'data' => $dto->toArray()]);
        } catch (\RuntimeException $e) {
            return $this->serviceError($e);
        }
    }

    public function pie(Request $request): JsonResponse
    {
        $params = $request->validate([
            'jenjang'         => 'nullable|string|in:D3,D4',
            'jurusan'         => 'nullable|string|max:100',
            'nama_prodi'      => 'nullable|string|max:100',
            'tahun_lulus'     => 'nullable|string|max:5',
            'minggu_snapshot' => 'nullable|string|max:10',
        ]);

        try {
            $dto = $this->service->getPie($params);
            return response()->json(['success' => true, 'data' => $dto->toArray()]);
        } catch (\RuntimeException $e) {
            return $this->serviceError($e);
        }
    }

    public function alasan(Request $request): JsonResponse
    {
        $params = $request->validate([
            'jenjang'         => 'nullable|string|in:D3,D4',
            'jurusan'         => 'nullable|string|max:100',
            'nama_prodi'      => 'nullable|string|max:100',
            'tahun_lulus'     => 'nullable|string|max:5',
            'minggu_snapshot' => 'nullable|string|max:10',
        ]);

        try {
            $dto = $this->service->getAlasan($params);
            return response()->json(['success' => true, 'data' => $dto->toArray()]);
        } catch (\RuntimeException $e) {
            return $this->serviceError($e);
        }
    }


    public function drillDown(Request $request): JsonResponse
    {
        $request->validate([
            'kesesuaian_sk'    => 'nullable|integer|min:1|max:5',
            'label_pertanyaan' => 'nullable|string|max:500',
            'jenjang'          => 'nullable|string|in:D3,D4',
            'nama_prodi'       => 'nullable|string|max:100',
            'tahun_lulus'      => 'nullable|string|max:5',
            'minggu_snapshot'  => 'nullable|string|max:10',
            'search'           => 'nullable|string|max:100',
            'page'             => 'nullable|integer|min:1',
            'per_page'         => 'nullable|integer|min:5|max:100',
        ]);

        // scopedParams() hanya meneruskan global filters — params spesifik diambil langsung
        $p = array_merge($this->scopedParams($request), [
            'kesesuaian_sk'    => $request->input('kesesuaian_sk'),
            'label_pertanyaan' => $request->input('label_pertanyaan'),
            'search'           => $request->input('search'),
            'page'             => $request->input('page'),
            'per_page'         => $request->input('per_page'),
        ]);

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