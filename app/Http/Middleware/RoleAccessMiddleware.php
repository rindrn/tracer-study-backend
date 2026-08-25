<?php
namespace App\Http\Middleware;

use App\Models\Transactional\Permission;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleAccessMiddleware
{
    /**
     * Nilai role lama pra-migrasi, dipetakan ke nilai baru yang sekarang
     * dipakai di seluruh sistem. Harus sinkron dengan `mapBackendRole()` di
     * `fe-tracer-study/src/lib/rbac.ts` — sebelum perbaikan ini, pemetaan
     * itu HANYA ada di frontend, sehingga user dengan `role` lama di DB
     * melihat UI penuh (frontend memetakannya) tapi setiap aksi nyata
     * ditolak 403 di sini (backend mencocokkan string literal, tanpa
     * pemetaan). Lihat HASIL_TESTING_2026-08-23.md bug #14.
     */
    private const LEGACY_ROLE_MAP = [
        'admin' => 'head_tracer',
        'p2mpp' => 'wadir',
        'prodi' => 'kaprodi',
    ];

    private function currentRole(string $role): string
    {
        return self::LEGACY_ROLE_MAP[$role] ?? $role;
    }

    /**
     * Usage:
     *   middleware('role:head_tracer,tracer_team')       → check role
     *   middleware('role:permission:questionnaire.edit') → check permission
     */
    public function handle(Request $request, Closure $next, string ...$params): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $effectiveRole = $this->currentRole($user->role);

        // Permission-based check: role:permission:permission_name
        if (count($params) === 1 && str_starts_with($params[0], 'permission:')) {
            $permName = substr($params[0], 11);
            $hasPermission = Permission::forRole($effectiveRole)->contains($permName);

            if (!$hasPermission) {
                return response()->json([
                    'success'    => false,
                    'message'    => 'Akses ditolak. Anda tidak memiliki permission ini.',
                    'your_role'  => $user->role,
                    'required'   => $permName,
                ], 403);
            }

            return $next($request);
        }

        // Role-based check (original behavior)
        if (!in_array($effectiveRole, $params, true)) {
            return response()->json([
                'success'       => false,
                'message'       => 'Akses ditolak. Role Anda tidak memiliki izin.',
                'your_role'     => $user->role,
                'allowed_roles' => $params,
            ], 403);
        }

        return $next($request);
    }
}
