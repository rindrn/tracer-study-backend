<?php
namespace App\Services\Transactional;

use App\DTOs\Transactional\ProgramResponseDTO;
use App\Exceptions\BusinessException;
use App\Repositories\Transactional\ProgramRepository;

class ProgramService
{
    public function __construct(
        private readonly ProgramRepository $repo,
    ) {}

    public function list(bool $includeInactive, ?string $degree): array
    {
        return $this->repo
            ->all($includeInactive, $degree)
            ->map(fn($p) => ProgramResponseDTO::fromModel($p)->toArray())
            ->toArray();
    }

    public function show(int $id): ProgramResponseDTO
    {
        $program = $this->repo->findById($id);
        if (! $program) {
            throw new BusinessException("Program ID {$id} tidak ditemukan.", 404);
        }
        return ProgramResponseDTO::fromModel($program);
    }

    public function create(array $validated): ProgramResponseDTO
    {
        $program = $this->repo->create([
            'name'      => $validated['name'],
            'code'      => strtoupper($validated['code']),
            'degree'    => $validated['degree'],
            'is_active' => $validated['is_active'] ?? true,
        ]);
        return ProgramResponseDTO::fromModel($program);
    }

    public function update(int $id, array $validated): ProgramResponseDTO
    {
        $program = $this->repo->findById($id);
        if (! $program) {
            throw new BusinessException("Program ID {$id} tidak ditemukan.", 404);
        }
        $updated = $this->repo->update($program, [
            'name'      => $validated['name'],
            'code'      => strtoupper($validated['code']),
            'degree'    => $validated['degree'],
            'is_active' => $validated['is_active'] ?? $program->is_active,
        ]);
        return ProgramResponseDTO::fromModel($updated);
    }

    public function destroy(int $id): void
    {
        $program = $this->repo->findById($id);
        if (! $program) {
            throw new BusinessException("Program ID {$id} tidak ditemukan.", 404);
        }
        if ($this->repo->hasActiveUsers($program)) {
            throw new BusinessException(
                'Program studi tidak dapat dinonaktifkan karena masih memiliki user aktif.', 409
            );
        }
        $this->repo->deactivate($program);
    }
}