<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Backfill `users.jurusan_id` (FK) dari `users.jurusan` (teks lama) untuk
 * akun kajur di dump `oltp_master_data.sql`.
 *
 * Dump itu dibuat sebelum kolom `jurusan_id` ada -- hanya mengisi kolom
 * teks lama. Tanpa backfill ini, `User::scopedProgramIds()` untuk role
 * kajur selalu kosong (jurusanEntity() null) walau `jurusan_program_scopes`
 * sudah benar, sehingga tetap 403 di endpoint dashboard. Idempotent:
 * hanya menyentuh baris yang jurusan_id-nya masih NULL.
 */
class UserJurusanLinkSeeder extends Seeder
{
    public function run(): void
    {
        DB::connection('oltp')->statement(<<<'SQL'
            UPDATE users u
            SET jurusan_id = j.id
            FROM jurusans j
            WHERE j.name = u.jurusan
              AND u.jurusan_id IS NULL
        SQL);
    }
}
