<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Melepas kunci jenjang pada `programs.degree` dan `education_records.degree`.
 *
 * `$table->enum()` di Postgres bukan native ENUM type melainkan VARCHAR plus
 * CHECK constraint, jadi daftar nilainya cukup diganti tanpa membongkar tabel
 * dan tanpa menyentuh data yang sudah ada.
 *
 * Sebelumnya `programs.degree` hanya menerima empat jenjang milik satu kampus,
 * sehingga SmartTracer tidak bisa dipasang di kampus yang punya D1, D2, S3,
 * program profesi, atau spesialis. Daftar barunya diambil dari
 * `config/academic.php` supaya migrasi, validasi, dan frontend tidak pernah
 * berbeda isi.
 */
return new class extends Migration
{
    protected $connection = 'oltp';

    /**
     * Riwayat pendidikan alumni menerima jenjang mana pun yang boleh dimiliki
     * prodi, ditambah 'Other' sebagai penampung jenjang dari luar negeri yang
     * tidak punya padanan di PDDIKTI.
     */
    private function educationDegrees(): array
    {
        return [...config('academic.degrees'), 'Other'];
    }

    private function setCheck(string $table, string $column, array $values): void
    {
        $constraint = "{$table}_{$column}_check";
        $list       = implode(',', array_map(
            fn ($v) => "'" . str_replace("'", "''", $v) . "'",
            $values
        ));

        DB::connection('oltp')->statement(
            "ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$constraint}"
        );
        DB::connection('oltp')->statement(
            "ALTER TABLE {$table} ADD CONSTRAINT {$constraint}
             CHECK ({$column}::text = ANY (ARRAY[{$list}]::text[]))"
        );
    }

    public function up(): void
    {
        $this->setCheck('programs', 'degree', config('academic.degrees'));
        $this->setCheck('education_records', 'degree', $this->educationDegrees());
    }

    /**
     * Balik ke daftar lama. Baris yang sempat memakai jenjang baru akan
     * menahan penambahan constraint, dan itu memang disengaja: lebih baik
     * rollback-nya gagal terang-terangan daripada data prodi ikut terhapus.
     */
    public function down(): void
    {
        $this->setCheck('programs', 'degree', ['S1', 'D3', 'D4', 'S2']);
        $this->setCheck('education_records', 'degree', [
            'D3', 'D4', 'S1', 'S2', 'S3', 'Profesi', 'Other',
        ]);
    }
};
