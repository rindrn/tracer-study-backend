<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Backfill `jurusan_program_scopes` dari kondisi `programs.jurusan` (teks)
 * saat ini -- untuk tiap jurusan, masukkan semua program yang
 * `programs.jurusan`-nya cocok persis dengan `jurusans.name`.
 *
 * Dipindah ke sini dari migration `2026_08_22_000001_create_jurusan_program_scopes_table`
 * karena migration jalan sebelum `jurusans`/`programs` terisi data (lewat
 * import dump di DatabaseSeeder), jadi backfill di migration selalu
 * menyisipkan 0 baris pada instalasi fresh. Idempotent lewat
 * `insertOrIgnore` -- aman dijalankan ulang di database yang sudah
 * disesuaikan manual lewat Master Data.
 */
class JurusanProgramScopeSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $rows = DB::connection('oltp')->table('jurusans as j')
            ->join('programs as p', 'p.jurusan', '=', 'j.name')
            ->select('j.id as jurusan_id', 'p.id as program_id')
            ->get();

        foreach ($rows->chunk(500) as $chunk) {
            DB::connection('oltp')->table('jurusan_program_scopes')->insertOrIgnore(
                $chunk->map(fn ($r) => [
                    'jurusan_id' => $r->jurusan_id,
                    'program_id' => $r->program_id,
                    'created_at' => $now,
                ])->all()
            );
        }
    }
}
