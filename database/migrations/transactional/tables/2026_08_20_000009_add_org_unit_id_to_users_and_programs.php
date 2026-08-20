<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 2 RBAC dinamis (DFR-12/13/14) — tautan generik user/program ke
 * `org_units`, dipakai HANYA oleh EnforcesProdiScope::scopedParamsGeneric()
 * ketika `config('institution.structure_template')` bukan "politeknik".
 *
 * Nullable & tidak diisi backfill apa pun di sini -- strategi expand-contract
 * yang sama dengan migration create_org_units_table (Fase 1): kolom teks lama
 * (`users.jurusan`, `programs.jurusan`, `programs.degree`) tetap satu-satunya
 * sumber kebenaran untuk mode "politeknik" (default, POLBAN produksi).
 * Kolom ini murni aditif -- tidak ada baris existing yang berubah nilai,
 * tidak ada query lama yang membaca kolom ini, jadi nol risiko regresi.
 */
return new class extends Migration {
    protected $connection = 'oltp';

    public function up(): void
    {
        Schema::connection('oltp')->table('users', function (Blueprint $table) {
            $table->foreignId('org_unit_id')
                  ->nullable()
                  ->after('jurusan')
                  ->constrained('org_units')
                  ->nullOnDelete();
        });

        Schema::connection('oltp')->table('programs', function (Blueprint $table) {
            $table->foreignId('org_unit_id')
                  ->nullable()
                  ->after('jurusan')
                  ->constrained('org_units')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::connection('oltp')->table('programs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('org_unit_id');
        });

        Schema::connection('oltp')->table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('org_unit_id');
        });
    }
};
