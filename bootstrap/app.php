<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\RoleAccessMiddleware;
use App\Exceptions\BusinessException;
use Illuminate\Database\QueryException;
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

        // Permintaan tak terautentikasi harus dijawab 401, bukan 500.
        //
        // Bawaannya, middleware Authenticate mencoba mengalihkan pengguna ke
        // route bernama 'login'. Aplikasi ini API-only dan tidak punya route
        // itu, sehingga penyusunan respons penolakan justru melempar
        // RouteNotFoundException dan berakhir sebagai 500.
        //
        // Akibatnya interceptor 401 di frontend (src/lib/api.ts) tidak pernah
        // kena: pengguna yang tokennya sudah tidak berlaku -- misalnya setelah
        // db:seed menghapus isi personal_access_tokens -- melihat "kesalahan
        // server" dan tetap terjebak, alih-alih diarahkan untuk masuk ulang.
        // Log pun terisi 500 palsu yang menenggelamkan galat sungguhan.
        //
        // Mengembalikan null membuat Laravel memakai jalur 401 yang benar.
        $middleware->redirectGuestsTo(fn () => null);
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

        // Pelanggaran foreign key (SQLSTATE 23503) → 409, bukan 500.
        //
        // Dua kejadian yang sah secara operasional tapi selama ini bocor sebagai
        // 500 lengkap dengan stack trace dan SQL mentahnya:
        //   - menghapus baris induk yang masih dirujuk tabel lain
        //     (mis. DELETE /provinces/{id} yang masih dipakai ref_ump), dan
        //   - menyimpan baris dengan id rujukan yang tidak ada
        //     (mis. POST /stakeholder-contacts dengan alumni_id asing).
        // Keduanya konflik keadaan data, jadi 409 dengan pesan yang bisa
        // ditampilkan apa adanya di frontend.
        $exceptions->render(function (QueryException $e, $request) {
            if (($e->errorInfo[0] ?? null) !== '23503') {
                return null; // galat query lain tetap lewat penanganan bawaan
            }

            $penghapusan = str_contains(strtolower($e->getMessage()), 'update or delete on table');

            return response()->json([
                'success' => false,
                'message' => $penghapusan
                    ? 'Data ini masih dipakai oleh data lain, jadi tidak bisa dihapus. Lepaskan atau hapus dulu keterkaitannya.'
                    : 'Data rujukan yang dipilih tidak ditemukan. Muat ulang halaman lalu pilih ulang.',
            ], 409);
        });

        // BusinessException → JSON dengan HTTP code sesuai $e->getCode().
        // Dipakai di Service layer untuk error business-level (not found, forbidden, conflict).
        $exceptions->render(function (BusinessException $e, $request) {
            $code = $e->getCode() ?: 400;
            return response()->json(array_merge([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getPayload()), $code);
        });

    })->create();
