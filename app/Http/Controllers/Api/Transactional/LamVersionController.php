<?php
// app/Http/Controllers/Api/Transactional/LamVersionController.php
namespace App\Http\Controllers\Api\Transactional;

use App\Http\Controllers\Controller;
use App\Http\Validators\LamVersionValidator;
use App\Services\Transactional\LamVersionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LamVersionController extends Controller
{
    public function __construct(
        private readonly LamVersionService   $service,
        private readonly LamVersionValidator $validator,
    ) {}

    public function show(int $id): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->service->show($id)->toArray(),
        ]);
    }

    // GET /api/lams/{id}/versions
    public function byLam(int $id): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->service->byLam($id),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validator->validateCreate($request->all());
        return response()->json([
            'success' => true,
            'message' => 'Versi LAM berhasil dibuat.',
            'data'    => $this->service->create($validated)->toArray(),
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $this->validator->validateUpdate($request->all());
        return response()->json([
            'success' => true,
            'message' => 'Versi LAM berhasil diperbarui.',
            'data'    => $this->service->update($id, $validated)->toArray(),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->service->delete($id);
        return response()->json([
            'success' => true,
            'message' => 'Versi LAM berhasil dihapus.',
        ]);
    }
}