<?php

namespace App\Http\Controllers\Api\Transactional;

use App\Http\Controllers\Controller;
use App\Services\Transactional\InsightBoardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * InsightBoardController — "Dashboard Saya" halaman Insight.
 *
 *   GET    /api/dashboard/insight/board          → kartu berurutan
 *   POST   /api/dashboard/insight/board          → sematkan { question_id, size? }
 *   PUT    /api/dashboard/insight/board/order    → { item_ids: [...] } urutan baru
 *   PATCH  /api/dashboard/insight/board/{id}     → { size: sm|lg }
 *   DELETE /api/dashboard/insight/board/{id}     → lepas sematan
 */
class InsightBoardController extends Controller
{
    public function __construct(
        private readonly InsightBoardService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->service->list($request->user())]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'question_id' => 'required|integer',
            'size'        => 'sometimes|in:sm,lg',
        ]);

        return response()->json([
            'success' => true,
            'data'    => $this->service->pin($request->user(), (int) $data['question_id'], $data['size'] ?? 'sm'),
        ], 201);
    }

    public function reorder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'item_ids'   => 'present|array',
            'item_ids.*' => 'integer',
        ]);

        $this->service->reorder($request->user(), $data['item_ids']);

        return response()->json(['success' => true, 'data' => $this->service->list($request->user())]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['size' => 'required|in:sm,lg']);

        return response()->json(['success' => true, 'data' => $this->service->resize($request->user(), $id, $data['size'])]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->service->unpin($request->user(), $id);

        return response()->json(['success' => true]);
    }
}
