<?php

namespace App\Http\Controllers\Api\Alumni;

use App\Http\Controllers\Controller;
use App\Repositories\Transactional\AlumniProfileRepository;
use App\Services\Transactional\AlumniSelfServiceService;
use App\Services\Transactional\ConsentService;
use App\Services\Transactional\DataSubjectRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Portal "Data Saya" — seluruhnya di belakang middleware 'auth:alumni'.
 *
 * IDENTITAS SELALU DARI TOKEN, TIDAK PERNAH DARI BODY.
 * Setiap method di sini memakai $request->user('alumni')->id dan tidak satu
 * pun menerima alumni_id sebagai parameter. Ini bukan kehati-hatian
 * berlebihan: begitu satu endpoint menerima id dari klien, seluruh portal
 * berubah dari "lihat data saya" menjadi "lihat data siapa saja", dan
 * kebocorannya tidak akan terlihat di pengujian mana pun yang memakai token
 * pemiliknya sendiri.
 */
class SelfServiceController extends Controller
{
    public function __construct(
        private readonly AlumniSelfServiceService  $selfService,
        private readonly ConsentService            $consent,
        private readonly DataSubjectRequestService $requests,
        private readonly AlumniProfileRepository   $alumniRepo,
    ) {}

    /** GET /api/alumni/me — seluruh data yang disimpan tentang diri sendiri. */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->selfService->overview($request->user('alumni')->id),
        ]);
    }

    /** GET /api/alumni/me/consent — keadaan persetujuan saja, ringan. */
    public function consentState(Request $request): JsonResponse
    {
        // Dibaca ulang dari basis data, bukan dari model pemegang token.
        // Model itu dimuat dari cincangan token dan bisa membawa nilai
        // consent_* yang sudah basi kalau persetujuan baru saja berubah di
        // perangkat lain.
        $alumni = $this->alumniRepo->findByIdWithProgram($request->user('alumni')->id);

        return response()->json([
            'success' => true,
            'data'    => $this->consent->state($alumni),
        ]);
    }

    /** POST /api/alumni/me/consent — setujui versi yang sedang berlaku. */
    public function grantConsent(Request $request): JsonResponse
    {
        $result = $this->consent->grant($request->user('alumni')->id);

        return response()->json([
            'success' => true,
            'message' => 'Persetujuan Anda telah dicatat.',
            'data'    => $result,
        ], 201);
    }

    /** DELETE /api/alumni/me/consent — tarik persetujuan. */
    public function withdrawConsent(Request $request): JsonResponse
    {
        $this->consent->withdraw($request->user('alumni')->id);

        return response()->json([
            'success' => true,
            // Kalimatnya sengaja menyebut batasnya. Menjanjikan lebih dari
            // yang dilakukan sistem adalah bentuk lain dari tidak jujur:
            // jawaban yang sudah masuk memang tidak ikut terhapus.
            'message' => 'Persetujuan Anda telah ditarik. Anda tidak dapat mengisi kuesioner sampai menyetujui kembali. '
                . 'Jawaban yang sudah terkirim sebelumnya tetap tersimpan — ajukan permintaan penghapusan bila Anda menghendakinya.',
        ]);
    }

    /** GET /api/alumni/me/requests — riwayat permintaan sendiri. */
    public function listRequests(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->requests->listForAlumni($request->user('alumni')->id),
        ]);
    }

    /** POST /api/alumni/me/requests — ajukan koreksi, penghapusan, atau keberatan. */
    public function storeRequest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type'    => ['required', 'string', 'in:' . implode(',', DataSubjectRequestService::TYPES)],
            // Batas bawah 10 karakter: permintaan sebaris seperti "hapus"
            // tidak bisa ditindaklanjuti dan hanya akan memantulkan
            // pertanyaan balik yang memperlambat jawabannya sendiri.
            'message' => ['required', 'string', 'min:10', 'max:2000'],
        ], [
            'type.in'          => 'Jenis permintaan harus salah satu dari: koreksi, penghapusan, atau keberatan.',
            'message.required' => 'Jelaskan permintaan Anda agar dapat ditindaklanjuti.',
            'message.min'      => 'Penjelasan terlalu singkat untuk dapat ditindaklanjuti.',
        ]);

        $result = $this->requests->submit(
            alumniId: $request->user('alumni')->id,
            type:     $validated['type'],
            message:  $validated['message'],
        );

        return response()->json([
            'success' => true,
            'message' => 'Permintaan Anda telah tercatat dan akan ditinjau petugas.',
            'data'    => $result,
        ], 201);
    }
}
