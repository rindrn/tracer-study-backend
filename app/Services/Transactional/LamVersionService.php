<?php
// app/Services/Transactional/LamVersionService.php
namespace App\Services\Transactional;

use App\DTOs\Transactional\LamVersionResponseDTO;
use App\Exceptions\BusinessException;
use App\Http\Validators\LamVersionValidator;
use App\Repositories\Transactional\LamVersionRepository;
use App\Repositories\Transactional\LamRepository;

class LamVersionService
{
    public function __construct(
        private readonly LamVersionRepository $repo,
        private readonly LamRepository        $lamRepo,
        private readonly LamVersionValidator  $validator,
    ) {}

    public function show(int $id): LamVersionResponseDTO
    {
        $row = $this->repo->findById($id);
        if (! $row) throw new BusinessException("LAM Version ID {$id} tidak ditemukan.", 404);
        return LamVersionResponseDTO::fromModel($row);
    }

    public function byLam(int $lamId): array
    {
        $lam = $this->lamRepo->findById($lamId);
        if (! $lam) throw new BusinessException("LAM ID {$lamId} tidak ditemukan.", 404);

        return [
            'lam'      => ['id' => $lam->id, 'name' => $lam->name],
            'versions' => $this->repo->byLam($lamId)
                ->map(fn($v) => ['id' => $v->id, 'year' => $v->year])
                ->toArray(),
        ];
    }

    public function create(array $data): LamVersionResponseDTO
    {
        // Cek duplikat lam_id + year sebelum insert
        $this->validator->assertUniqueVersion($data['lam_id'], $data['year']);

        return LamVersionResponseDTO::fromModel($this->repo->create($data));
    }

    public function update(int $id, array $data): LamVersionResponseDTO
    {
        $existing = $this->repo->findById($id);
        if (! $existing) {
            throw new BusinessException("LAM Version ID {$id} tidak ditemukan.", 404);
        }

        return LamVersionResponseDTO::fromModel($this->repo->update($id, $data));
    }

    public function delete(int $id): void
    {
        if (! $this->repo->findById($id)) {
            throw new BusinessException("LAM Version ID {$id} tidak ditemukan.", 404);
        }
        $this->repo->delete($id);
    }
}