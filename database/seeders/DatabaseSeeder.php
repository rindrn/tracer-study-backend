<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        putenv('PGPASSWORD=' . env('OLTP_DB_PASSWORD'));

        // init.sql sebenarnya pg_dump POLOS (tanpa --clean/--if-exists) -- tidak
        // ada satupun DROP SCHEMA/DROP TABLE di dalamnya, jadi CREATE-nya cuma
        // aman dijalankan sekali di database yang benar-benar kosong.
        // migrate:fresh (dijalankan sebelum seeder ini) TIDAK cukup untuk itu:
        // Schema::dropAllTables() Laravel cuma membersihkan schema 'public',
        // sedangkan separuh dump ini (tracer_oltp.*) ada di schema terpisah yang
        // tidak disentuh sama sekali. Akibatnya redeploy kedua dst di server yang
        // sudah pernah di-seed selalu gagal "already exists"/"duplicate key".
        // Drop eksplisit kedua schema di sini membuat seeder ini idempoten,
        // tidak bergantung pada perilaku drop Laravel terhadap schema non-default.
        $dropSchemas = sprintf(
            'psql -h %s -p %s -U %s -d %s -v ON_ERROR_STOP=1 -c "DROP SCHEMA IF EXISTS public CASCADE; DROP SCHEMA IF EXISTS tracer_oltp CASCADE; CREATE SCHEMA public;"',
            env('OLTP_DB_HOST'),
            env('OLTP_DB_PORT'),
            env('OLTP_DB_USERNAME'),
            env('OLTP_DB_DATABASE'),
        );
        passthru($dropSchemas, $dropStatus);
        if ($dropStatus !== 0) {
            throw new Exception('Drop schema public/tracer_oltp sebelum reseed gagal.');
        }

        // init.sql = full pg_dump (CREATE) of both schemas -- must run first.
        // 002/003 are additive-only scripts layered on top, so they must run
        // every time init.sql runs (a fresh import wipes the tables they
        // create, since those tables aren't part of the dump).
        foreach (['dump/init.sql'] as $relative) {
            $path = database_path($relative);

            $command = sprintf(
                'psql -h %s -p %s -U %s -d %s -f "%s"',
                env('OLTP_DB_HOST'),
                env('OLTP_DB_PORT'),
                env('OLTP_DB_USERNAME'),
                env('OLTP_DB_DATABASE'),
                $path
            );

            passthru($command, $status);

            if ($status !== 0) {
                throw new Exception("Import {$relative} gagal.");
            }
        }
    }
}