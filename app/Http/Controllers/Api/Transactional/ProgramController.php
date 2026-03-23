<?php
namespace App\Http\Controllers\Api\Transactional;

use App\Http\Controllers\Controller;
use App\Http\Validators\ProgramValidator;
use App\Services\Transactional\ProgramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProgramController extends Controller
{
    public function __construct(
        private readonly ProgramService   $service,
        private readonly ProgramValidator $validator,
    ) {}

    // GET /api/programs?include_inactive=true&degree=S1
    public function index(Request $request): JsonResponse
    {
        $data = $this->service->list(
            includeInactive: $request->boolean('include_inactive', false),
            degree:          $request->query('degree'),
        );

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }

    // GET /api/programs/{id}
    public function show(int $id): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->service->show($id)->toArray(),
        ]);
    }

    // POST /api/programs
    public function store(Request $request): JsonResponse
    {
        $validated = $this->validator->validateCreate($request->all());
        $result    = $this->service->create($validated);  // ← langsung array

        return response()->json([
            'success' => true,
            'message' => 'Program studi berhasil dibuat.',
            'data'    => $result->toArray(),
        ], 201);
    }

    // PUT /api/programs/{id}
    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $this->validator->validateUpdate($request->all(), $id);
        $result    = $this->service->update($id, $validated);  // ← langsung array

        return response()->json([
            'success' => true,
            'message' => 'Program studi berhasil diperbarui.',
            'data'    => $result->toArray(),
        ]);
    }

    // DELETE /api/programs/{id}
    public function destroy(int $id): JsonResponse
    {
        $this->service->destroy($id);

        return response()->json([
            'success' => true,
            'message' => 'Program studi berhasil dinonaktifkan.',
        ]);
    }
}