<?php

namespace App\Http\Controllers\Api\Transactional;

use App\Http\Controllers\Controller;
use App\Models\Transactional\EtlRun;
use Illuminate\Http\JsonResponse;

/**
 * GET /api/etl-runs/{id} -- dipoll FE setelah simpan/nonaktifkan mapping
 * Langkah 1 untuk menampilkan status ETL (queued/running/completed/failed)
 * yang otomatis ter-trigger di belakang layar. Lihat RunEtlJob.
 */
class EtlRunController extends Controller
{
    public function show(int $id): JsonResponse
    {
        $run = EtlRun::find($id);

        if ($run === null) {
            return response()->json(['success' => false, 'message' => 'ETL run tidak ditemukan.'], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'id'            => $run->id,
                'status'        => $run->status,
                'reason'        => $run->reason,
                'id_waktu'      => $run->id_waktu,
                'summary'       => $run->summary,
                'error_message' => $run->error_message,
                'started_at'    => $run->started_at,
                'finished_at'   => $run->finished_at,
            ],
        ]);
    }
}
