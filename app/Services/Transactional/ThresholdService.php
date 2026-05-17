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
        if (! $version) throw new BusinessException("LAM Version ID {$lamVersionId} tidak ditemukan.", 404);

        return [
            'lam' => [
                'id'   => $version->lam_id,
                'name' => $version->lam_name,
            ],
            'version' => [
                'id'   => $version->id,
                'year' => $version->year,
            ],
            'thresholds' => $this->repo->byVersion($lamVersionId)
                ->map(fn($row) => ThresholdResponseDTO::fromRow($row)->toArray())
                ->toArray(),
        ];
    }

    public function create(array $validated): ThresholdResponseDTO
    {
        $row = $this->repo->create($validated);
        return ThresholdResponseDTO::fromRow($row);
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
}