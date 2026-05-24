<?php

/**
 * config/cubejs.php
 *
 * Konfigurasi koneksi Laravel → Cube.js.
 * Taruh file ini di: config/cubejs.php
 *
 * Cube.js menggunakan JWT yang di-sign dengan CUBEJS_API_SECRET.
 * Laravel perlu tahu base URL Cube.js dan secret-nya untuk generate token.
 *
 * Di .env Laravel tambahkan:
 *   CUBEJS_BASE_URL=http://localhost:4000
 *   CUBEJS_API_SECRET=<salin nilai CUBEJS_API_SECRET dari repo Cube.js>
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Cube.js Base URL
    |--------------------------------------------------------------------------
    | URL server Cube.js yang sedang running.
    | Development  : http://localhost:4000
    | Docker/infra : http://cubejs:4000  (sesuaikan service name docker-compose)
    */
    'base_url' => env('CUBEJS_BASE_URL', 'http://localhost:4000'),

    /*
    |--------------------------------------------------------------------------
    | Cube.js API Secret
    |--------------------------------------------------------------------------
    | Salin dari CUBEJS_API_SECRET di repo Cube.js.
    | Laravel menggunakan ini untuk generate JWT Bearer token
    | yang dikirim ke setiap request Cube.js.
    |
    | JANGAN commit secret ini — selalu lewat .env
    */
    'api_secret' => env('CUBEJS_API_SECRET', ''),

    /*
    |--------------------------------------------------------------------------
    | Token TTL (detik)
    |--------------------------------------------------------------------------
    | Berapa lama JWT token valid. Default 1 jam.
    | Untuk server-to-server internal, boleh di-set lebih lama.
    */
    'token_ttl' => env('CUBEJS_TOKEN_TTL', 3600),

    /*
    |--------------------------------------------------------------------------
    | HTTP Timeout (detik)
    |--------------------------------------------------------------------------
    */
    'timeout' => env('CUBEJS_TIMEOUT', 30),
];