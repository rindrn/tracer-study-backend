<?php

namespace App\Http\Controllers\Api\Transactional;

use App\Http\Controllers\Controller;
use App\Services\Transactional\InsightQuestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * InsightQuestionController — pertanyaan tersimpan halaman Insight.
 *
 *   GET    /api/dashboard/insight/questions        → milik sendiri + yang dibagikan
 *   GET    /api/dashboard/insight/questions/{id}   → satu pertanyaan (tautan ?q=id)
 *   POST   /api/dashboard/insight/questions        → simpan
 *   PUT    /api/dashboard/insight/questions/{id}   → ubah (pemilik saja)
 *   DELETE /api/dashboard/insight/questions/{id}   → hapus (pemilik saja)
 *
 * Isi `query` divalidasi terhadap katalog OLAP di service, bukan di sini —
 * aturan bentuk di bawah hanya memastikan tipe datanya.
 */
class InsightQuestionController extends Controller
{
    public function __construct(
        private readonly InsightQuestionService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->service->list($request->user())]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->service->show($request->user(), $id)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules(required: true));

        return response()->json(
            ['success' => true, 'data' => $this->service->create($request->user(), $data)],
            201,
        );
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate($this->rules(required: false));

        return response()->json(['success' => true, 'data' => $this->service->update($request->user(), $id, $data)]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->service->delete($request->user(), $id);

        return response()->json(['success' => true]);
    }

    private function rules(bool $required): array
    {
        $req = $required ? 'required' : 'sometimes';

        return [
            'title'                    => "{$req}|string|max:150",
            'description'              => 'nullable|string|max:1000',
            'chart_measure'            => 'nullable|string|max:100',
            'is_shared'                => 'sometimes|boolean',
            'query'                    => "{$req}|array",
            'query.cube'               => 'required_with:query|string|max:100',
            'query.measures'           => 'required_with:query|array|min:1',
            'query.measures.*'         => 'string|max:100',
            'query.rowDims'            => 'present_with:query|array',
            'query.rowDims.*'          => 'string|max:100',
            'query.colDim'             => 'nullable|string|max:100',
            'query.filters'            => 'present_with:query|array',
            'query.filters.*.member'   => 'required|string|max:100',
            'query.filters.*.values'   => 'present|array',
            'query.filters.*.values.*' => 'nullable|string|max:255',
        ];
    }
}
