<?php
namespace App\Http\Middleware;

use App\Models\Transactional\Permission;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleAccessMiddleware
{
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

        // Permission-based check: role:permission:permission_name
        if (count($params) === 1 && str_starts_with($params[0], 'permission:')) {
            $permName = substr($params[0], 11);
            $hasPermission = Permission::forRole($user->role)->contains($permName);

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
        if (!in_array($user->role, $params, true)) {
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
