<?php

namespace Database\Seeders\Concerns;

use RuntimeException;

/**
 * Menjalankan berkas .sql lewat psql, dipakai bersama oleh jalur pemasangan
 * baku (DatabaseSeeder) dan jalur pemasangan demo (DemoSeeder).
 *
 * Lewat psql, bukan lewat PDO: berkas dumpnya memakai perintah khas psql
 * (COPY ... FROM stdin, \.) yang tidak dipahami driver PDO.
 */
trait ImportsSqlDumps
{
    /**
     * Rakit argumen koneksi psql dari env.
     *
     * @param string $conn 'OLTP' atau 'OLAP' — menentukan prefix env mana yang dipakai.
     */
    private function psqlDsn(string $conn): string
    {
        putenv('PGPASSWORD=' . env("{$conn}_DB_PASSWORD"));

        return sprintf(
            '-h %s -p %s -U %s -d %s',
            escapeshellarg(env("{$conn}_DB_HOST", '127.0.0.1')),
            escapeshellarg(env("{$conn}_DB_PORT", '5432')),
            escapeshellarg(env("{$conn}_DB_USERNAME", 'postgres')),
            escapeshellarg(env("{$conn}_DB_DATABASE", 'tracer_study')),
        );
    }

    /**
     * Jalankan satu berkas .sql pada koneksi yang diminta.
     *
     * @param string $relative Path relatif terhadap database_path().
     * @param string $label    Nama manusiawi untuk pesan konsol.
     * @param string $conn     'OLTP' atau 'OLAP'.
     * @param bool   $optional true = berkas yang tidak ada dilewati dengan
     *                         peringatan, bukan menggagalkan seeding.
     */
    private function importSqlFile(string $relative, string $label, string $conn, bool $optional = false): void
    {
        $path = database_path($relative);

        if (!is_file($path)) {
            if ($optional) {
                $this->command?->warn("Lewati {$relative} ({$label}) -- berkasnya tidak ada.");

                return;
            }

            throw new RuntimeException("File dump tidak ditemukan: {$path}");
        }

        $this->command?->info("Mengimpor {$relative} ({$label})...");
        passthru('psql ' . $this->psqlDsn($conn) . ' -v ON_ERROR_STOP=1 -q -f ' . escapeshellarg($path), $status);

        if ($status !== 0) {
            throw new RuntimeException("Import {$relative} gagal.");
        }
    }

    /**
     * Siapkan schema OLAP (public) beserta ekstensi yang dipakainya.
     *
     * olap_schema.sql adalah dump POLOS -- tidak mengandung DROP apa pun,
     * jadi CREATE-nya hanya aman di schema kosong. `migrate:fresh` tidak
     * membantu di sini: dia bekerja di koneksi default (search_path
     * tracer_oltp) dan tidak menyentuh public sama sekali. Karena itu schema
     * public dijatuhkan eksplisit dulu supaya reseed berulang tidak gagal
     * "already exists" / "duplicate key".
     *
     * Drop ini aman terhadap tracking migration: tabel `migrations` milik
     * Laravel ada di tracer_oltp, bukan public.
     */
    private function prepareOlapSchema(): void
    {
        $this->command?->info('Menjatuhkan dan membuat ulang schema public (OLAP)...');
        passthru(
            'psql ' . $this->psqlDsn('OLAP') . ' -v ON_ERROR_STOP=1 -c '
            . escapeshellarg('DROP SCHEMA IF EXISTS public CASCADE; CREATE SCHEMA public;'),
            $status
        );

        if ($status !== 0) {
            throw new RuntimeException('Gagal reset schema public sebelum import OLAP.');
        }

        $this->importSqlFile('dump/olap_schema.sql', 'rangka star schema OLAP', 'OLAP');

        // Sesudah drop schema, bukan sebelum: DROP SCHEMA public CASCADE ikut
        // menjatuhkan ekstensi yang terpasang di dalamnya.
        $this->importSqlFile(
            'dump/004_pg_trgm.sql',
            'ekstensi pg_trgm (deteksi pertanyaan bermakna sama)',
            'OLAP',
        );

        $this->command?->info('Schema OLAP siap.');
    }
}
