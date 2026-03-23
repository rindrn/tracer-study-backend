<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
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

    }
}
