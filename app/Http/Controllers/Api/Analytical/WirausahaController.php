<?php

namespace App\Http\Controllers\Api\Analytical;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Analytical\Concerns\EnforcesProdiScope;
use App\Services\Analytical\WirausahaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WirausahaController extends Controller
{
    use EnforcesProdiScope;
    
    public function __construct(
        private readonly WirausahaService $service,
    ) {}


    public function bar(Request $request): JsonResponse
    {
        $request->validate([
            'jenjang'         => 'nullable|string|in:D3,D4,S2,S1',
            'jurusan'         => 'nullable|string|max:100',
            'nama_prodi'      => 'nullable|string|max:100',
            'tahun_lulus'     => 'nullable|string|max:5',
            'minggu_snapshot' => 'nullable|string|max:10',
        ]);
        $p = $this->scopedParams($request);
 
        try {
            $dto = $this->service->getBar($p);
            return response()->json(['success' => true, 'data' => $dto->toArray()]);
        } catch (\RuntimeException $e) {
            return $this->serviceError($e);
        }
    }


    public function pie(Request $request): JsonResponse
    {
        $request->validate([
            'jenjang'         => 'nullable|string|in:D3,D4,S2,S1',
            'jurusan'         => 'nullable|string|max:100',
            'nama_prodi'      => 'nullable|string|max:100',
            'tahun_lulus'     => 'nullable|string|max:5',
            'minggu_snapshot' => 'nullable|string|max:10',
        ]);
        $p = $this->scopedParams($request);
 
        try {
            $dto = $this->service->getPie($p);
            return response()->json(['success' => true, 'data' => $dto->toArray()]);
        } catch (\RuntimeException $e) {
            return $this->serviceError($e);
        }
    }


    public function drillDown(Request $request): JsonResponse
    {
        $request->validate([
            // Jangan di-whitelist. Label jabatan berasal dari questionnaire_options
            // dan bisa berubah tiap periode kuesioner — whitelist hardcoded lama
            // ('Owner', 'CEO', 'Manager', 'Direktur') tidak pernah ada di data,
            // sementara opsi asli 'Freelance / Kerja Lepas' justru ditolak 422.
            'jabatan'         => 'nullable|string|max:100',
            'jenjang'         => 'nullable|string|in:D3,D4,S2,S1',
            'nama_prodi'      => 'nullable|string|max:100',
            'tahun_lulus'     => 'nullable|string|max:5',
            'minggu_snapshot' => 'nullable|string|max:10',
            'search'          => 'nullable|string|max:100',
            'page'            => 'nullable|integer|min:1',
            'per_page'        => 'nullable|integer|min:5|max:100',
        ]);

        // scopedParams() hanya meneruskan global filters — params spesifik diambil langsung
        $p = array_merge($this->scopedParams($request), [
            'jabatan'  => $request->input('jabatan'),
            'search'   => $request->input('search'),
            'page'     => $request->input('page'),
            'per_page' => $request->input('per_page'),
        ]);

        try {
            $dto = $this->service->getDrillDown($p);
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
            'jenjang'         => 'nullable|string|in:D3,D4,S2,S1',
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

    private function serviceError(\RuntimeException $e): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
        ], Response::HTTP_SERVICE_UNAVAILABLE);
    }
}