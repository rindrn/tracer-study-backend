<?php

namespace App\Console\Commands;

use App\Models\Transactional\EtlRun;
use App\Services\ETL\EtlOrchestratorService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Entry point ETL. Jalankan via: php artisan etl:run
 *
 * Untuk TESTING (per beberapa menit), daftarkan di routes/console.php:
 *   Schedule::command('etl:run')->everyFiveMinutes();
 *
 * Untuk PRODUCTION (mingguan):
 *   Schedule::command('etl:run')->weeklyOn(1, '01:00');
 *
 * PENCATATAN (LAP-08)
 * -------------------
 * Setiap eksekusi menulis satu baris tracer_oltp.etl_runs, sama seperti
 * jalur queue (RunEtlJob). Sebelumnya hanya jalur queue yang mencatat,
 * sedangkan perintah inilah yang justru jalan rutin tiap Senin — akibatnya
 * belasan snapshot di dim_waktu tidak punya baris log padanan, dan
 * pertanyaan sesederhana "ETL Senin kemarin berhasil atau tidak?" tidak
 * bisa dijawab dari mana pun. Ringkasan 12 tahapnya dulu hanya tercetak ke
 * terminal lalu hilang bersama sesinya.
 *
 * Pencatatan sengaja tidak boleh menggagalkan pemuatan data: barisnya
 * ditulis lewat koneksi 'oltp', sedangkan ETL bertransaksi di koneksi
 * 'olap', jadi keduanya transaksi terpisah. Kalau penulisan log gagal,
 * ETL tetap jalan — lihat catatan di setiap blok try di bawah.
 *
 * FULL SNAPSHOT SECARA DEFAULT (bukan lagi incremental)
 * ------------------------------------------------------
 * FR-063 (SRS) mengharuskan ETL mingguan "memperbarui data fakta" dan
 * "pembaruan agregasi data yang digunakan dashboard" -- dibaca sebagai:
 * hasil satu run ETL harus jadi potret LENGKAP & terkini, bukan cuma
 * tempelan response yang baru masuk sejak run sebelumnya.
 *
 * Sebelum ini, defaultnya incremental (force=false): fact_tracer_study
 * untuk id_waktu terbaru cuma berisi beberapa baris (alumni yang baru
 * submit), sedangkan dashboard Luaran Pekerjaan/Analisis Capaian Lulusan
 * DEFAULT memilih snapshot TERBARU itu -- hasilnya KPI dihitung dari
 * sampel kecil yang menyesatkan (mis. 92,9% dari 14 alumni, padahal
 * populasi sebenarnya ~5.200 dan angka aslinya 88%), walau
 * tracer_oltp.etl_runs correctly menunjukkan status completed.
 *
 * --incremental tetap disediakan untuk kasus performa (histori sangat
 * besar) di mana admin SADAR snapshot itu bukan potret lengkap dan
 * memang hanya ingin memproses delta -- bukan lagi perilaku default.
 */
class RunEtlSnapshot extends Command
{
    protected $signature = 'etl:run
                            {--incremental : Hanya proses response baru sejak snapshot terakhir -- snapshot hasilnya TIDAK lengkap, lihat catatan class ini}
                            {--reason=cli_manual : Penanda pemicu yang dicatat di kolom etl_runs.reason}';

    protected $description = 'Jalankan ETL snapshot penuh dari tracer_oltp ke OLAP (schema public)';

    public function handle(EtlOrchestratorService $orchestrator): int
    {
        $this->info('=== SmartTracer ETL Snapshot dimulai ===');
        $startedAt = now();

        $run = $this->openRun();

        try {
            $result = $orchestrator->run(force: ! $this->option('incremental'));
        } catch (Throwable $e) {
            $this->closeRun($run, [
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
                'finished_at'   => now(),
            ]);

            // Dilempar ulang supaya penjadwal melihatnya sebagai kegagalan
            // (onFailure di routes/console.php) dan exit code-nya bukan 0.
            throw $e;
        }

        $this->closeRun($run, [
            'status'      => 'completed',
            'id_waktu'    => $result->idWaktu,
            'summary'     => $result->toArray(),
            'finished_at' => now(),
        ]);

        $this->table(
            ['Tahap', 'Diproses', 'Insert', 'Skip'],
            $result->toTableRows()
        );

        $this->info(sprintf(
            '=== ETL selesai dalam %.2f detik. id_waktu snapshot: %d ===',
            now()->diffInSeconds($startedAt, true),
            $result->idWaktu
        ));

        if ($run !== null) {
            $this->info("Tercatat di etl_runs id={$run->id}.");
        }

        return self::SUCCESS;
    }

    /**
     * Buka baris pencatatan. Mengembalikan null bila gagal — ETL tetap
     * dijalankan tanpa catatan, karena kehilangan satu baris log jauh lebih
     * ringan daripada melewatkan pemuatan data mingguan.
     *
     * triggered_by dibiarkan NULL: kolom itu foreign key ke users, dan
     * eksekusi dari CLI maupun penjadwal memang tidak punya pengguna.
     */
    private function openRun(): ?EtlRun
    {
        try {
            return EtlRun::create([
                'status'     => 'running',
                'reason'     => (string) $this->option('reason'),
                'started_at' => now(),
            ]);
        } catch (Throwable $e) {
            $this->warn("Gagal mencatat ke etl_runs: {$e->getMessage()}. ETL tetap dilanjutkan.");
            return null;
        }
    }

    /** @param array<string,mixed> $attributes */
    private function closeRun(?EtlRun $run, array $attributes): void
    {
        if ($run === null) {
            return;
        }

        try {
            $run->update($attributes);
        } catch (Throwable $e) {
            $this->warn("Gagal memperbarui etl_runs id={$run->id}: {$e->getMessage()}.");
        }
    }
}
