<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * org_units — pohon unit organisasi sungguhan (Fase 1, DFR-24/DFR-25).
 *
 * Self-referencing adjacency list (parent_id -> org_units.id), bukan
 * closure table maupun JSONB path -- lihat catatan audit di
 * cetak-biru-struktur-dinamis.md: skala nyata proyek ini (36 prodi, ~11
 * jurusan) tidak butuh struktur seberat itu.
 *
 * TIDAK ADA constraint anti-siklus di level database: Postgres tidak
 * native mencegah siklus multi-level lewat CHECK/trigger sederhana, dan
 * menambah trigger rekursif untuk kasus ini lebih rumit daripada
 * manfaatnya pada skala puluhan baris. Validasinya di OrgUnitService
 * (level aplikasi) -- lihat assertNoCycle().
 *
 * `programs.jurusan` (kolom teks) SENGAJA TIDAK disentuh migration ini.
 * Strategi expand-contract: org_units hidup berdampingan dengan kolom lama
 * sampai Fase 2+ menyiapkan jalur baca ganda (DFR-25).
 */
return new class extends Migration {
    protected $connection = 'oltp';

    public function up(): void
    {
        Schema::connection('oltp')->create('org_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('org_unit_type_id')
                  ->constrained('org_unit_types')
                  ->restrictOnDelete();
            $table->foreignId('parent_id')
                  ->nullable()
                  ->constrained('org_units')
                  ->nullOnDelete();
            $table->string('name', 150);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['org_unit_type_id']);
            $table->index(['parent_id']);
            // Nama unik di antara saudara kandung (parent + level yang sama)
            // -- bukan unik global, karena "Departemen Teknik" di bawah dua
            // fakultas berbeda adalah dua unit yang sah berbeda.
            $table->unique(['org_unit_type_id', 'parent_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::connection('oltp')->dropIfExists('org_units');
    }
};
