<?php

namespace App\Http\Controllers\Api\Transactional;

use App\Http\Controllers\Controller;
use App\Services\Transactional\QuestionSemanticMappingService;
use Illuminate\Http\JsonResponse;

/**
 * GET /api/semantic-roles -- semua role AKTIF di semantic_role_registry.
 * FE mengelompokkan hasil ini per `category` di sisi client (dropdown
 * bergrup di halaman Pemetaan Pertanyaan).
 */
class SemanticRoleController extends Controller
{
    public function __construct(
        private readonly QuestionSemanticMappingService $service,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->service->listRoles()->values(),
        ]);
    }
}
