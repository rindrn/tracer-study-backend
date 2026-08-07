<?php

namespace App\Http\Controllers\Api\Analytical;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Analytical\Concerns\EnforcesProdiScope;
use App\Services\Analytical\MasaTungguService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MasaTungguController extends Controller
{
    use EnforcesProdiScope;

    public function __construct(
        private readonly MasaTungguService $service,
    ) {}

    public function bar(Request $request): JsonResponse
    {
        $request->validate([
            'jenjang'         => 'nullable|string|in:D3,D4,S2,S1',
            'jurusan'         => 'nullable|string|max:100',
            'nama_prodi'      => 'nullable|string|max:100',
            'tahun_lulus'     => 'nullable|string|max:5',
            'minggu_snapshot' => 'nullable|string|max:10',
            // Ambang dinamis (dulu hardcode 6 bulan) -- dikirim FE dari useLamFilter's
            // dynamicParam.value (indikator employment_time), default 6 kalau kosong.
            'batas_cepat_bulan' => 'nullable|numeric|min:0.1|max:60',
        ]);
        $p = $this->scopedParams($request);
 
        try {
            $dto = $this->service->getBar($p);
            return response()->json(['success' => true, 'data' => $dto->toArray()]);
        } catch (\RuntimeException $e) {
            return $this->serviceError($e);
        }
    }


    public function distribusi(Request $request): JsonResponse
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
            $dto = $this->service->getDistribusi($p);
            return response()->json(['success' => true, 'data' => $dto->toArray()]);
        } catch (\RuntimeException $e) {
            return $this->serviceError($e);
        }
    }

   
    public function drillDown(Request $request): JsonResponse
    {
        $request->validate([
            // 'cepat' -- lihat catatan MasaTungguRepository::buildRentangFilters():
            // drill-down dari bar "% Lulusan <= N Bulan" HARUS pakai ini (bukan
            // '0-3' yang dulu di-hardcode di FE), supaya jumlah baris cocok
            // dengan angka cepat di bar saat ambang dinamis > 3 bulan.
            'rentang'         => 'required|string|in:cepat,0-3,3-6,>6',
            'jenjang'         => 'nullable|string|in:D3,D4,S2,S1',
            'jurusan'         => 'nullable|string|max:100',
            'nama_prodi'      => 'nullable|string|max:100',
            'tahun_lulus'     => 'nullable|string|max:5',
            'minggu_snapshot' => 'nullable|string|max:10',
            'search'          => 'nullable|string|max:100',
            'page'            => 'nullable|integer|min:1',
            'per_page'        => 'nullable|integer|min:5|max:100',
            'batas_cepat_bulan' => 'nullable|numeric|min:0.1|max:60',
        ]);
        $p = $this->scopedParams($request);
 
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
            'batas_cepat_bulan' => 'nullable|numeric|min:0.1|max:60',
        ]);
        $p = $this->scopedParams($request);
 
        try {
            $dto = $this->service->getBandingkan($p);
            return response()->json(['success' => true, 'data' => $dto->toArray()]);
        } catch (\RuntimeException $e) {
            return $this->serviceError($e);
        }
    }

    // FR-026: pola pencarian kerja (bulan sebelum lulus mulai cari kerja + durasi)
    public function polaPencarianKerja(Request $request): JsonResponse
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
            $dto = $this->service->getPolaPencarianKerja($p);
            return response()->json(['success' => true, 'data' => $dto->toArray()]);
        } catch (\RuntimeException $e) {
            return $this->serviceError($e);
        }
    }

    // FR-027: prediksi tren median masa tunggu periode berikutnya (regresi linier)
    public function prediksi(Request $request): JsonResponse
    {
        $request->validate([
            'jenjang'         => 'nullable|string|in:D3,D4,S2,S1',
            'jurusan'         => 'nullable|string|max:100',
            'nama_prodi'      => 'nullable|string|max:100',
            'minggu_snapshot' => 'nullable|string|max:10',
        ]);
        $p = $this->scopedParams($request);

        try {
            $dto = $this->service->getPrediksi($p);
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