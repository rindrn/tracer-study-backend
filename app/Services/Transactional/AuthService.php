<?php
namespace App\Services\Transactional;
 
use App\DTOs\Auth\ResponseAuthDTO; 
use App\Models\Transactional\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
 
class AuthService
{
    public function login(string $email, string $password): ResponseAuthDTO
    {
        $user = User::where('email', $email)->first();
 
        if (! $user || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Email atau password salah.'],
            ]);
        }
 
        // Satu user = satu token aktif
        $user->tokens()->delete();
        $token = $user->createToken('auth_token')->plainTextToken;
 
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
            token:         $token,
        );
    }
 
    public function logout(User $user): void
    {
        $user->currentAccessToken()->delete();
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
        ];
    }
}
