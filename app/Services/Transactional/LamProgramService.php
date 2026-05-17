<?php
// app/Services/Transactional/LamProgramService.php
namespace App\Services\Transactional;

use App\Exceptions\BusinessException;
use App\Repositories\Transactional\LamProgramRepository;
use App\Repositories\Transactional\LamRepository;

class LamProgramService
{
    public function __construct(
        private readonly LamProgramRepository $repo,
        private readonly LamRepository        $lamRepo,
    ) {}

    public function byLam(int $lamId): array
    {
        $lam = $this->lamRepo->findById($lamId);
        if (! $lam) throw new BusinessException("LAM ID {$lamId} tidak ditemukan.", 404);

        return [
            'lam'      => ['id' => $lam->id, 'name' => $lam->name],
            'programs' => $this->repo->byLam($lamId)->toArray(),
        ];
    }

    public function syncAdd(array $data): array
    {
        $lam = $this->lamRepo->findById($data['lam_id']);
        if (! $lam) throw new BusinessException("LAM ID {$data['lam_id']} tidak ditemukan.", 404);

        $this->repo->syncAdd($data['lam_id'], $data['program_ids']);

        return [
            'lam'      => ['id' => $lam->id, 'name' => $lam->name],
            'programs' => $this->repo->byLam($data['lam_id'])->toArray(),
        ];
    }

    public function detach(array $data): void
    {
        $lam = $this->lamRepo->findById($data['lam_id']);
        if (! $lam) throw new BusinessException("LAM ID {$data['lam_id']} tidak ditemukan.", 404);

        $this->repo->detach($data['lam_id'], $data['program_id']);
    }
}