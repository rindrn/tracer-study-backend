<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        putenv('PGPASSWORD=' . env('OLTP_DB_PASSWORD'));

        // init.sql = full pg_dump (DROP + CREATE) of both schemas -- must
        // run first. 002/003 are additive-only scripts layered on top, so
        // they must run every time init.sql runs (a fresh import wipes the
        // tables they create, since those tables aren't part of the dump).
        foreach (['dump/init.sql', 'dump/002_semantic_mapping_schema.sql', 'dump/003_semantic_mapping_seed.sql', 'dump/004_pg_trgm.sql'] as $relative) {
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