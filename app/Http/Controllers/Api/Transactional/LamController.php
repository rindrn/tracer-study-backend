<?php
// app/Http/Controllers/Api/Transactional/LamController.php
namespace App\Http\Controllers\Api\Transactional;

use App\Http\Controllers\Controller;
use App\Http\Validators\LamValidator;
use App\Services\Transactional\LamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LamController extends Controller
{
    public function __construct(
        private readonly LamService   $service,
        private readonly LamValidator $validator,
    ) {}

    public function index(Request $request): JsonResponse
    {
        // ?include=versions,programs,thresholds
        $include = $request->has('include')
            ? array_map('trim', explode(',', $request->query('include', '')))
            : [];

        // Whitelist agar tidak bisa inject sembarang relasi
        $allowed = ['versions', 'programs', 'thresholds'];
        $include = array_intersect($include, $allowed);

        return response()->json([
            'success' => true,
            'data'    => $this->service->list($include),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->service->show($id)->toArray(),
        ]);
    }

    // GET /api/lams/{id}/full?year=2025
    public function full(Request $request, int $id): JsonResponse
    {
        $year = (int) $request->query('year', now()->year);
        return response()->json([
            'success' => true,
            'data'    => $this->service->full($id, $year),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validator->validateCreate($request->all());
        return response()->json([
            'success' => true,
            'message' => 'LAM berhasil dibuat.',
            'data'    => $this->service->create($validated)->toArray(),
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $this->validator->validateUpdate($request->all(), $id);
        return response()->json([
            'success' => true,
            'message' => 'LAM berhasil diperbarui.',
            'data'    => $this->service->update($id, $validated)->toArray(),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->service->delete($id);
        return response()->json([
            'success' => true,
            'message' => 'LAM berhasil dihapus.',
        ]);
    }
}