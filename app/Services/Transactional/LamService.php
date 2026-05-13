<?php
// app/Services/Transactional/LamService.php
namespace App\Services\Transactional;

use App\DTOs\Transactional\LamResponseDTO;
use App\Exceptions\BusinessException;
use App\Repositories\Transactional\LamRepository;

class LamService
{
    public function __construct(private readonly LamRepository $repo) {}

    public function list(): array
    {
        return $this->repo->all()
            ->map(fn($row) => LamResponseDTO::fromModel($row)->toArray())
            ->toArray();
    }

    public function show(int $id): LamResponseDTO
    {
        $row = $this->repo->findById($id);
        if (! $row) throw new BusinessException("LAM ID {$id} tidak ditemukan.", 404);
        return LamResponseDTO::fromModel($row);
    }

    public function full(int $id, int $year): array
    {
        $row = $this->repo->fullDetail($id, $year);
        if (! $row) throw new BusinessException("Data LAM ID {$id} tahun {$year} tidak ditemukan.", 404);

        return [
            'lam' => [
                'id'   => $row->lam_id,
                'name' => $row->lam_name,
                'code' => $row->lam_code,
            ],
            'version' => [
                'id'           => $row->lam_version_id,
                'year'         => $row->year,
                'version_name' => $row->version_name,
                'is_active'    => $row->is_active,
            ],
            'programs'   => json_decode($row->programs ?? '[]', true),
            'thresholds' => json_decode($row->thresholds ?? '[]', true),
        ];
    }

    public function create(array $data): LamResponseDTO
    {
        return LamResponseDTO::fromModel($this->repo->create($data));
    }

    public function update(int $id, array $data): LamResponseDTO
    {
        if (! $this->repo->findById($id)) {
            throw new BusinessException("LAM ID {$id} tidak ditemukan.", 404);
        }
        return LamResponseDTO::fromModel($this->repo->update($id, $data));
    }

    public function delete(int $id): void
    {
        if (! $this->repo->findById($id)) {
            throw new BusinessException("LAM ID {$id} tidak ditemukan.", 404);
        }
        $this->repo->delete($id);
    }
}