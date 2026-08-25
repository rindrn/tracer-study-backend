<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Jalur seeding baku: data karangan (Faker).
 *
 *     php artisan migrate:fresh --seed
 *     php artisan db:seed
 *
 * Sebelumnya kelas ini memuat data alumni SUNGGUHAN dari
 * database/dump/oltp_real_data.sql. Itu berbahaya sebagai perilaku bawaan:
 * `migrate:fresh --seed` adalah perintah refleks saat mengembangkan, dan
 * tanpa diminta ia menyalin data pribadi nyata ke basis data mana pun yang
 * kebetulan sedang ditunjuk `.env` -- termasuk basis data percobaan milik
 * orang lain. Yang bawaan sekarang adalah data karangan; data asli harus
 * diminta eksplisit:
 *
 *     php artisan db:seed --class=RealDataSeeder
 *
 * Seluruh isinya ada di DemoSeeder, tidak disalin ke sini, supaya kedua cara
 * memanggilnya (lewat --seed dan lewat --class=DemoSeeder) tidak pernah bisa
 * menyimpang satu sama lain. DemoSeeder sudah berdiri sendiri: ia menyiapkan
 * rangka OLAP lebih dulu, jadi tidak ada langkah psql manual di sini.
 *
 * Sesudah seeding, jalankan `php artisan etl:run` supaya dim_* dan fact_*
 * terisi dan dashboard tidak kosong.
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call(DemoSeeder::class);
    }
}
