<?php
namespace App\Services\Transactional;

use App\DTOs\Transactional\ThresholdResponseDTO;
use App\Exceptions\BusinessException;
use App\Repositories\Transactional\ThresholdRepository;
use App\Repositories\Transactional\LamVersionRepository;

class ThresholdService
{
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
        $rows = $this->repo->byVersion($lamVersionId);
        return $this->formatGroupedResponse($version, $rows);
    }

    public function create(array $validated): ThresholdResponseDTO
    {
        // Cek duplikat (lam_version + indicator + level)
        $existing = DB::connection('oltp')  // via constraint di DB sudah handle ini
            ?? null; // Laravel akan throw QueryException jika duplicate, tangkap di Handler
        return ThresholdResponseDTO::fromRow($this->repo->create($validated));
    }

    public function update(int $id, array $validated): ThresholdResponseDTO
    {
        if (! $this->repo->findById($id)) {
            throw new BusinessException("Threshold ID {$id} tidak ditemukan.", 404);
        }
        return ThresholdResponseDTO::fromRow($this->repo->update($id, $validated));
    }

    public function delete(int $id): void
    {
        if (! $this->repo->findById($id)) {
            throw new BusinessException("Threshold ID {$id} tidak ditemukan.", 404);
        }
        $this->repo->delete($id);
    }

    public function bulkCreate(int $lamVersionId, array $validated): array
    {
        $version = $this->versionRepo->findById($lamVersionId);
        if (! $version) {
            throw new BusinessException("LAM Version ID {$lamVersionId} tidak ditemukan.", 404);
        }

        $rows = $this->repo->bulkCreate($lamVersionId, $validated['thresholds']);

        return $this->formatGroupedResponse($version, $rows);
    }

    public function bulkUpdate(int $lamVersionId, array $validated): array
    {
        $version = $this->versionRepo->findById($lamVersionId);
        if (! $version) {
            throw new BusinessException("LAM Version ID {$lamVersionId} tidak ditemukan.", 404);
        }

        $this->repo->bulkUpdate($validated['thresholds']);

        // Re-fetch untuk response terbaru
        $rows = $this->repo->byVersion($lamVersionId);
        return $this->formatGroupedResponse($version, $rows);
    }

    // Private helper: hindari duplikasi logic grouped response
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

    public function forChart(?int $prodiId, string $indicatorKey): array
    {
        // Mode agregasi semua prodi
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
                $first = $items->first();
                $thresholds = [];
                foreach ($items as $item) {
                    $thresholds[$item->threshold_level] = [
                        'threshold_id' => $item->threshold_id,
                        'value'        => (float) $item->threshold_value,
                    ];
                }
                return [
                    'id'         => $first->version_id,
                    'year'       => $first->year,
                    'version_name' => $first->version_name,
                    'label'      => $result->lam->lam_name . ' ' . $first->year,
                    'is_active'  => (bool) $first->is_active,
                    'thresholds' => $thresholds,
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

    private function resolveIndicatorMeta(string $key): array
    {
        // Untuk mode all_prodi, tetap kembalikan metadata indicator
        // agar FE bisa tahu unit/operator-nya
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
    }
}