<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Dashboard Saya" di halaman Insight: pertanyaan tersimpan yang disematkan
 * seorang pengguna, lengkap dengan urutan dan ukuran kartunya.
 *
 * Yang disematkan boleh milik sendiri atau yang dibagikan orang lain. Kalau
 * pemiliknya berhenti membagikan, sematan orang lain tidak dihapus di sini
 * tetapi disaring saat dibaca (InsightBoardService) — begitu dibagikan lagi,
 * kartunya muncul kembali di tempat semula.
 */
return new class extends Migration
{
    protected $connection = 'oltp';

    public function up(): void
    {
        Schema::connection('oltp')->create('insight_dashboard_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('insight_questions')->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->string('size', 2)->default('sm');
            $table->timestamps();

            $table->unique(['user_id', 'question_id']);
        });
    }

    public function down(): void
    {
        Schema::connection('oltp')->dropIfExists('insight_dashboard_items');
    }
};
