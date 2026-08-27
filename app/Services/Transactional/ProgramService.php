<?php
// app/Services/Transactional/ProgramService.php
namespace App\Services\Transactional;

use App\DTOs\Transactional\ProgramResponseDTO;
use App\Exceptions\BusinessException;
use App\Repositories\Transactional\ProgramRepository;
use App\Traits\WithCache;

class ProgramService
{
    use WithCache;

    private const TTL = 600; // 10 menit

    public function __construct(
        private readonly ProgramRepository $repo,
    ) {}

    public function list(bool $includeInactive, ?string $degree): array
    {
        $key = 'programs:list:' . ($includeInactive ? '1' : '0') . ':' . ($degree ?? 'all');

        // Tag WAJIB: key daftar bercabang per (includeInactive, degree),
        // jadi tidak ada satu nama key yang bisa di-forget() satu per satu
        // setelah prodi berubah. Tanpa tag, forgetTag('programs') di
        // create/update/destroy tidak menyentuh key ini sama sekali dan
        // daftar prodi tetap menampilkan kode lama sampai TTL habis.
        return $this->remember($key, function () use ($includeInactive, $degree) {
            return $this->repo
                ->all($includeInactive, $degree)
                ->map(fn($p) => ProgramResponseDTO::fromModel($p)->toArray())
                ->toArray();
        }, self::TTL, ['programs']);
    }

    public function show(int $id): ProgramResponseDTO
    {
        $program = $this->remember("programs:show:{$id}", function () use ($id) {
            return $this->repo->findById($id);
        }, self::TTL, ['programs']);

        if (! $program) {
            throw new BusinessException("Program ID {$id} tidak ditemukan.", 404);
        }
        return ProgramResponseDTO::fromModel($program);
    }

    /**
     * Kode PDDIKTI dirapikan seragam sebelum disimpan: spasi di ujung dibuang
     * dan isian kosong dijadikan null. Tanpa ini string kosong dari form
     * tersimpan apa adanya, dan ekspor format=code mengira kodenya ADA lalu
     * mengirim sel kosong ke portal alih-alih jatuh ke kode internal.
     */
    private function normalizeDiktiCode(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    public function create(array $validated): ProgramResponseDTO
    {
        $program = $this->repo->create([
            'name'       => $validated['name'],
            'code'       => strtoupper($validated['code']),
            'dikti_code' => $this->normalizeDiktiCode($validated['dikti_code'] ?? null),
            'jurusan'    => $validated['jurusan'] ?? null,
            'degree'    => $validated['degree'],
            'is_active' => $validated['is_active'] ?? true,
            'accreditation'    => $validated['accreditation'] ?? null,
            'accredited_until' => $validated['accredited_until'] ?? null,
        ]);

        $this->forgetTag('programs');

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
            // array_key_exists, bukan ??: mengosongkan kode PDDIKTI berarti
            // mengirim null secara sengaja, dan itu harus tersimpan. Kunci
            // yang memang tidak dikirim baru mempertahankan nilai lama.
            'dikti_code' => array_key_exists('dikti_code', $validated)
                ? $this->normalizeDiktiCode($validated['dikti_code'])
                : $program->dikti_code,
            'jurusan'   => array_key_exists('jurusan', $validated)
                ? $validated['jurusan']
                : $program->jurusan,
            'degree'    => $validated['degree'],
            'is_active' => $validated['is_active'] ?? $program->is_active,
            // ?? mempertahankan nilai lama saat kunci tidak dikirim; itu yang
            // membuat form yang belum punya isian akreditasi tidak menghapus
            // data yang sudah ada. Mengosongkan tetap bisa, dengan mengirim
            // null secara eksplisit.
            'accreditation'    => $validated['accreditation'] ?? $program->accreditation,
            'accredited_until' => $validated['accredited_until'] ?? $program->accredited_until,
        ]);

        // Cukup forgetTag: key show sekarang bertag 'programs', dan forget()
        // polos tidak menyentuh key yang disimpan di namespace tag.
        $this->forgetTag('programs');

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

        $this->forgetTag('programs');
    }
}