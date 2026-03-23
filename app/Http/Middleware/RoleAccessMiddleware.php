<?php
// app/Http/Middleware/RoleAccessMiddleware.php
namespace App\Http\Middleware;
 
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
 
class RoleAccessMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
 
        if (! $user) {
            return response()->json(['success'=>false,'message'=>'Unauthenticated.'], 401);
        }
 
        if (! in_array($user->role, $roles, strict: true)) {
            return response()->json([
                'success'       => false,
                'message'       => 'Akses ditolak. Role Anda tidak memiliki izin.',
                'your_role'     => $user->role,
                'allowed_roles' => $roles,
            ], 403);
        }
 
        return $next($request);
    }
}
