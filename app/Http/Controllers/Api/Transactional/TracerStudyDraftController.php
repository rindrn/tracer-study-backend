<?php

namespace App\Http\Controllers\Api\Transactional;

use App\Http\Controllers\Controller;
use App\Services\Transactional\ConsentService;
use App\Services\Transactional\TracerStudyDraftService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Draf pengisian kuesioner alumni.
 *
 * Seluruh endpoint di sini berada di belakang middleware 'auth:alumni', jadi
 * $request->user('alumni') dijamin ada dan identitasnya tidak perlu (dan tidak
 * boleh) ikut dikirim di body.
 */
class TracerStudyDraftController extends Controller
{
    public function __construct(
        private readonly TracerStudyDraftService $service,
        private readonly ConsentService          $consent,
    ) {}

    /**
     * GET /api/tracer-study/draft — muat draf tersimpan.
     *
     * SENGAJA tidak menuntut persetujuan. Alumni yang menarik persetujuannya
     * tetap boleh melihat apa yang sudah pernah ia tulis; yang dihentikan
     * adalah penulisan baru, bukan aksesnya sendiri atas datanya sendiri.
     */
    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->service->get($request->user('alumni')),
        ]);
    }

    /** POST /api/tracer-study/draft — simpan draf (autosave). */
    public function store(Request $request): JsonResponse
    {
        // Sengaja longgar: draf memang setengah jadi. Aturan wajib isi dan
        // kecocokan tipe baru ditegakkan saat submit (SubmitTracerStudyRequest).
        $request->validate([
            'answers' => ['required', 'array'],
        ]);

        // Autosave juga pemrosesan data pribadi, jadi ia tunduk pada dasar
        // hukum yang sama dengan pengiriman. Menjaga submit saja akan
        // menyisakan jalur yang menuliskan jawaban alumni ke basis data tanpa
        // persetujuan — dan justru jalur itu yang paling sering dilewati,
        // karena autosave berjalan sendiri tanpa alumni menekan apa pun.
        $this->consent->assertGranted($request->user('alumni'));

        return response()->json([
            'success' => true,
            'data'    => $this->service->save($request->user('alumni'), $request->input('answers')),
        ]);
    }

    /** DELETE /api/tracer-study/draft — "mulai ulang". */
    public function destroy(Request $request): JsonResponse
    {
        $deleted = $this->service->clear($request->user('alumni'));

        return response()->json([
            'success' => true,
            'message' => $deleted > 0
                ? 'Draf pengisian berhasil dihapus.'
                : 'Tidak ada draf yang perlu dihapus.',
        ]);
    }
}
