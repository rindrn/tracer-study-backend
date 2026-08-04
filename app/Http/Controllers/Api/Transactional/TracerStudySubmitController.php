<?php

namespace App\Http\Controllers\Api\Transactional;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SubmitTracerStudyRequest;
use App\Services\Transactional\TracerStudySubmitService;
use Illuminate\Http\JsonResponse;

class TracerStudySubmitController extends Controller
{
    public function __construct(
        private readonly TracerStudySubmitService $service,
    ) {}

    /**
     * POST /api/tracer-study/submit — di belakang middleware 'auth:alumni'.
     *
     * @throws BusinessException 403 jika NIM pada payload bukan milik pemegang token
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
