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
            // Boleh kosong bila ada rumus; syarat "minimal satu" dicek di service.
            'query.measures'           => 'present_with:query|array',
            'query.measures.*'         => 'string|max:100',
            'query.rowDims'            => 'present_with:query|array',
            'query.rowDims.*'          => 'string|max:100',
            'query.colDim'             => 'nullable|string|max:100',
            'query.filters'            => 'present_with:query|array',
            'query.filters.*.member'   => 'required|string|max:100',
            'query.filters.*.operator' => 'sometimes|string|in:equals,notEquals,set,notSet',
            'query.filters.*.values'   => 'present|array',
            'query.filters.*.values.*' => 'nullable|string|max:255',
            'query.formulas'           => 'sometimes|array|max:9',
            'query.formulas.*.key'     => 'required|string|max:20',
            'query.formulas.*.label'   => 'required|string|max:150',
            'query.formulas.*.left'    => 'required|string|max:100',
            'query.formulas.*.op'      => 'required|string|in:div,sub,add,mul',
            'query.formulas.*.right'   => 'required|string|max:100',
            'query.formulas.*.format'  => 'required|string|max:20',
            'query.minN'               => 'nullable|integer|min:1|max:100000',
            'query.sort'               => 'nullable|array',
            'query.sort.by'            => 'required_with:query.sort|string|max:100',
            'query.sort.direction'     => 'required_with:query.sort|in:asc,desc',
            'query.sort.limit'         => 'nullable|integer|min:1|max:500',
            'query.percent'            => 'nullable|in:none,row,column,all',
            'query.viz'                => 'nullable|in:auto,table,bar,row,line,area,stacked,pie',
            'query.diff'               => 'nullable|array',
            'query.diff.a'             => 'required_with:query.diff|string|max:255',
            'query.diff.b'             => 'required_with:query.diff|string|max:255',
        ];
    }
}
