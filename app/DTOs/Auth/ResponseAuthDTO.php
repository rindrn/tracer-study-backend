<?php
namespace App\DTOs\Auth;
 
// ── Response setelah login berhasil ──────────────────────────
class ResponseAuthDTO
{
    public function __construct(
        public readonly int     $userId,
        public readonly string  $name,
        public readonly string  $email,
        public readonly string  $role,
        public readonly ?int    $programId,
        public readonly ?string $programName,
        public readonly ?string $programCode,
        public readonly ?string $programDegree,
        public readonly string  $token,
    ) {}
 
    public function toArray(): array
    {
        return [
            'user' => [
                'id'             => $this->userId,
                'name'           => $this->name,
                'email'          => $this->email,
                'role'           => $this->role,
                'program_id'     => $this->programId,
                'program_name'   => $this->programName,
                'program_code'   => $this->programCode,
                'program_degree' => $this->programDegree,
            ],
            'token'      => $this->token,
            'token_type' => 'Bearer',
        ];
    }
}
