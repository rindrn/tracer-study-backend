<?php
// app/Services/Transactional/AlumniAuthService.php
namespace App\Services\Transactional;

use App\Exceptions\BusinessException;
use App\Repositories\Transactional\AlumniProfileRepository;

/**
 * AlumniAuthService — logic autentikasi alumni (pengisi kuesioner).
 *
 * Alumni tidak menyimpan password di DB; verifikasi memakai NIM atau
 * 6 digit terakhir NIK sebagai "password default". Service ini berdiri
 * sendiri (tidak menggunakan Sanctum) karena sesi alumni ringan.
 */
class AlumniAuthService
{
    public function __construct(
        private readonly AlumniProfileRepository $alumniRepo,
    ) {}

    /**
     * Verifikasi kredensial alumni dan kembalikan profil lengkap.
     *
     * @throws BusinessException 401 jika NIM/email tidak ketemu atau password salah
     * @throws BusinessException 403 jika alumni nonaktif
     */
    public function login(string $identifier, string $password): array
    {
        $alumni = $this->alumniRepo->findByNimOrEmailWithProgram($identifier);

        if (!$alumni) {
            throw new BusinessException('NIM atau email tidak ditemukan dalam database alumni.', 401);
        }

        if (!$alumni->is_active) {
            throw new BusinessException('Akun alumni tidak aktif. Hubungi admin.', 403);
        }

        if (!$this->isValidPassword($alumni, $password)) {
            throw new BusinessException('Password salah. Gunakan NIM Anda sebagai password.', 401);
        }

        return [
            'id'              => $alumni->id,
            'nim'             => $alumni->nim,
            'name'            => $alumni->name,
            'email'           => $alumni->email,
            'phone'           => $alumni->phone,
            'program_id'      => $alumni->program_id,
            'program_name'    => $alumni->program_name,
            'program_code'    => $alumni->program_code,
            'program_degree'  => $alumni->program_degree,
            'entry_year'      => $alumni->entry_year,
            'graduation_year' => $alumni->graduation_year,
        ];
    }

    /**
     * Password default yang diterima:
     * - NIM (case sensitive & lowercase)
     * - 6 digit terakhir NIK (kalau NIK tersedia)
     */
    private function isValidPassword(object $alumni, string $password): bool
    {
        $valid = [
            $alumni->nim,
            strtolower($alumni->nim),
        ];

        if ($alumni->nik) {
            $valid[] = substr($alumni->nik, -6);
        }

        return in_array($password, $valid, strict: true);
    }
}
