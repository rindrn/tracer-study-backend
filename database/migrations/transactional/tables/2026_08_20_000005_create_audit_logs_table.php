<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Jejak audit — siapa menyentuh data pribadi siapa, kapan, dari mana.
 *
 * Tabel dengan nama sama pernah ada lalu dibuang di migration
 * 2026_05_25_000001 karena "nol referensi di kode", dengan rencana memakai
 * package di kemudian hari. Package itu tidak pernah datang, dan sementara
 * itu proposal BAB 2.3 menjanjikan audit trail sebagai salah satu dari empat
 * mekanisme PDP. Versi ini ditulis sendiri, tanpa dependensi baru, dan
 * langsung dipakai AuditLogService.
 *
 * TIGA PERBEDAAN DARI TABEL LAMA
 * ------------------------------
 * 1. Pelakunya bukan hanya staf. Alumni menyetujui, menarik persetujuan, dan
 *    mengajukan koreksi atas datanya sendiri — seluruhnya peristiwa yang
 *    wajib tercatat. Karena itu `actor_user_id` berkunci asing ke `users`
 *    diganti pasangan `actor_type` + `actor_id` tanpa kunci asing.
 *
 * 2. `actor_label` menyimpan cuplikan nama dan surel pelaku pada saat
 *    kejadian. Inilah yang menutup DATA-08 ("riwayat aktivitas staf bertahan
 *    meski akun dihapus"): kunci asing ber-nullOnDelete pada tabel lama
 *    membuat log berubah jadi "seseorang" begitu akunnya dihapus, padahal
 *    justru penghapusan akun adalah saat log paling dibutuhkan.
 *
 * 3. `subject_alumni_id` berdiri terpisah dari `entity_type`/`entity_id`.
 *    Pertanyaan yang paling sering diajukan ke jejak audit PDP bukan "apa
 *    yang terjadi pada baris X" melainkan "siapa saja yang pernah menyentuh
 *    data SAYA" — dan alumni berhak menanyakan itu. Kolom berindeks sendiri
 *    membuat pertanyaan itu terjawab satu query, tanpa memindai JSON.
 *
 * TIDAK ADA KUNCI ASING SAMA SEKALI, disengaja. Baris log harus lebih awet
 * daripada baris yang dicatatnya; kunci asing apa pun — dengan cascade
 * maupun dengan restrict — menjadikan log ikut terhapus atau justru
 * menghalangi penghapusan yang sah.
 */
return new class extends Migration
{
    protected $connection = 'oltp';

    public function up(): void
    {
        // Penjaga untuk keadaan langka tapi merusak: kalau migration
        // 2026_05_25_000001 pernah di-rollback, down()-nya sudah membuat
        // ulang tabel audit_logs versi lama dan create() di sini akan gagal.
        if (Schema::connection('oltp')->hasTable('audit_logs')) {
            return;
        }

        Schema::connection('oltp')->create('audit_logs', function (Blueprint $table) {
            $table->id();

            // ── Pelaku ────────────────────────────────────────────────
            // 'user'   : staf mana pun (Ketua Tracer, Tim Tracer, Kaprodi, …)
            // 'alumni' : pemilik data yang bertindak atas datanya sendiri
            // 'system' : ETL terjadwal, perintah CLI, pekerjaan antrean
            $table->string('actor_type', 20)->default('system');
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_label', 150)->nullable();

            // ── Perbuatan ─────────────────────────────────────────────
            // Bertitik, mis. 'consent.granted', 'alumni.updated',
            // 'export.ministry', 'auth.login_failed'.
            $table->string('action', 100);

            // ── Sasaran ───────────────────────────────────────────────
            $table->string('entity_type', 100)->nullable();
            $table->string('entity_id', 64)->nullable();
            $table->unsignedBigInteger('subject_alumni_id')->nullable();

            // ── Keadaan saat kejadian ─────────────────────────────────
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();

            // Ringkasan bebas: berapa baris terekspor, kolom apa yang
            // berubah, filter apa yang dipakai. Nilai data pribadi TIDAK
            // pernah masuk ke sini utuh — PersonalData::mask() dulu, kalau
            // tidak tabel log berubah menjadi salinan kedua yang justru
            // tidak terenkripsi.
            $table->json('context')->nullable();

            $table->timestamp('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));

            $table->index(['actor_type', 'actor_id', 'created_at']);
            $table->index(['entity_type', 'entity_id']);
            $table->index(['subject_alumni_id', 'created_at']);
            $table->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::connection('oltp')->dropIfExists('audit_logs');
    }
};
