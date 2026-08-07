<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
use App\Services\CubeJsClient;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(CubeJsClient::class, fn() => new CubeJsClient());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom([
            database_path('migrations/transactional/tables'),
            database_path('migrations/transactional/views'),
            database_path('migrations/analytical/tables'),
            database_path('migrations/analytical/views'),
        ]);
        // Override Sanctum agar token disimpan di koneksi oltp
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        $this->configureRateLimiting();
    }

    /**
     * Pembatasan laju rute masuk (NFR-06).
     *
     * Sebelumnya tidak ada pembatasan sama sekali di seluruh aplikasi, dan itu
     * paling berbahaya di sisi alumni: NIM berpola TAHUN + nomor urut sehingga
     * mudah dienumerasi, sementara NIM itu SEKALIGUS kata sandinya (lihat
     * AlumniAuthService::isValidPassword). Menebak tidak diperlukan — seluruh
     * akun bisa disapu skrip sederhana dalam hitungan menit.
     *
     * Dua lapis, karena keduanya menahan serangan yang berbeda:
     *
     *   - Per akun (5/menit): menahan penebakan kata sandi pada SATU akun,
     *     termasuk dari banyak alamat sekaligus.
     *   - Per alamat (30/menit): menahan penyapuan LINTAS akun dari satu
     *     sumber — ini yang relevan untuk enumerasi NIM, karena di sana tiap
     *     akun hanya perlu satu percobaan.
     *
     * Angka 30 sengaja longgar supaya jaringan bersama (kampus, warnet, NAT
     * kantor) tidak ikut terhalang; penyapuan 505 akun tetap memakan belasan
     * menit alih-alih beberapa detik, cukup untuk terlihat di log.
     *
     * Pengenal dinormalkan ke huruf kecil supaya "NIM123" dan "nim123" tidak
     * dihitung sebagai dua ember terpisah — isValidPassword() sendiri menerima
     * NIM huruf kecil, jadi ember yang peka huruf besar-kecil akan
     * melipatgandakan jatah percobaan tanpa disadari.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $identifier = Str::lower(trim(
                (string) $request->input('email', $request->input('nim_or_email', ''))
            ));

            return [
                Limit::perMinute(5)->by($identifier . '|' . $request->ip()),
                Limit::perMinute(30)->by($request->ip()),
            ];
        });
    }
}
