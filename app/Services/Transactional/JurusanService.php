<?php
// app/Services/Transactional/JurusanService.php
namespace App\Services\Transactional;

use App\Exceptions\BusinessException;
use App\Repositories\Transactional\JurusanRepository;
use Illuminate\Support\Facades\DB;

/**
 * JurusanService — satu-satunya jalur tulis untuk master jurusan.
 *
 * Nama jurusan tersimpan di tiga tempat: baris `jurusans` (daftar induk),
 * kolom teks `programs.jurusan`, dan `users.jurusan` (lingkup akses Kajur).
 * Ketiganya harus berubah bersama, jadi seluruh penulisan dikumpulkan di
 * sini alih-alih tersebar di controller.
 */
class JurusanService
{
    private const CONN = 'oltp';

    public function __construct(
        private readonly JurusanRepository $jurusanRepo,
    ) {}

    public function list(): array
    {
        return $this->jurusanRepo->allWithUsage()->map(fn ($row) => [
            'id'            => (int) $row->id,
            'name'          => $row->name,
            'is_active'     => (bool) $row->is_active,
            'program_count' => (int) $row->program_count,
            'user_count'    => (int) $row->user_count,
        ])->all();
    }

    public function create(string $name): array
    {
        $name = trim($name);

        if ($this->jurusanRepo->findByName($name) !== null) {
            throw new BusinessException("Jurusan \"{$name}\" sudah ada.", 422);
        }

        $id = $this->jurusanRepo->insert($name);

        return ['id' => $id, 'name' => $name];
    }

    /**
     * Ganti nama dan/atau status aktif.
     *
     * Penggantian nama merambat ke programs dan users dalam satu transaksi:
     * kalau salah satunya gagal, daftar induk tidak boleh terlanjur berubah
     * sementara pemakainya masih memakai nama lama.
     */
    public function update(int $id, string $name, ?bool $isActive = null): array
    {
        $jurusan = $this->jurusanRepo->find($id);
        if ($jurusan === null) {
            throw new BusinessException('Jurusan tidak ditemukan.', 404);
        }

        $name = trim($name);
        $duplicate = $this->jurusanRepo->findByName($name);
        if ($duplicate !== null && (int) $duplicate->id !== $id) {
            throw new BusinessException("Jurusan \"{$name}\" sudah ada.", 422);
        }

        $affected = ['programs' => 0, 'users' => 0];

        DB::connection(self::CONN)->transaction(function () use ($id, $jurusan, $name, $isActive, &$affected) {
            $attributes = ['name' => $name];
            if ($isActive !== null) {
                $attributes['is_active'] = $isActive;
            }
            $this->jurusanRepo->update($id, $attributes);

            if ($jurusan->name !== $name) {
                $affected = $this->jurusanRepo->renameUsages($jurusan->name, $name);
            }
        });

        return [
            'id'       => $id,
            'name'     => $name,
            'affected' => $affected,
        ];
    }

    /**
     * Hapus jurusan.
     *
     * Ditolak selama masih dipakai. Menghapusnya akan meninggalkan teks
     * yatim di programs.jurusan -- nilainya tetap ada dan tetap tampil di
     * laporan, tapi tidak lagi punya baris induk, sehingga daftar pilihan
     * dan data sebenarnya berbeda tanpa ada yang memberi tahu.
     */
    public function delete(int $id): void
    {
        $jurusan = $this->jurusanRepo->find($id);
        if ($jurusan === null) {
            throw new BusinessException('Jurusan tidak ditemukan.', 404);
        }

        $programs = $this->jurusanRepo->programCount($jurusan->name);
        $users    = $this->jurusanRepo->userCount($jurusan->name);

        if ($programs > 0 || $users > 0) {
            $bagian = [];
            if ($programs > 0) $bagian[] = "{$programs} program studi";
            if ($users > 0)    $bagian[] = "{$users} akun staf";

            throw new BusinessException(
                "Jurusan \"{$jurusan->name}\" masih dipakai " . implode(' dan ', $bagian)
                . '. Pindahkan dulu pemakainya sebelum menghapus.',
                422,
            );
        }

        $this->jurusanRepo->delete($id);
    }
}
