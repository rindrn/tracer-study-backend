<?php

namespace App\Http\Controllers\Api\Analytical;

use App\Http\Controllers\Controller;
use App\Repositories\Config\EtlAnomalyLogRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/etl-anomaly-log -- halaman monitoring admin (bukan setup
 * sekali-jalan seperti Pemetaan Pertanyaan, jadi cukup baca langsung
 * lewat repository, tidak perlu Service layer terpisah).
 */
class EtlAnomalyLogController extends Controller
{
    public function __construct(
        private readonly EtlAnomalyLogRepository $repo,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'etl_run_id'    => 'nullable|string|max:50',
            'semantic_role' => 'nullable|string|max:50',
            'nim'           => 'nullable|string|max:30',
            'page'          => 'nullable|integer|min:1',
        ]);

        $paginated = $this->repo->paginate([
            'etl_run_id'    => $request->query('etl_run_id'),
            'semantic_role' => $request->query('semantic_role'),
            'nim'           => $request->query('nim'),
        ], 15);

        return response()->json(array_merge(['success' => true], $paginated->toArray()));
    }
}
