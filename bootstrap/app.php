<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\RoleAccessMiddleware;
use App\Exceptions\BusinessException;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias(['role' => RoleAccessMiddleware::class]);

        // CORS — izinkan frontend Vite dev server (lihat config/cors.php)
        $middleware->append(\Illuminate\Http\Middleware\HandleCors::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {

        // ValidationException → selalu return JSON, bukan redirect
        $exceptions->render(function (ValidationException $e, $request) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors'  => $e->errors(),
            ], 422);
        });

        // BusinessException → JSON dengan HTTP code sesuai $e->getCode().
        // Dipakai di Service layer untuk error business-level (not found, forbidden, conflict).
        $exceptions->render(function (BusinessException $e, $request) {
            $code = $e->getCode() ?: 400;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $code);
        });

    })->create();
