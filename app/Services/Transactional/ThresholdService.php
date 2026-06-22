<?php
// app/Services/Transactional/ThresholdService.php
namespace App\Services\Transactional;

use App\DTOs\Transactional\ThresholdResponseDTO;
use App\Exceptions\BusinessException;
use App\Repositories\Transactional\ThresholdRepository;
use App\Repositories\Transactional\LamVersionRepository;
use App\Traits\WithCache;
use Illuminate\Support\Facades\DB;

class ThresholdService
{
    use WithCache;

    private const TTL      = 1800;  // 30 menit — untuk data per-version/per-chart
    private const TTL_META = 3600; // 1 jam  — untuk metadata indikator (jarang berubah)

    public function __construct(
        private readonly ThresholdRepository  $repo,
        private readonly LamVersionRepository $versionRepo,
    ) {}

    public function list(int $perPage = 15): array
    {
        $page   = (int) request()->query('page', 1);
        $result = $this->repo->paginate($perPage, $page);

        return [
            'data' => collect($result['rows'])
                ->map(fn($row) => ThresholdResponseDTO::fromRow($row)->toArray())
                ->toArray(),
            'meta' => [
                'current_page' => $result['page'],
                'last_page'    => $result['last_page'],
                'per_page'     => $result['per_page'],
                'total'        => $result['total'],
            ],
        ];
    }

    public function show(int $id): ThresholdResponseDTO
    {
        $row = $this->repo->findById($id);
        if (! $row) throw new BusinessException("Threshold ID {$id} tidak ditemukan.", 404);
        return ThresholdResponseDTO::fromRow($row);
    }

    public function byVersion(int $lamVersionId): array
    {
        $version = $this->versionRepo->findById($lamVersionId);
        if (! $version) {
            throw new BusinessException("LAM Version ID {$lamVersionId} tidak ditemukan.", 404);
        }

        return $this->remember("thresholds:by_version:{$lamVersionId}", function () use ($version, $lamVersionId) {
            $rows = $this->repo->byVersion($lamVersionId);
            return $this->formatGroupedResponse($version, $rows);
        }, self::TTL);
    }

    public function create(array $validated): ThresholdResponseDTO
    {
        $result = ThresholdResponseDTO::fromRow($this->repo->create($validated));

        $this->forget("thresholds:by_version:{$validated['lam_version_id']}");
        $this->forgetTag('thresholds');

        return $result;
    }

    public function update(int $id, array $validated): ThresholdResponseDTO
    {
        $existing = $this->repo->findById($id);
        if (! $existing) {
            throw new BusinessException("Threshold ID {$id} tidak ditemukan.", 404);
        }

        $result = ThresholdResponseDTO::fromRow($this->repo->update($id, $validated));

        $this->forget("thresholds:by_version:{$existing->lam_version_id}");
        $this->forgetTag('thresholds');

        return $result;
    }

    public function delete(int $id): void
    {
        $existing = $this->repo->findById($id);
        if (! $existing) {
            throw new BusinessException("Threshold ID {$id} tidak ditemukan.", 404);
        }

        $this->repo->delete($id);

        $this->forget("thresholds:by_version:{$existing->lam_version_id}");
        $this->forgetTag('thresholds');
    }

    public function bulkCreate(int $lamVersionId, array $validated): array
    {
        $version = $this->versionRepo->findById($lamVersionId);
        if (! $version) {
            throw new BusinessException("LAM Version ID {$lamVersionId} tidak ditemukan.", 404);
        }

        $rows   = $this->repo->bulkCreate($lamVersionId, $validated['thresholds']);
        $result = $this->formatGroupedResponse($version, $rows);

        // Bust semua cache yang berkaitan
        $this->forget("thresholds:by_version:{$lamVersionId}");
        $this->forgetTag('thresholds', 'lams');

        return $result;
    }

    public function bulkUpdate(int $lamVersionId, array $validated): array
    {
        $version = $this->versionRepo->findById($lamVersionId);
        if (! $version) {
            throw new BusinessException("LAM Version ID {$lamVersionId} tidak ditemukan.", 404);
        }

        $this->repo->bulkUpdate($validated['thresholds']);

        $rows   = $this->repo->byVersion($lamVersionId);
        $result = $this->formatGroupedResponse($version, $rows);

        $this->forget("thresholds:by_version:{$lamVersionId}");
        $this->forgetTag('thresholds', 'lams');

        return $result;
    }

    public function forChart(?int $prodiId, string $indicatorKey): array
    {
        $key = 'thresholds:chart:' . ($prodiId ?? 'all') . ':' . $indicatorKey;

        return $this->remember($key, function () use ($prodiId, $indicatorKey) {
            return $this->doForChart($prodiId, $indicatorKey);
        }, self::TTL);
    }

    // ── Private helpers ───────────────────────────────────────

    private function doForChart(?int $prodiId, string $indicatorKey): array
    {
        if (! $prodiId) {
            return [
                'context'   => 'all_prodi',
                'lam'       => null,
                'indicator' => $this->resolveIndicatorMeta($indicatorKey),
                'versions'  => [],
            ];
        }

        $result = $this->repo->byProdiAndIndicator($prodiId, $indicatorKey);

        // Prodi tidak punya LAM
        if (! $result) {
            return [
                'context'   => 'prodi',
                'lam'       => null,
                'indicator' => $this->resolveIndicatorMeta($indicatorKey),
                'versions'  => [],
            ];
        }

        // Group rows per version
        $versions = collect($result->rows)
            ->groupBy('version_id')
            ->map(function ($items) use ($result) {
                $first      = $items->first();
                $thresholds = [];
                foreach ($items as $item) {
                    $thresholds[$item->threshold_level] = [
                        'threshold_id' => $item->threshold_id,
                        'value'        => (float) $item->threshold_value,
                    ];
                }
                return [
                    'id'           => $first->version_id,
                    'year'         => $first->year,
                    'version_name' => $first->version_name,
                    'label'        => $result->lam->lam_name . ' ' . $first->year,
                    'is_active'    => (bool) $first->is_active,
                    'thresholds'   => $thresholds,
                ];
            })
            ->values()
            ->toArray();

        $firstRow = $result->rows->first();

        return [
            'context'   => 'prodi',
            'lam'       => [
                'id'   => $result->lam->lam_id,
                'name' => $result->lam->lam_name,
                'code' => $result->lam->lam_code,
            ],
            'indicator' => [
                'key'      => $firstRow->indicator_key,
                'name'     => $firstRow->indicator_name,
                'unit'     => $firstRow->indicator_unit,
                'operator' => $firstRow->indicator_operator,
            ],
            'versions' => $versions,
        ];
    }

    private function formatGroupedResponse(object $version, \Illuminate\Support\Collection $rows): array
    {
        $grouped = $rows->groupBy('indicator_id')
            ->map(function ($items) {
                $first  = $items->first();
                $result = [
                    'indicator_id'   => $first->indicator_id,
                    'indicator_key'  => $first->indicator_key,
                    'indicator_name' => $first->indicator_name,
                    'unit'           => $first->indicator_unit,
                    'operator'       => $first->indicator_operator,
                ];
                foreach ($items as $item) {
                    $result[$item->threshold_level] = [
                        'threshold_id' => $item->threshold_id,
                        'value'        => (float) $item->threshold_value,
                    ];
                }
                return $result;
            })
            ->values()
            ->toArray();

        return [
            'lam'        => ['id' => $version->lam_id, 'name' => $version->lam_name],
            'version'    => ['id' => $version->id,     'year' => $version->year],
            'thresholds' => $grouped,
        ];
    }

    private function resolveIndicatorMeta(string $key): array
    {
        return $this->remember("threshold_indicators:meta:{$key}", function () use ($key) {
            $row = DB::connection('oltp')
                ->table('threshold_indicators')
                ->where('key', $key)
                ->first();

            if (! $row) return ['key' => $key, 'name' => null, 'unit' => null, 'operator' => null];

            return [
                'key'      => $row->key,
                'name'     => $row->name,
                'unit'     => $row->unit,
                'operator' => $row->operator,
            ];
        }, self::TTL_META);
    }
}