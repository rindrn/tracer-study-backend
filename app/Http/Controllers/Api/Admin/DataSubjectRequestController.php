<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\Transactional\DataSubjectRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Antrean permintaan hak subjek data, untuk Ketua Tracer.
 *
 * Dibatasi ke satu peran, bukan ke semua staf. Permintaan ini memuat kalimat
 * bebas dari alumni yang kerap berisi keadaan pribadi ("data saya dipakai
 * pihak lain", "saya tidak ingin dihubungi lagi") — konteks yang tidak perlu
 * dan tidak pantas dibaca setiap pengelola prodi.
 */
class DataSubjectRequestController extends Controller
{
    public function __construct(
        private readonly DataSubjectRequestService $service,
    ) {}

    /** GET /api/admin/data-subject-requests?status=pending */
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->service->listForReview($request->query('status')),
        ]);
    }

    /** PATCH /api/admin/data-subject-requests/{id} */
    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'status'   => ['required', 'string', 'in:in_review,fulfilled,rejected'],
            'response' => ['nullable', 'string', 'max:2000'],
        ], [
            'status.in' => 'Status harus salah satu dari: in_review, fulfilled, rejected.',
        ]);

        // Kewajiban alasan pada penolakan ditegakkan di service, bukan di
        // aturan validasi ini. Alasannya: syaratnya bergantung pada nilai
        // field lain, dan menaruh aturan bersyarat di dua tempat berarti
        // suatu saat keduanya berbeda.
        $result = $this->service->resolve(
            requestId: $id,
            status:    $validated['status'],
            response:  $validated['response'] ?? null,
            handler:   $request->user(),
        );

        return response()->json([
            'success' => true,
            'message' => 'Permintaan berhasil diperbarui.',
            'data'    => $result,
        ]);
    }
}
