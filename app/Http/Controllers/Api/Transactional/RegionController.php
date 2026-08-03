<?php

namespace App\Http\Controllers\Api\Transactional;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RegionController extends Controller
{
    public function provinces(): JsonResponse
    {
        $data = DB::connection('oltp')->table('provinces')->orderBy('name')->get();
        return response()->json(['success' => true, 'data' => $data]);
    }

    public function cities(Request $request): JsonResponse
    {
        $query = DB::connection('oltp')->table('cities');
        if ($request->query('province_code')) {
            $query->where('province_code', $request->query('province_code'));
        }
        return response()->json(['success' => true, 'data' => $query->orderBy('name')->get()]);
    }

    // ── CRUD Provinces ───────────────────────────────────────────
    public function storeProvince(Request $request): JsonResponse
    {
        $data = $request->validate(['code' => 'required|string|max:10|unique:oltp.provinces,code', 'name' => 'required|string|max:100']);
        $id = DB::connection('oltp')->table('provinces')->insertGetId($data);
        return response()->json(['success' => true, 'data' => (object) array_merge(['id' => $id], $data)], 201);
    }

    public function updateProvince(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['code' => "required|string|max:10|unique:oltp.provinces,code,{$id}", 'name' => 'required|string|max:100']);
        DB::connection('oltp')->table('provinces')->where('id', $id)->update($data);
        return response()->json(['success' => true]);
    }

    public function destroyProvince(int $id): JsonResponse
    {
        DB::connection('oltp')->table('provinces')->where('id', $id)->delete();
        return response()->json(['success' => true]);
    }

    // ── CRUD Cities ──────────────────────────────────────────────
    public function storeCity(Request $request): JsonResponse
    {
        $data = $request->validate(['province_code' => 'required|string|max:10', 'code' => 'required|string|max:10|unique:oltp.cities,code', 'name' => 'required|string|max:150']);
        $id = DB::connection('oltp')->table('cities')->insertGetId($data);
        return response()->json(['success' => true, 'data' => (object) array_merge(['id' => $id], $data)], 201);
    }

    public function updateCity(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['province_code' => 'required|string|max:10', 'code' => "required|string|max:10|unique:oltp.cities,code,{$id}", 'name' => 'required|string|max:150']);
        DB::connection('oltp')->table('cities')->where('id', $id)->update($data);
        return response()->json(['success' => true]);
    }

    public function destroyCity(int $id): JsonResponse
    {
        DB::connection('oltp')->table('cities')->where('id', $id)->delete();
        return response()->json(['success' => true]);
    }

    public function downloadProvinces(): StreamedResponse
    {
        $rows = DB::connection('oltp')->table('provinces')->orderBy('name')->get();
        return $this->csvResponse('master_provinsi.csv', ['Kode', 'Nama Provinsi'], $rows->map(fn ($r) => [$r->code, $r->name])->toArray());
    }

    public function downloadCities(): StreamedResponse
    {
        $rows = DB::connection('oltp')->table('cities')->orderBy('province_code')->orderBy('name')->get();
        return $this->csvResponse('master_kabupaten_kota.csv', ['Kode Provinsi', 'Kode Kab/Kota', 'Nama Kab/Kota'], $rows->map(fn ($r) => [$r->province_code, $r->code, $r->name])->toArray());
    }

    public function downloadPrograms(): StreamedResponse
    {
        $rows = DB::connection('oltp')->table('programs')->where('is_active', true)->orderBy('jurusan')->orderBy('degree')->get();
        return $this->csvResponse('master_kode_prodi.csv', ['Kode Dikti', 'Kode Internal', 'Nama Program Studi', 'Jenjang', 'Jurusan'], $rows->map(fn ($r) => [$r->dikti_code ?? '', $r->code, $r->name, $r->degree, $r->jurusan])->toArray());
    }

    private function csvResponse(string $filename, array $headers, array $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM
            fputcsv($out, $headers);
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
