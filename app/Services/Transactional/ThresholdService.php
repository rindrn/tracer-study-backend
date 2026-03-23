<?php
// app/Services/Transactional/ThresholdService.php
namespace App\Services\Transactional;

use App\DTOs\Transactional\ThresholdResponseDTO;
use App\Exceptions\BusinessException;
use App\Repositories\Transactional\ThresholdRepository;

class ThresholdService
{
    public function __construct(
        private readonly ThresholdRepository $repo,
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
        if (! $row) {
            throw new BusinessException("Threshold ID {$id} tidak ditemukan.", 404);
        }
        return ThresholdResponseDTO::fromRow($row);
    }

    // ← Terima array $validated langsung dari validator
    public function create(array $validated): ThresholdResponseDTO
    {
        $row = $this->repo->create(
            ['name' => $validated['name'], 'value' => $validated['value']],
            $validated['program_ids'],
        );
        return ThresholdResponseDTO::fromRow($row);
    }

    // ← Terima array $validated langsung dari validator
    public function update(int $id, array $validated): ThresholdResponseDTO
    {
        if (! $this->repo->findById($id)) {
            throw new BusinessException("Threshold ID {$id} tidak ditemukan.", 404);
        }
        $row = $this->repo->update(
            $id,
            ['name' => $validated['name'], 'value' => $validated['value']],
            $validated['program_ids'],
        );
        return ThresholdResponseDTO::fromRow($row);
    }

    public function delete(int $id): void
    {
        if (! $this->repo->findById($id)) {
            throw new BusinessException("Threshold ID {$id} tidak ditemukan.", 404);
        }
        $this->repo->delete($id);
    }
}