<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Login endpoint untuk alumni/mahasiswa yang ingin mengisi kuesioner.
 * 
 * Alumni tidak memiliki password di database. Verifikasi dilakukan
 * dengan mencocokkan NIM + email sebagai faktor autentikasi ringan.
 * 
 * Ini BUKAN Sanctum token-based auth — hanya mengembalikan profil alumni
 * jika verifikasi berhasil, supaya frontend bisa menyimpan session ringan.
 */
class AlumniAuthController extends Controller
{
    /**
     * POST /api/auth/alumni-login
     * 
     * Body: { "nim": "211511001", "email": "xxx@student.polban.ac.id" }
     * Atau : { "nim": "211511001", "password": "211511001" }   ← NIM sebagai password default
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'nim_or_email' => ['required', 'string'],
            'password'     => ['required', 'string'],
        ]);

        $identifier = $request->input('nim_or_email');
        $password   = $request->input('password');

        // Cari alumni berdasarkan NIM atau email
        $alumni = DB::connection('oltp')->table('alumni_profiles')
            ->leftJoin('programs', 'alumni_profiles.program_id', '=', 'programs.id')
            ->select(
                'alumni_profiles.id',
                'alumni_profiles.nim',
                'alumni_profiles.name',
                'alumni_profiles.email',
                'alumni_profiles.phone',
                'alumni_profiles.program_id',
                'alumni_profiles.entry_year',
                'alumni_profiles.graduation_year',
                'alumni_profiles.is_active',
                'alumni_profiles.nik',
                'programs.name as program_name',
                'programs.code as program_code',
                'programs.degree as program_degree',
            )
            ->where(function ($q) use ($identifier) {
                $q->where('alumni_profiles.nim', $identifier)
                  ->orWhere('alumni_profiles.email', $identifier);
            })
            ->first();

        if (!$alumni) {
            return response()->json([
                'success' => false,
                'message' => 'NIM atau email tidak ditemukan dalam database alumni.',
            ], 401);
        }

        if (!$alumni->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Akun alumni tidak aktif. Hubungi admin.',
            ], 403);
        }

        // Verifikasi password:
        // Strategi: NIM digunakan sebagai password default.
        // Alternatif: bisa juga pakai 6 digit terakhir NIK.
        $validPasswords = [
            $alumni->nim,                                    // NIM sebagai password
            strtolower($alumni->nim),                        // NIM lowercase
        ];

        if ($alumni->nik) {
            $validPasswords[] = substr($alumni->nik, -6);    // 6 digit terakhir NIK
        }

        if (!in_array($password, $validPasswords, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Password salah. Gunakan NIM Anda sebagai password.',
            ], 401);
        }

        return response()->json([
            'success' => true,
            'message' => 'Login alumni berhasil.',
            'data'    => [
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
            ],
        ]);
    }
}
