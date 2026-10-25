<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Perbaiki pemetaan kategori "kesesuaian bidang" yang salah di
 * public.kpi_category_mapping (digunakan_oleh = 'kesesuaian_bidang_relevance').
 *
 * Masalah: dua level yang seharusnya "sesuai" tidak terhitung sesuai —
 *   - option_code 1 (Sangat Erat): baris aktifnya 'tidak_sesuai' (ter-toggle
 *     bolak-balik lewat UI admin; state terakhir keliru).
 *   - option_code 3 (Cukup Erat): TIDAK punya baris mapping sama sekali,
 *     jadi tidak masuk pembilang maupun penyebut.
 * Akibatnya pct_sesuai jeblok (~28%) padahal seharusnya ~83% (Sangat Erat +
 * Erat + Cukup Erat = sesuai; Kurang Erat + Tidak Sama Sekali = tidak_sesuai).
 *
 * Tabel ini data runtime (diedit via KpiCategoryMappingController / UI), bukan
 * dihasilkan kode — jadi koreksi lewat `tinker` tidak reproducible dan bisa
 * hilang. Migrasi ini membawanya ke source: idempotent, jalan otomatis saat
 * `migrate --force` di entrypoint, dan tidak menyentuh kombinasi lain.
 *
 * Catatan point-in-time: measure Cube memetakan level -> sesuai/tidak saat
 * query berdasarkan mapping yang berlaku pada tanggal_refresh tiap snapshot.
 * Koreksi ini berlaku mulai tanggal deploy, jadi angka di dashboard ikut benar
 * pada SNAPSHOT ETL BERIKUTNYA (jalankan `php artisan etl:run`, atau tunggu
 * scheduler). Sengaja tidak menulis ulang tanggal/riwayat snapshot lama.
 */
return new class extends Migration
{
    protected $connection = 'olap';

    private const ROLE      = 'relevansi_bidang';
    private const DIGUNAKAN = 'kesesuaian_bidang_relevance';

    /** option_code => label; keduanya seharusnya 'sesuai'. */
    private const SESUAI_CODES = [
        '1' => 'Sangat Erat',
        '3' => 'Cukup Erat',
    ];

    public function up(): void
    {
        if (! Schema::connection($this->connection)->hasTable('kpi_category_mapping')) {
            return;
        }

        foreach (self::SESUAI_CODES as $code => $label) {
            $base = fn() => DB::connection($this->connection)
                ->table('kpi_category_mapping')
                ->where('semantic_role', self::ROLE)
                ->where('digunakan_oleh', self::DIGUNAKAN)
                ->where('option_code', (string) $code);

            // Sudah benar (ada baris aktif 'sesuai')? -> tidak ada yang perlu
            // dikerjakan. Membuat migrasi aman di DB hasil seed yang sudah bersih.
            $alreadyCorrect = (clone $base())
                ->where('kpi_category', 'sesuai')
                ->where('is_active', true)
                ->whereNull('deactivated_at')
                ->exists();

            if ($alreadyCorrect) {
                continue;
            }

            // Nonaktifkan baris aktif yang keliru untuk kombinasi ini.
            (clone $base())
                ->where('is_active', true)
                ->update([
                    'is_active'      => false,
                    'deactivated_at' => now(),
                    'updated_at'     => now(),
                ]);

            // Pasang baris aktif 'sesuai' yang benar.
            DB::connection($this->connection)->table('kpi_category_mapping')->insert([
                'semantic_role'         => self::ROLE,
                'option_code'           => (string) $code,
                'option_label_snapshot' => $label,
                'kpi_category'          => 'sesuai',
                'kpi_category_label'    => 'Sesuai Bidang',
                'digunakan_oleh'        => self::DIGUNAKAN,
                'is_active'             => true,
                'effective_date'        => now()->toDateString(),
                'created_at'            => now(),
                'updated_at'            => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Sengaja no-op: ini koreksi konfigurasi yang keliru, bukan sesuatu
        // yang bermakna untuk dipulihkan ke keadaan salah.
    }
};
