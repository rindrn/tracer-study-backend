<?php
// app/Services/Transactional/LamVersionService.php
namespace App\Services\Transactional;

use App\DTOs\Transactional\LamVersionResponseDTO;
use App\Exceptions\BusinessException;
use App\Http\Validators\LamVersionValidator;
use App\Repositories\Transactional\LamVersionRepository;
use App\Repositories\Transactional\LamRepository;
use App\Traits\WithCache;

class LamVersionService
{
    use WithCache;

    private const TTL = 1800; // 10 menit

    public function __construct(
        private readonly LamVersionRepository $repo,
        private readonly LamRepository        $lamRepo,
        private readonly LamVersionValidator  $validator,
    ) {}

    public function show(int $id): LamVersionResponseDTO
    {
        $row = $this->remember("lam_versions:show:{$id}", function () use ($id) {
            return $this->repo->findById($id);
        }, self::TTL);

        if (! $row) throw new BusinessException("LAM Version ID {$id} tidak ditemukan.", 404);
        return LamVersionResponseDTO::fromModel($row);
    }

    public function byLam(int $lamId): array
    {
        $lam = $this->lamRepo->findById($lamId);
        if (! $lam) throw new BusinessException("LAM ID {$lamId} tidak ditemukan.", 404);

        $versions = $this->remember("lam_versions:by_lam:{$lamId}", function () use ($lamId) {
            return $this->repo->byLam($lamId)
                ->map(fn($v) => ['id' => $v->id, 'year' => $v->year])
                ->toArray();
        }, self::TTL);

        return [
            'lam'      => ['id' => $lam->id, 'name' => $lam->name],
            'versions' => $versions,
        ];
    }

    public function create(array $data): LamVersionResponseDTO
    {
        $this->validator->assertUniqueVersion($data['lam_id'], $data['year']);

        $result = LamVersionResponseDTO::fromModel($this->repo->create($data));

        // Bust cache list versi milik LAM ini + cache LAM
        $this->forget("lam_versions:by_lam:{$data['lam_id']}");
        $this->forgetTag('lams');

        return $result;
    }

    public function update(int $id, array $data): LamVersionResponseDTO
    {
        $existing = $this->repo->findById($id);
        if (! $existing) {
            throw new BusinessException("LAM Version ID {$id} tidak ditemukan.", 404);
        }

        $result = LamVersionResponseDTO::fromModel($this->repo->update($id, $data));

        $this->forget(
            "lam_versions:show:{$id}",
            "lam_versions:by_lam:{$existing->lam_id}",
        );
        $this->forgetTag('lams', 'thresholds');

        return $result;
    }

    public function delete(int $id): void
    {
        $existing = $this->repo->findById($id);
        if (! $existing) {
            throw new BusinessException("LAM Version ID {$id} tidak ditemukan.", 404);
        }

        $this->repo->delete($id);

        $this->forget(
            "lam_versions:show:{$id}",
            "lam_versions:by_lam:{$existing->lam_id}",
        );
        $this->forgetTag('lams', 'thresholds');
    }
}