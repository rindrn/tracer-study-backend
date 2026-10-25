<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menormalkan nilai `users.role` warisan ke nilai kanonik.
 *
 * Latar: basis data produksi berisi nilai role lama (mis. 'kotc', 'p2mpp')
 * yang TIDAK ada di enum aplikasi (User::ROLES_ALL) maupun di RoleSeeder.
 * Seluruh gate memakai nilai baru ('head_tracer', 'wadir', dst). Akibatnya
 * akun dengan role lama lolos login tetapi setiap aksi terproteksi ditolak
 * 403 ("unauth") -- lihat RoleAccessMiddleware. Band-aid sebelumnya cuma
 * memetakan sebagian nilai di middleware (LEGACY_ROLE_MAP) dan di frontend
 * (mapBackendRole di fe-tracer-study/src/lib/rbac.ts), dan 'kotc' kelewat di
 * keduanya -- jadi bug ini hanya muncul di atas data produksi, bukan di
 * basis data hasil seed lokal.
 *
 * Migrasi ini memindahkan normalisasi itu ke DATA, sekali untuk selamanya:
 * berapa pun kali di-restore dari dump produksi, `migrate` meluruskan
 * nilainya. Peta harus tetap sinkron dengan LEGACY_ROLE_MAP (backend) dan
 * mapBackendRole (frontend).
 *
 * Idempotent: hanya menyentuh baris yang masih bernilai lama; jalan dua kali
 * tidak berefek. Tidak reversible secara bermakna (nilai lama sudah usang),
 * jadi down() sengaja no-op.
 */
return new class extends Migration
{
    protected $connection = 'oltp';

    /** Nilai role lama => nilai kanonik. Sinkron dgn RoleAccessMiddleware. */
    private const LEGACY_ROLE_MAP = [
        'admin'          => 'head_tracer',
        'kotc'           => 'head_tracer',
        'p2mpp'          => 'wadir',
        'prodi'          => 'kaprodi',
        'ketua_fakultas' => 'dekan',
    ];

    public function up(): void
    {
        foreach (self::LEGACY_ROLE_MAP as $old => $new) {
            DB::connection($this->connection)
                ->table('users')
                ->where('role', $old)
                ->update(['role' => $new]);
        }
    }

    public function down(): void
    {
        // Sengaja kosong: nilai role lama sudah usang dan tidak dipulihkan.
    }
};
