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
 * Login berhasil menerbitkan token Sanctum pada guard 'alumni' (lihat
 * config/auth.php). Token itu wajib dikirim saat submit kuesioner —
 * POST /api/tracer-study/submit tidak lagi publik.
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

    /**
     * POST /api/auth/alumni-logout — cabut token alumni yang sedang dipakai.
     *
     * Berada di belakang middleware 'auth:alumni', jadi $request->user()
     * dijamin instance AlumniProfile.
     */
    public function logout(Request $request): JsonResponse
    {
        $this->service->logout($request->user('alumni'));

        return response()->json([
            'success' => true,
            'message' => 'Logout alumni berhasil.',
        ]);
    }
}
