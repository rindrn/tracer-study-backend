<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Perluasan dari 2026_08_26_000001_sync_lams_id_sequence: bukan hanya `lams`
 * yang sequence-nya tertinggal. Setiap tabel yang barisnya masuk lewat
 * restore/import dump (bukan lewat Eloquent) meninggalkan `<tabel>_id_seq`
 * di belakang MAX(id), sehingga INSERT pertama lewat aplikasi gagal HTTP 500
 * dengan "duplicate key value violates unique constraint".
 *
 * Migrasi ini menyapu tabel ber-kolom `id` di skema koneksi oltp saja
 * (search_path `tracer_oltp`), lalu menyelaraskan sequence-nya. Skema OLAP
 * (`public`) dan pre-aggregation Cube.js sengaja tidak disentuh meski satu
 * basis data fisik -- keduanya diisi ulang oleh ETL, bukan oleh aplikasi ini.
 *
 * Idempoten: aman dijalankan berkali-kali dan tidak berefek pada sequence
 * yang sudah sinkron.
 */
return new class extends Migration
{
    protected $connection = 'oltp';

    public function up(): void
    {
        $db = DB::connection('oltp');

        // search_path koneksi oltp bisa berisi beberapa skema; ambil semuanya
        // supaya migrasi tetap benar kalau konfigurasinya berubah.
        $skema = array_map('trim', explode(',', (string) config('database.connections.oltp.search_path')));
        $skema = array_values(array_filter($skema));

        $tables = $db->select(<<<'SQL'
            SELECT n.nspname AS skema, c.relname AS tabel
            FROM pg_class c
            JOIN pg_namespace n ON n.oid = c.relnamespace
            JOIN pg_attribute a ON a.attrelid = c.oid
                 AND a.attname = 'id' AND a.attnum > 0 AND NOT a.attisdropped
            WHERE c.relkind = 'r'
              AND n.nspname = ANY(?)
            ORDER BY 1, 2
        SQL, ['{' . implode(',', $skema) . '}']);

        foreach ($tables as $t) {
            $nama = $t->skema . '.' . $t->tabel;

            // Tabel tanpa sequence (mis. id yang di-assign manual) dilewati.
            $seq = $db->selectOne('SELECT pg_get_serial_sequence(?, ?) AS seq', [$nama, 'id'])->seq;
            if (! $seq) {
                continue;
            }

            // is_called=false saat tabel kosong supaya nomor 1 tidak ikut terlewat.
            $db->statement(
                "SELECT setval('{$seq}', COALESCE((SELECT MAX(id) FROM {$nama}), 1), (SELECT MAX(id) FROM {$nama}) IS NOT NULL)"
            );
        }
    }

    public function down(): void
    {
        // Tidak ada rollback yang bermakna untuk penyelarasan sequence.
    }
};
