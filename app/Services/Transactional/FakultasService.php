<?php
// app/Services/Transactional/FakultasService.php
namespace App\Services\Transactional;

use App\Exceptions\BusinessException;
use App\Repositories\Transactional\FakultasRepository;

/**
 * FakultasService — satu-satunya jalur tulis untuk master Fakultas dan
 * keanggotaan jurusannya (fakultas_jurusan_scopes).
 *
 * Dipakai jalur otorisasi Dekan: User::scopedProgramIds()
 * merangkai fakultas->jurusans->programs jadi satu daftar program_id.
 */
class FakultasService
{
    public function __construct(
        private readonly FakultasRepository $repo,
    ) {}

    public function list(): array
    {
        return $this->repo->allWithUsage()->map(fn ($row) => [
            'id'            => (int) $row->id,
            'name'          => $row->name,
            'is_active'     => (bool) $row->is_active,
            'jurusan_count' => (int) $row->jurusan_count,
            'user_count'    => (int) $row->user_count,
            'jurusan_ids'   => $this->repo->scopedJurusanIds((int) $row->id),
        ])->all();
    }

    public function create(string $name): array
    {
        $name = trim($name);

        if ($this->repo->findByName($name) !== null) {
            throw new BusinessException("Fakultas \"{$name}\" sudah ada.", 422);
        }

        $id = $this->repo->insert($name);

        return ['id' => $id, 'name' => $name];
    }

    public function update(int $id, string $name, ?bool $isActive = null): array
    {
        $fakultas = $this->repo->find($id);
        if ($fakultas === null) {
            throw new BusinessException('Fakultas tidak ditemukan.', 404);
        }

        $name      = trim($name);
        $duplicate = $this->repo->findByName($name);
        if ($duplicate !== null && (int) $duplicate->id !== $id) {
            throw new BusinessException("Fakultas \"{$name}\" sudah ada.", 422);
        }

        $attributes = ['name' => $name];
        if ($isActive !== null) {
            $attributes['is_active'] = $isActive;
        }
        $this->repo->update($id, $attributes);

        return ['id' => $id, 'name' => $name];
    }

    /**
     * Hapus fakultas. Ditolak selama masih dipakai akun Dekan --
     * menghapusnya akan melepas fakultas_id akun itu jadi NULL (FK
     * nullOnDelete) dan diam-diam membuatnya kehilangan seluruh scope.
     */
    public function delete(int $id): void
    {
        $fakultas = $this->repo->find($id);
        if ($fakultas === null) {
            throw new BusinessException('Fakultas tidak ditemukan.', 404);
        }

        $users = $this->repo->userCount($id);
        if ($users > 0) {
            throw new BusinessException(
                "Fakultas \"{$fakultas->name}\" masih dipakai {$users} akun staf. Pindahkan dulu pemakainya sebelum menghapus.",
                422,
            );
        }

        $this->repo->delete($id);
    }

    /** @return array<int> */
    public function scopedJurusanIds(int $id): array
    {
        if ($this->repo->find($id) === null) {
            throw new BusinessException('Fakultas tidak ditemukan.', 404);
        }

        return $this->repo->scopedJurusanIds($id);
    }

    public function syncScopedJurusans(int $id, array $jurusanIds): array
    {
        if ($this->repo->find($id) === null) {
            throw new BusinessException('Fakultas tidak ditemukan.', 404);
        }

        $this->repo->syncJurusans($id, $jurusanIds);

        return ['id' => $id, 'jurusan_ids' => $this->repo->scopedJurusanIds($id)];
    }
}
