<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Konfigurasi CORS agar frontend (Vite dev server) bisa berkomunikasi
    | dengan backend Laravel API.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'http://localhost:8080',    // Vite dev server (fe-tracer-study)
        'http://127.0.0.1:8080',
        'http://localhost:5173',    // Vite default alt port
        'http://localhost:3000',    // Alternative
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    // Header yang boleh DIBACA peramban. Daftar aman CORS hanya memuat
    // segelintir header baku; apa pun di luar itu tidak terlihat oleh kode
    // frontend meski jelas terkirim — lewat curl tampak, lewat aplikasi tidak.
    //
    // Content-Disposition: dibutuhkan agar unduhan bisa memakai nama berkas
    // yang ditetapkan server, bukan nama tebakan peramban.
    'exposed_headers' => [
        'Content-Disposition',
    ],

    'max_age' => 0,

    'supports_credentials' => true,

];
