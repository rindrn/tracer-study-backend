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
     * Pengenal dinormalkan ke huruf kecil supaya "NIM123" dan "nim123" tidak
     * dihitung sebagai dua ember terpisah — isValidPassword() sendiri menerima
     * NIM huruf kecil, jadi ember yang peka huruf besar-kecil akan
     * melipatgandakan jatah percobaan tanpa disadari.
     *
     * EMBER DIPISAH PER RUTE, dan itu bukan sekadar kerapian. Halaman masuk
     * di frontend mencoba rute staf lebih dulu, lalu jatuh ke rute alumni
     * kalau gagal (Login.tsx handleSubmit) — jadi SATU penekanan tombol oleh
     * alumni menghasilkan DUA permintaan. Tanpa nama rute di dalam kunci,
     * keduanya jatuh ke ember yang sama karena pengenalnya identik, sehingga
     * jatah 5 percobaan habis hanya dalam 2 kali percobaan sungguhan.
     *
     * Batas per alamat dinaikkan ke 60 dengan alasan yang sama: pola dua
     * permintaan itu membuat kuota alamat terpakai dua kali lipat. 60 berarti
     * 30 percobaan sungguhan per menit dari satu alamat — cukup longgar untuk
     * jaringan bersama (kampus, NAT kantor), sementara penyapuan 505 akun
     * tetap memakan sekitar sembilan menit alih-alih beberapa detik. Penyapu
     * tidak memakai pola dua permintaan itu, jadi kuota penuhnya berlaku
     * untuk dia.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $identifier = Str::lower(trim(
                (string) $request->input('email', $request->input('nim_or_email', ''))
            ));

            // Balasan bawaan Laravel untuk 429 adalah {"message":"Too Many
            // Attempts."} — berbahasa Inggris dan bentuknya berbeda dari
            // seluruh balasan lain di API ini. Diseragamkan ke {success,
            // message} berbahasa Indonesia, dengan sisa waktu ikut di BADAN
            // balasan.
            //
            // Sisa waktu WAJIB ada di badan, tidak cukup di header
            // Retry-After: header itu tidak termasuk daftar aman CORS, dan
            // config/cors.php tidak mengekspos apa pun, sehingga peramban
            // TIDAK BISA membacanya. Lewat curl kelihatan, lewat browser
            // tidak — persis jenis perbedaan yang menyesatkan saat diuji.
            $tooMany = function (Request $request, array $headers) {
                $seconds = (int) ($headers['Retry-After'] ?? 60);

                return response()->json([
                    'success'     => false,
                    'message'     => "Terlalu banyak percobaan masuk. Silakan coba lagi dalam {$seconds} detik.",
                    'retry_after' => $seconds,
                ], 429, $headers);
            };

            return [
                Limit::perMinute(5)
                    ->by($request->path() . '|' . $identifier . '|' . $request->ip())
                    ->response($tooMany),
                Limit::perMinute(60)
                    ->by($request->ip())
                    ->response($tooMany),
            ];
        });
    }
}
