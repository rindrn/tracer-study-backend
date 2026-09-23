<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pertanyaan tersimpan halaman Insight (OLAP Explorer).
 *
 * Yang disimpan hanya SUSUNAN pertanyaannya (`query`: cube, measure, dimensi
 * baris/kolom, saringan) — bukan hasilnya. Membuka pertanyaan selalu
 * menjalankan ulang query dengan scope role si PEMBUKA, jadi pertanyaan yang
 * dibagikan admin tetap hanya menampilkan prodi sendiri bagi kaprodi, dan
 * angkanya selalu mengikuti snapshot terbaru.
 */
return new class extends Migration
{
    protected $connection = 'oltp';

    public function up(): void
    {
        Schema::connection('oltp')->create('insight_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->jsonb('query');
            $table->string('chart_measure', 100)->nullable();
            $table->boolean('is_shared')->default(false);
            $table->timestamps();

            $table->index('user_id');
            $table->index('is_shared');
        });
    }

    public function down(): void
    {
        Schema::connection('oltp')->dropIfExists('insight_questions');
    }
};
