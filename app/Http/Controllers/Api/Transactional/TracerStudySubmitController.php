<?php

namespace App\Http\Controllers\Api\Transactional;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SubmitTracerStudyRequest;
use App\Services\Transactional\ConsentService;
use App\Services\Transactional\TracerStudySubmitService;
use Illuminate\Http\JsonResponse;

class TracerStudySubmitController extends Controller
{
    public function __construct(
        private readonly TracerStudySubmitService $service,
        private readonly ConsentService           $consent,
    ) {}

    /**
     * POST /api/tracer-study/submit — di belakang middleware 'auth:alumni'.
     *
     * @throws BusinessException 403 jika NIM pada payload bukan milik pemegang token
     * @throws BusinessException 451 jika alumni belum menyetujui pemberitahuan privasi
     */
    public function store(SubmitTracerStudyRequest $request): JsonResponse
    {
        // Autentikasi saja tidak cukup: tanpa cek ini, alumni yang sudah login
        // masih bisa mengirim jawaban atas nama NIM orang lain, karena seluruh
        // alur submit (upsert alumni, response, employment) berpijak pada NIM
        // di payload — bukan pada identitas pemegang token.
        $alumni = $request->user('alumni');
        if ($request->validated()['nim'] !== $alumni->nim) {
            throw new BusinessException(
                'NIM pada formulir tidak sesuai dengan akun yang sedang login.',
                403,
            );
        }

        // Dasar hukum pemrosesan diperiksa SEBELUM satu baris pun ditulis
        // (UU 27/2022). Diletakkan di sini, sejajar dengan pemeriksaan NIM,
        // supaya kedua syarat masuk terlihat berdampingan alih-alih tersebar
        // antara controller dan service.
        $this->consent->assertGranted($alumni);

        \Log::info('[TracerStudy] Submit payload keys', [
            'keys'      => array_keys($request->all()),
            'alumni_id' => $alumni->id,
        ]);

        $this->service->submit(
            validated:  $request->validated(),
            rawAnswers: $request->all(),
        );

        return response()->json([
            'success' => true,
            'message' => 'Data Kuesioner Tracer Study berhasil disimpan.',
        ], 201);
    }
}
