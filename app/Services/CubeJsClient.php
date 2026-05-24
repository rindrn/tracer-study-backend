<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * CubeJsClient
 *
 * Singleton service untuk semua HTTP request Laravel → Cube.js.
 * Generate JWT dari CUBEJS_API_SECRET (HS256), di-cache agar
 * tidak di-generate ulang tiap request.
 *
 * Taruh di: app/Services/CubeJsClient.php
 * Daftarkan sebagai singleton di AppServiceProvider::register()
 */
class CubeJsClient
{
    private string $baseUrl;
    private string $secret;
    private int    $ttl;
    private int    $timeout;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('cubejs.base_url', 'http://localhost:4000'), '/');
        $this->secret  = config('cubejs.api_secret', '');
        $this->ttl     = (int) config('cubejs.token_ttl', 3600);
        $this->timeout = (int) config('cubejs.timeout', 30);
    }

    /**
     * Kirim query ke Cube.js POST /cubejs-api/v1/load
     *
     * @param  array $cubeQuery  Cube.js query object (measures, dimensions, filters, order)
     * @return \Illuminate\Support\Collection  Collection of row arrays
     * @throws \RuntimeException
     */
    public function load(array $cubeQuery): \Illuminate\Support\Collection
    {
        $response = Http::withToken($this->getToken())
            ->timeout($this->timeout)
            ->post("{$this->baseUrl}/cubejs-api/v1/load", ['query' => $cubeQuery]);

        if ($response->failed()) {
            Log::error('CubeJsClient: request failed', [
                'status' => $response->status(),
                'body'   => $response->body(),
                'query'  => $cubeQuery,
            ]);
            throw new \RuntimeException(
                "Cube.js request failed [{$response->status()}]: " . $response->body()
            );
        }

        $body = $response->json();

        // Pre-aggregation masih building
        if (($body['error'] ?? null) === 'Continue wait') {
            throw new \RuntimeException(
                'Cube.js sedang membangun pre-aggregations. Silakan coba beberapa saat lagi.'
            );
        }

        if (isset($body['error'])) {
            Log::error('CubeJsClient: Cube.js error', ['error' => $body['error'], 'query' => $cubeQuery]);
            throw new \RuntimeException('Cube.js error: ' . $body['error']);
        }

        return collect($body['data'] ?? []);
    }

    // ──────────────────────────────────────────────────────────────
    //  JWT generation — HS256, tanpa library eksternal
    // ──────────────────────────────────────────────────────────────

    private function getToken(): string
    {
        // Cache key unik per secret (aman multi-env)
        $cacheKey = 'cubejs_jwt_' . substr(md5($this->secret), 0, 12);

        // Cache sampai 60 detik sebelum expired agar tidak ada race condition
        return Cache::remember($cacheKey, $this->ttl - 60, fn() => $this->generateJwt());
    }

    private function generateJwt(): string
    {
        $now     = time();
        $header  = $this->b64u(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = $this->b64u(json_encode(['iat' => $now, 'exp' => $now + $this->ttl]));
        $sig     = $this->b64u(hash_hmac('sha256', "{$header}.{$payload}", $this->secret, true));

        return "{$header}.{$payload}.{$sig}";
    }

    private function b64u(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}