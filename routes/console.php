<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Log;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


/*
|--------------------------------------------------------------------------
| ETL Scheduling
|--------------------------------------------------------------------------
|
| MODE PRODUCTION (aktif sekarang): jalan mingguan setiap Senin jam 01:00.
| Mode testing (setiap 5 menit) ada di bagian bawah untuk dipakai lagi
| kalau perlu debug cepat (comment yang production, uncomment yang testing).
|
| onSuccess() memicu etl:create-preagg-indexes SETELAH etl:run selesai
| -- TAPI perhatikan catatan timing di bawah: index baru berguna kalau
| Cube.js SUDAH rebuild pre-aggregation dengan data terbaru. Jika
| rebuild Cube.js berjalan async/terjadwal terpisah, panggilan index
| ini bisa mendahului rebuild dan menjadi no-op (tabel prefix belum
| ada / masih versi lama). Untuk SmartTracer yang ETL-nya mingguan dan
| Cube.js refresh-nya juga mingguan, jeda singkat ini biasanya aman
| karena CreatePreAggIndexes pakai CREATE INDEX IF NOT EXISTS dan tetap
| bisa dijalankan ulang manual kapan saja tanpa efek samping.
|
*/

// --reason membedakan eksekusi terjadwal dari eksekusi manual di CLI saat
// riwayat etl_runs dibaca; tanpa itu keduanya tercatat sebagai 'cli_manual'.
Schedule::command('etl:run --reason=scheduled_weekly')
    ->weeklyOn(1, '01:00') // setiap Senin jam 01:00
    ->withoutOverlapping()
    ->onSuccess(function () {
        Log::info('ETL snapshot mingguan berhasil');
        Artisan::call('etl:create-preagg-indexes');
    })
    ->onFailure(fn () => Log::error('ETL snapshot mingguan gagal'));

Schedule::command('tracer:recalc-response-threshold')->dailyAt('01:00');

/*
|--------------------------------------------------------------------------
| MODE TESTING (uncomment untuk debug cepat, comment yang production di atas)
|--------------------------------------------------------------------------
|
| Schedule::command('etl:run')
|     ->everyFiveMinutes()
|     ->withoutOverlapping()
|     ->onSuccess(function () {
|         Log::info('ETL snapshot berhasil');
|         Artisan::call('etl:create-preagg-indexes');
|     })
|     ->onFailure(fn () => Log::error('ETL snapshot gagal'));
|
*/
