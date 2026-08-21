<?php
namespace App\DTOs\Auth;

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
        public readonly ?string $jurusan,
        /** Nama Jurusan entity yang dipimpin (role kajur); null untuk role lain. */
        public readonly ?string $jurusanName,
        /** Nama Fakultas entity yang dipimpin (role ketua_fakultas); null untuk role lain. */
        public readonly ?string $fakultasName,
        /**
         * Nama-nama Jurusan anggota fakultas yang dipimpin (role
         * ketua_fakultas); array kosong untuk role lain. Dashboard Cube.js
         * (Overview/Employment/Education/KPI) masih mewajibkan Ketua
         * Fakultas memilih SATU jurusan dari daftar ini sebelum melihat
         * data -- lihat catatan kompatibilitas di
         * EnforcesProdiScope::scopedParams().
         */
        public readonly array   $fakultasJurusanNames,
        /**
         * Daftar program_id dalam cakupan (dari User::scopedProgramIds()) --
         * array kosong untuk role tanpa batasan prodi (canAccessAll()).
         * Dipakai FE mempersempit opsi dropdown prodi/jurusan tanpa perlu
         * derive ulang dari programs.jurusan (lihat Fase 5).
         */
        public readonly array   $scopedProgramIds,
        public readonly string  $token,
    ) {}

    public function toArray(): array
    {
        return [
            'user' => [
                'id'                 => $this->userId,
                'name'               => $this->name,
                'email'              => $this->email,
                'role'               => $this->role,
                'program_id'         => $this->programId,
                'program_name'       => $this->programName,
                'program_code'       => $this->programCode,
                'program_degree'     => $this->programDegree,
                'jurusan'            => $this->jurusan,
                'jurusan_name'       => $this->jurusanName,
                'fakultas_name'      => $this->fakultasName,
                'fakultas_jurusan_names' => $this->fakultasJurusanNames,
                'scoped_program_ids' => $this->scopedProgramIds,
            ],
            'token'      => $this->token,
            'token_type' => 'Bearer',
        ];
    }
}
