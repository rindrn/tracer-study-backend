<?php
// app/Services/Transactional/AlumniAuthService.php
namespace App\Services\Transactional;

use App\Exceptions\BusinessException;
use App\Models\Transactional\AlumniProfile;
use App\Repositories\Transactional\AlumniProfileRepository;

/**
 * AlumniAuthService — logic autentikasi alumni (pengisi kuesioner).
 *
 * Alumni tidak menyimpan password di DB; verifikasi memakai NIM atau
 * 6 digit terakhir NIK sebagai "password default".
 *
 * Login berhasil menerbitkan token Sanctum pada guard 'alumni'. Token itulah
 * satu-satunya cara memanggil POST /api/tracer-study/submit — sebelumnya
 * endpoint tersebut publik, sehingga siapa pun bisa mengirim jawaban atas nama
 * NIM mana pun tanpa login sama sekali.
 */
class AlumniAuthService
{
    /**
     * Masa berlaku token alumni. Pengisian kuesioner selesai dalam satu sesi
     * (paling lama beberapa jam), jadi token tidak perlu hidup lama. Ini juga
     * menahan penumpukan baris personal_access_tokens dari login berulang.
     */
    private const TOKEN_TTL_HOURS = 12;

    public function __construct(
        private readonly AlumniProfileRepository $alumniRepo,
    ) {}

    /**
     * Verifikasi kredensial alumni, terbitkan token, kembalikan profil lengkap.
     *
     * @return array profil alumni + kunci 'token' berisi plain text token
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

        // Satu alumni = satu token aktif, sama seperti AuthService staff.
        $model = AlumniProfile::findOrFail($alumni->id);
        $model->tokens()->delete();
        $token = $model->createToken(
            'alumni_token',
            ['alumni'],
            now()->addHours(self::TOKEN_TTL_HOURS),
        )->plainTextToken;

        return [
            'token'           => $token,
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

    /** Cabut seluruh token milik alumni (dipanggil saat keluar sesi). */
    public function logout(AlumniProfile $alumni): void
    {
        $alumni->tokens()->delete();
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
