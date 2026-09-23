<?php

namespace Tests\Support;

use App\Models\Transactional\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel-tabel Insight di PostgreSQL `oltp` sungguhan, tapi seluruhnya di
 * dalam satu transaksi: dibuat di schema sementara yang ikut hilang saat
 * rollback — tidak ada yang tertinggal di database.
 *
 * (Tidak memakai SQLite karena ekstensi pdo_sqlite tidak terpasang di
 * lingkungan ini.) Tes dilewati kalau PostgreSQL tidak bisa dihubungi.
 *
 * Panggil setUpInsightSchema() di setUp() dan tearDownInsightSchema() di
 * tearDown() sebelum parent::tearDown().
 */
trait UsesInsightTestSchema
{
    protected function setUpInsightSchema(): void
    {
        try {
            DB::connection('oltp')->getPdo();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Koneksi oltp (PostgreSQL) tidak tersedia: ' . $e->getMessage());
        }

        DB::connection('oltp')->beginTransaction();

        $schema = 'insight_test_' . bin2hex(random_bytes(4));
        DB::connection('oltp')->statement("CREATE SCHEMA {$schema}");
        DB::connection('oltp')->statement("SET LOCAL search_path TO {$schema}");

        Schema::connection('oltp')->create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('email');
            $t->string('password');
            $t->string('role');
            $t->timestamps();
        });

        Schema::connection('oltp')->create('insight_questions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->string('title', 150);
            $t->text('description')->nullable();
            $t->jsonb('query');
            $t->string('chart_measure', 100)->nullable();
            $t->boolean('is_shared')->default(false);
            $t->timestamps();
        });

        Schema::connection('oltp')->create('insight_dashboard_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('question_id')->constrained('insight_questions')->cascadeOnDelete();
            $t->unsignedInteger('position')->default(0);
            $t->string('size', 2)->default('sm');
            $t->timestamps();
            $t->unique(['user_id', 'question_id']);
        });
    }

    protected function tearDownInsightSchema(): void
    {
        if (DB::connection('oltp')->transactionLevel() > 0) {
            DB::connection('oltp')->rollBack();
        }
    }

    protected function makeUser(string $name): User
    {
        $u = new User();
        $u->forceFill([
            'name' => $name, 'email' => strtolower($name) . '@test', 'password' => 'x', 'role' => 'head_tracer',
        ])->save();

        return $u;
    }
}
