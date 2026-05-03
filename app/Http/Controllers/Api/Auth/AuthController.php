<?php
namespace App\Http\Controllers\Api\Auth;
 
use App\Http\Controllers\Controller;
use App\Services\Transactional\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
 
class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $service,
    ) {}
 
    // GET /api/auth/demo-accounts
    public function demoAccounts(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->service->demoAccounts(),
        ]);
    }

    // POST auth/login
    public function login(Request $request): JsonResponse
    {
        // Validasi sederhana langsung di sini (hanya 2 field, tidak perlu Validator terpisah)
        $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);
 
        $dto = $this->service->login(
            email:    $request->input('email'),
            password: $request->input('password'),
        );
 
        return response()->json([
            'success' => true,
            'message' => 'Login berhasil.',
            'data'    => $dto->toArray(),
        ]);
    }
 
    // POST /api/auth/logout
    public function logout(Request $request): JsonResponse
    {
        $this->service->logout($request->user());
 
        return response()->json([
            'success' => true,
            'message' => 'Logout berhasil.',
        ]);
    }
 
    // GET /api/auth/me
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->service->me($request->user()),
        ]);
    }
}
