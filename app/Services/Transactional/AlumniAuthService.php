<?php
// app/Services/Transactional/AlumniAuthService.php
namespace App\Services\Transactional;

use App\Exceptions\BusinessException;
use App\Models\Transactional\AlumniProfile;
use App\Repositories\Transactional\AlumniProfileRepository;
use Illuminate\Support\Facades\Hash;

/**
 * AlumniAuthService — logic autentikasi alumni (pengisi kuesioner).
 *
 * Pengenalnya NIM atau surel, kata sandinya dicincang di kolom
 * `alumni_profiles.password` dan diterbitkan AlumniCredentialService.
 *
 * DULU: kata sandinya adalah NIM itu sendiri, dengan enam digit terakhir NIK
 * sebagai cadangan — tidak ada yang tersimpan, tidak ada yang dicincang.
 * Artinya pengenal dan kata sandi adalah benda yang sama, dan bendanya bukan
 * rahasia: NIM tercetak di ijazah dan ada di setiap berkas impor, sementara
 * NIK ikut tertulis di lembar ekspor kementerian sehingga setiap staf yang
 * berwenang mengekspor memegang kata sandi cadangan seluruh alumni dalam
 * cakupannya. NIM juga berpola tahun + nomor urut, jadi mudah dienumerasi.
 *
 * Pengenal TETAP boleh NIM maupun surel. Yang berbahaya dulu adalah NIM
 * sebagai kata sandi, bukan sebagai pengenal — begitu kata sandinya acak, NIM
 * kembali menjadi nama pengguna biasa yang memang tidak perlu rahasia, dan
 * membuangnya hanya menyulitkan alumni yang lupa surel mana yang terdaftar.
 *
 * Login berhasil menerbitkan token Sanctum pada guard 'alumni'. Token itulah
 * satu-satunya cara memanggil POST /api/tracer-study/submit.
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
        private readonly ConsentService          $consent,
        private readonly AuditLogService         $audit,
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
            throw new BusinessException(
                'Akun Anda telah dinonaktifkan oleh Tim Tracer Study. Hubungi pengelola untuk mengaktifkannya kembali.',
                403,
            );
        }

        // Alumni yang kredensialnya belum pernah diterbitkan dibedakan dari
        // kata sandi salah, karena jalan keluarnya berbeda: yang satu harus
        // meminta ke Tim Tracer, yang lain cukup mengetik ulang. Menyamakan
        // keduanya membuat alumni mencoba berulang kali sampai terkena
        // pembatas laju, padahal tidak ada kata sandi yang bisa benar.
        if (empty($alumni->password)) {
            throw new BusinessException(
                'Akun Anda belum memiliki kata sandi. Silakan hubungi Tim Tracer Study untuk memperoleh kredensial masuk.',
                401,
            );
        }

        if (!Hash::check($password, $alumni->password)) {
            // Percobaan gagal dicatat dengan pelaku eksplisit: belum ada sesi
            // pada titik ini, jadi resolveActor() tidak punya apa pun untuk
            // disimpulkan. Yang dicatat identitas yang DICOBA, bukan yang
            // terbukti — itulah gunanya bagi penelusuran serangan tebak sandi.
            $this->audit->record('auth.alumni_login_failed', [
                'actor_type'        => 'system',
                'entity_type'       => 'alumni_profiles',
                'entity_id'         => $alumni->id,
                'subject_alumni_id' => $alumni->id,
                'context'           => ['identifier' => $identifier],
            ]);

            throw new BusinessException('Kata sandi salah.', 401);
        }

        // Satu alumni = satu token aktif, sama seperti AuthService staff.
        $model = AlumniProfile::findOrFail($alumni->id);
        $model->tokens()->delete();
        $token = $model->createToken(
            'alumni_token',
            ['alumni'],
            now()->addHours(self::TOKEN_TTL_HOURS),
        )->plainTextToken;

        $this->audit->record('auth.alumni_login', [
            'actor_type'        => 'alumni',
            'actor_id'          => $alumni->id,
            'actor_label'       => trim("{$alumni->name} ({$alumni->nim})"),
            'entity_type'       => 'alumni_profiles',
            'entity_id'         => $alumni->id,
            'subject_alumni_id' => $alumni->id,
        ]);

        return [
            'token'           => $token,
            'id'              => $alumni->id,
            'nim'             => $alumni->nim,
            'name'            => $alumni->name,
            'email'           => $alumni->email,
            'phone'           => $alumni->phone,
            // NIK dan NPWP ikut karena keduanya dipakai mengisi ulang bagian
            // identitas formulir. Nilainya tidak pernah tersimpan di
            // response_answers (IDENTITY_KEYS membuangnya — tempatnya memang di
            // alumni_profiles), jadi tanpa dikirim di sini isiannya kembali
            // kosong setiap kali pengisian dimuat ulang, termasuk setelah
            // Ketua Tracer mengembalikan status ke Ongoing.
            'nik'             => $alumni->nik,
            'npwp'            => $alumni->npwp,
            'program_id'      => $alumni->program_id,
            'program_name'    => $alumni->program_name,
            'program_code'    => $alumni->program_code,
            'program_degree'  => $alumni->program_degree,
            'entry_year'      => $alumni->entry_year,
            'graduation_year' => $alumni->graduation_year,
            // Keadaan persetujuan ikut di respons masuk supaya frontend bisa
            // membuka layar persetujuan SEBELUM formulir dimuat. Tanpa ini
            // alumni baru tahu persetujuannya kurang setelah selesai mengisi
            // dan ditolak 451 — seluruh pekerjaannya terbuang, dan itu
            // langsung memukul response rate yang justru jadi tolok ukur
            // keberhasilan sistem ini.
            'consent'         => $this->consent->state($alumni),
        ];
    }

    /** Cabut seluruh token milik alumni (dipanggil saat keluar sesi). */
    public function logout(AlumniProfile $alumni): void
    {
        $alumni->tokens()->delete();
    }

}
