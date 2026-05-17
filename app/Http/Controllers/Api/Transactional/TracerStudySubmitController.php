<?php

namespace App\Http\Controllers\Api\Transactional;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SubmitTracerStudyRequest;
use App\Services\Transactional\TracerStudySubmitService;
use Illuminate\Http\JsonResponse;

class TracerStudySubmitController extends Controller
{
    public function __construct(
        private readonly TracerStudySubmitService $service,
    ) {}

    /** POST /api/tracer-study/submit */
    public function store(SubmitTracerStudyRequest $request): JsonResponse
    {
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
