<?php
// app/Traits/WithCache.php
namespace App\Traits;

use Illuminate\Support\Facades\Cache;

trait WithCache
{
    /**
     * Ambil dari cache; kalau miss, jalankan $callback lalu simpan.
     * $tags harus diisi kalau key ini juga mau bisa dihapus lewat forgetTag() —
     * tanpa tag di sini, forgetTag() tidak akan pernah menyentuh key ini.
     */
    protected function remember(string $key, \Closure $callback, int $ttlSeconds = 300, array $tags = []): mixed
    {
        $store = Cache::store('redis');

        return $tags
            ? $store->tags($tags)->remember($key, $ttlSeconds, $callback)
            : $store->remember($key, $ttlSeconds, $callback);
    }

    /**
     * Hapus satu atau lebih cache key.
     */
    protected function forget(string ...$keys): void
    {
        foreach ($keys as $key) {
            Cache::store('redis')->forget($key);
        }
    }

    /**
     * Hapus semua cache yang bertag — hanya Redis yang support tags.
     */
    protected function forgetTag(string ...$tags): void
    {
        Cache::store('redis')->tags($tags)->flush();
    }
}