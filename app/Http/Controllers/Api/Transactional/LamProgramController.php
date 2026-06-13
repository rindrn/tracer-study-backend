<?php
// app/Http/Controllers/Api/Transactional/LamProgramController.php
namespace App\Http\Controllers\Api\Transactional;

use App\Http\Controllers\Controller;
use App\Http\Validators\LamProgramValidator;
use App\Services\Transactional\LamProgramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LamProgramController extends Controller
{
    public function __construct(
        private readonly LamProgramService   $service,
        private readonly LamProgramValidator $validator,
    ) {}

    // GET /api/lams/{id}/programs
    public function byLam(int $id): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->service->byLam($id),
        ]);
    }

    // POST /api/lam-programs
    public function store(Request $request): JsonResponse
    {
        $validated = $this->validator->validateStore($request->all());
        return response()->json([
            'success' => true,
            'message' => 'Program berhasil ditambahkan ke LAM.',
            'data'    => $this->service->syncAdd($validated),
        ], 201);
    }

    // DELETE /api/lam-programs
    public function destroy(Request $request): JsonResponse
    {
        $validated = $this->validator->validateDestroy($request->all());
        $this->service->detach($validated);
        return response()->json([
            'success' => true,
            'message' => 'Program berhasil dihapus dari LAM.',
        ]);
    }
}