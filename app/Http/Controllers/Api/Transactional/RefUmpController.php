<?php
// app/Http/Controllers/Api/Transactional/RefUmpController.php

namespace App\Http\Controllers\Api\Transactional;

use App\Http\Controllers\Controller;
use App\Http\Validators\RefUmpValidator;
use App\Services\Transactional\RefUmpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class RefUmpController extends Controller
{
    public function __construct(
        private readonly RefUmpService   $service,
        private readonly RefUmpValidator $validator,
    ) {}

    // ─────────────────────────────────────────────────────────
    //  GET /api/ump/years
    //  Daftar tahun yang sudah punya data UMP.
    //  Dipakai FE untuk populate dropdown.
    // ─────────────────────────────────────────────────────────
    public function years(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->service->availableYears(),
        ]);
    }

    // ─────────────────────────────────────────────────────────
    //  GET /api/ump/{tahun}
    //  Data UMP 34 provinsi untuk satu tahun.
    //  Kalau tahun belum ada → return 34 baris kosong (preview kosong).
    // ─────────────────────────────────────────────────────────
    public function show(int $tahun): JsonResponse
    {
        $tahun = $this->validator->validateTahun($tahun);

        return response()->json([
            'success' => true,
            'data'    => $this->service->getByTahun($tahun),
        ]);
    }

    // ─────────────────────────────────────────────────────────
    //  GET /api/ump/{tahun}/fetch-bps
    //  Fetch data UMP dari BPS API → return preview (BELUM simpan).
    //  Admin review dulu di FE, lalu POST /api/ump/{tahun}/bulk-save.
    // ─────────────────────────────────────────────────────────
    public function fetchBps(int $tahun): JsonResponse
    {
        $tahun = $this->validator->validateTahun($tahun);

        $preview = $this->service->previewBps($tahun);

        return response()->json([
            'success' => true,
            'message' => "{$preview['ok_count']} provinsi berhasil difetch dari BPS."
                . ($preview['fail_count'] > 0
                    ? " {$preview['fail_count']} provinsi gagal, isi manual."
                    : ''),
            'data'    => $preview,
        ]);
    }

    // ─────────────────────────────────────────────────────────
    //  POST /api/ump/import
    //  Upload file Excel/CSV → return preview rows (BELUM simpan).
    //  Body: multipart/form-data, field "file"
    //        (opsional) field "tahun" — override tahun dari file
    //
    //  Setelah preview, admin tetap kirim ke POST /bulk-save.
    // ─────────────────────────────────────────────────────────
    public function import(Request $request): JsonResponse
    {
        $this->validator->validateImportFile($request->all() + ['file' => $request->file('file')]);

        $preview = $this->service->previewImport($request->file('file'));

        $msg = "Berhasil memparse {$preview['ok_count']} baris.";
        if (! empty($preview['unrecognized'])) {
            $names = implode(', ', $preview['unrecognized']);
            $msg  .= " " . count($preview['unrecognized']) . " nama provinsi tidak dikenali dan diabaikan: {$names}.";
        }

        return response()->json([
            'success' => true,
            'message' => $msg,
            'data'    => $preview,
        ]);
    }

    // ─────────────────────────────────────────────────────────
    //  POST /api/ump/{tahun}/bulk-save
    //  Simpan semua baris ke DB (setelah admin review preview).
    //  Baris dengan nilai_ump = null → dilewati.
    //
    //  Body JSON:
    //  {
    //    "rows": [
    //      { "id_provinsi": 30, "nilai_ump": 2057495, "sumber": "BPS_API" },
    //      { "id_provinsi": 27, "nilai_ump": 5067381, "sumber": "BPS_API" },
    //      { "id_provinsi": 35, "nilai_ump": null,    "sumber": "GAGAL"   }
    //    ]
    //  }
    // ─────────────────────────────────────────────────────────
    public function bulkSave(Request $request, int $tahun): JsonResponse
    {
        $tahun     = $this->validator->validateTahun($tahun);
        $validated = $this->validator->validateBulkSave(
            array_merge($request->all(), ['tahun' => $tahun])
        );

        $result = $this->service->bulkSave($tahun, $validated['rows']);

        return response()->json([
            'success' => true,
            'message' => "Data UMP {$tahun} berhasil disimpan."
                . " {$result['saved_count']} provinsi tersimpan"
                . ($result['skipped_count'] > 0
                    ? ", {$result['skipped_count']} dilewati (nilai kosong)."
                    : '.'),
            'data'    => $result,
        ]);
    }

    // ─────────────────────────────────────────────────────────
    //  PATCH /api/ump/{tahun}/provinces/{idProvinsi}
    //  Edit manual satu baris (inline edit dari tabel FE).
    //  Kalau baris belum ada → insert baru (upsert).
    //
    //  Body JSON: { "nilai_ump": 2100000 }
    // ─────────────────────────────────────────────────────────
    public function updateSingle(Request $request, int $tahun, int $idProvinsi): JsonResponse
    {
        $tahun     = $this->validator->validateTahun($tahun);
        $validated = $this->validator->validateUpdateSingle($request->all());

        $row = $this->service->updateSingle($tahun, $idProvinsi, $validated['nilai_ump']);

        return response()->json([
            'success' => true,
            'message' => "UMP {$row->namaProvinsi} tahun {$tahun} berhasil diperbarui.",
            'data'    => $row->toArray(),
        ]);
    }

    // ─────────────────────────────────────────────────────────
    //  GET /api/ump/template
    //  Download template CSV berisi 34 nama provinsi, kolom nilai kosong.
    // ─────────────────────────────────────────────────────────
    public function template(): Response
    {
        $csv = $this->service->generateTemplateCsv();

        return response($csv, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="template_ump.csv"',
        ]);
    }
}