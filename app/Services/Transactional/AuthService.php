<?php
namespace App\Services\Transactional;
 
use App\DTOs\Auth\ResponseAuthDTO; 
use App\Exceptions\BusinessException;
use App\Models\Transactional\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
 
class AuthService
{
    public function __construct(
        private readonly AuditLogService $audit,
    ) {}

    public function login(string $email, string $password): ResponseAuthDTO
    {
        $user = User::where('email', $email)->first();
 
        if (! $user || ! Hash::check($password, $user->password)) {
            // Akun staf memegang akses ke data pribadi seluruh alumni dalam
            // cakupannya, jadi percobaan masuk yang gagal padanya jauh lebih
            // berarti daripada pada akun biasa. Surel yang dicoba ikut
            // dicatat -- itu yang membedakan salah ketik sesekali dari
            // penebakan beruntun terhadap satu akun tertentu.
            $this->audit->record('auth.login_failed', [
                'actor_type'  => 'system',
                'entity_type' => 'users',
                'entity_id'   => $user?->id,
                'context'     => ['email' => $email],
            ]);

            throw ValidationException::withMessages([
                'email' => ['Email atau password salah.'],
            ]);
        }

        // Penonaktifan dilempar sebagai BusinessException 403, BUKAN
        // ValidationException 422 seperti kredensial salah. Halaman masuk
        // menyatukan dua rute -- staf dicoba lebih dulu, lalu jatuh ke rute
        // alumni -- sehingga tanpa status yang berbeda, staf yang akunnya
        // dinonaktifkan ikut jatuh ke rute alumni dan yang terbaca di layar
        // adalah "NIM atau email tidak ditemukan dalam database alumni":
        // sebab sebenarnya tersembunyi, dan pemiliknya mengira akunnya
        // terhapus. Lihat Login.tsx yang menghentikan penerusan pada 403.
        if (! $user->isActive()) {
            throw new BusinessException(
                'Akun Anda telah dinonaktifkan oleh Ketua Tim Tracer Study. Hubungi pengelola untuk mengaktifkannya kembali.',
                403,
            );
        }
 
        // Satu user = satu token aktif
        $user->tokens()->delete();
        $token = $user->createToken('auth_token')->plainTextToken;
 
        $this->audit->record('auth.login', [
            'actor_type'  => 'user',
            'actor_id'    => $user->id,
            'actor_label' => trim("{$user->name} <{$user->email}>"),
            'entity_type' => 'users',
            'entity_id'   => $user->id,
            'context'     => ['role' => $user->role],
        ]);

        // Load relasi program + jurusan/fakultas entity (null kalau tidak relevan)
        $user->load(['program', 'jurusanEntity.programs', 'fakultas.jurusans.programs']);

        return new ResponseAuthDTO(
            userId:           $user->id,
            name:             $user->name,
            email:            $user->email,
            role:             $user->role,
            programId:        $user->program_id,
            programName:      $user->program?->name,
            programCode:      $user->program?->code,
            programDegree:    $user->program?->degree,
            jurusan:          $user->jurusan,
            jurusanName:      $user->jurusanEntity?->name,
            fakultasName:     $user->fakultas?->name,
            fakultasJurusanNames: $user->fakultas?->jurusans->pluck('name')->all() ?? [],
            scopedProgramIds: $user->scopedProgramIds(),
            token:            $token,
        );
    }
 
    public function logout(User $user): void
    {
        $user->currentAccessToken()->delete();
    }

    /**
     * Ganti password staff.
     *
     * Seluruh token lain dicabut setelah password berganti — kalau ada sesi
     * yang bocor, mengganti password harus benar-benar memutusnya, bukan
     * membiarkannya tetap hidup dengan token lama. Token yang sedang dipakai
     * sengaja dipertahankan supaya pengguna tidak terlempar keluar sendiri.
     */
    public function changePassword(User $user, string $currentPassword, string $newPassword): void
    {
        if (! Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Password saat ini salah.'],
            ]);
        }

        if (Hash::check($newPassword, $user->password)) {
            throw ValidationException::withMessages([
                'new_password' => ['Password baru harus berbeda dari password saat ini.'],
            ]);
        }

        // Cast 'hashed' di model User yang melakukan hashing.
        $user->password = $newPassword;
        $user->save();

        $currentTokenId = $user->currentAccessToken()?->id;
        $user->tokens()->when($currentTokenId, fn ($q) => $q->where('id', '!=', $currentTokenId))->delete();
    }
 
    public function me(User $user): array
    {
        $user->load(['program', 'jurusanEntity.programs', 'fakultas.jurusans.programs']);

        return [
            'id'                 => $user->id,
            'name'               => $user->name,
            'email'              => $user->email,
            'role'               => $user->role,
            'program_id'         => $user->program_id,
            'program_name'       => $user->program?->name,
            'program_code'       => $user->program?->code,
            'program_degree'     => $user->program?->degree,
            'jurusan'            => $user->jurusan,
            'jurusan_name'       => $user->jurusanEntity?->name,
            'fakultas_name'      => $user->fakultas?->name,
            'fakultas_jurusan_names' => $user->fakultas?->jurusans->pluck('name')->all() ?? [],
            'scoped_program_ids' => $user->scopedProgramIds(),
        ];
    }
}
