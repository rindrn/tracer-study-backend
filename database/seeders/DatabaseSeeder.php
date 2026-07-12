<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $sql = database_path('dump/init.sql');

        putenv('PGPASSWORD=' . env('OLTP_DB_PASSWORD'));

        $command = sprintf(
            'psql -h %s -p %s -U %s -d %s -f "%s"',
            env('OLTP_DB_HOST'),
            env('OLTP_DB_PORT'),
            env('OLTP_DB_USERNAME'),
            env('OLTP_DB_DATABASE'),
            $sql
        );

        passthru($command, $status);

        if ($status !== 0) {
            throw new Exception('Import init.sql gagal.');
        }
    }
}