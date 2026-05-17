<?php
// app/Services/Transactional/LamService.php
namespace App\Services\Transactional;

use App\DTOs\Transactional\LamResponseDTO;
use App\Exceptions\BusinessException;
use App\Repositories\Transactional\LamRepository;
use App\Repositories\Transactional\LamProgramRepository;

class LamService
{
    public function __construct(
        private readonly LamRepository        $repo,
        private readonly LamProgramRepository $programRepo,
    ) {}

    public function list(array $include = []): array
    {
        return $this->repo->all($include)
            ->map(function ($lam) use ($include) {
                $data = [
                    'id'         => $lam->id,
                    'name'       => $lam->name,
                    'code'       => $lam->code,
                    'created_at' => $lam->created_at,
                ];

                if (in_array('versions', $include)) {
                    $data['versions'] = collect($lam->versions ?? [])
                        ->map(fn($v) => [
                            'id'           => $v->id,
                            'year'         => $v->year,
                            'version_name' => $v->version_name,
                            'is_active'    => (bool) $v->is_active,
                        ])->toArray();
                }

                if (in_array('programs', $include)) {
                    $data['programs'] = collect($lam->programs ?? [])
                        ->map(fn($p) => [
                            'id'     => $p->id,
                            'name'   => $p->name,
                            'code'   => $p->code,
                            'degree' => $p->degree,
                        ])->toArray();
                }

                if (in_array('thresholds', $include)) {
                    $data['thresholds'] = $lam->thresholds ?? [];
                }

                return $data;
            })
            ->toArray();
    }

    public function show(int $id): LamResponseDTO
    {
        $lam = $this->repo->findById($id);
        if (! $lam) throw new BusinessException("LAM ID {$id} tidak ditemukan.", 404);

        $programs = $this->programRepo->byLam($id)->toArray();
        return LamResponseDTO::fromRow($lam, $programs);
    }

    public function full(int $id, int $year): array
    {
        $row = $this->repo->fullDetail($id, $year);
        if (! $row) throw new BusinessException("Data LAM ID {$id} tahun {$year} tidak ditemukan.", 404);

        // thresholds dari view sudah JSON, group per indicator di sini
        $rawThresholds = json_decode($row->thresholds ?? '[]', true);
        $grouped = collect($rawThresholds)
            ->groupBy('indicator_id')
            ->map(function ($items) {
                $first = $items->first();
                $result = [
                    'indicator_id'   => $first['indicator_id'],
                    'indicator_key'  => $first['indicator_key'],
                    'indicator_name' => $first['indicator_name'],
                    'unit'           => $first['unit'],
                    'operator'       => $first['operator'],
                ];
                foreach ($items as $item) {
                    $result[$item['level']] = [
                        'threshold_id' => $item['threshold_id'],
                        'value'        => $item['value'],
                    ];
                }
                return $result;
            })
            ->values()
            ->toArray();

        return [
            'lam'        => ['id' => $row->lam_id, 'name' => $row->lam_name, 'code' => $row->lam_code],
            'version'    => ['id' => $row->lam_version_id, 'year' => $row->year],
            'programs'   => json_decode($row->programs ?? '[]', true),
            'thresholds' => $grouped,
        ];
    }

    public function create(array $data): LamResponseDTO
    {
        $lam      = $this->repo->create($data, $data['program_ids'] ?? []);
        $programs = $this->programRepo->byLam($lam->id)->toArray();
        return LamResponseDTO::fromRow($lam, $programs);
    }

    public function update(int $id, array $data): LamResponseDTO
    {
        if (! $this->repo->findById($id)) {
            throw new BusinessException("LAM ID {$id} tidak ditemukan.", 404);
        }
        $lam      = $this->repo->update($id, $data);
        $programs = $this->programRepo->byLam($id)->toArray();
        return LamResponseDTO::fromRow($lam, $programs);
    }

    public function delete(int $id): void
    {
        if (! $this->repo->findById($id)) {
            throw new BusinessException("LAM ID {$id} tidak ditemukan.", 404);
        }
        $this->repo->delete($id);
    }
}