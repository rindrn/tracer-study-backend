<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Services\Transactional\AlumniAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
    public function __construct(
        private readonly AlumniAuthService $service,
    ) {}

    /**
     * POST /api/auth/alumni-login
     *
     * Body: { "nim_or_email": "...", "password": "..." }
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'nim_or_email' => ['required', 'string'],
            'password'     => ['required', 'string'],
        ]);

        $alumni = $this->service->login(
            identifier: $request->input('nim_or_email'),
            password:   $request->input('password'),
        );

        return response()->json([
            'success' => true,
            'message' => 'Login alumni berhasil.',
            'data'    => $alumni,
        ]);
    }
}
