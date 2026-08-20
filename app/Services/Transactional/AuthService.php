<?php
namespace App\Services\Transactional;
 
use App\DTOs\Auth\ResponseAuthDTO; 
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

        if (! $user->isActive()) {
            throw ValidationException::withMessages([
                'email' => ['Akun Anda telah dinonaktifkan. Hubungi administrator.'],
            ]);
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

        // Load relasi program (null jika admin/p2mpp)
        $user->load('program');
 
        return new ResponseAuthDTO(
            userId:        $user->id,
            name:          $user->name,
            email:         $user->email,
            role:          $user->role,
            programId:     $user->program_id,
            programName:   $user->program?->name,
            programCode:   $user->program?->code,
            programDegree: $user->program?->degree,
            jurusan:       $user->jurusan,
            token:         $token,
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
        $user->load('program');
 
        return [
            'id'             => $user->id,
            'name'           => $user->name,
            'email'          => $user->email,
            'role'           => $user->role,
            'program_id'     => $user->program_id,
            'program_name'   => $user->program?->name,
            'program_code'   => $user->program?->code,
            'program_degree' => $user->program?->degree,
            'jurusan'        => $user->jurusan,
        ];
    }
}
