<?php

namespace App\Jobs;

use App\Mail\AlumniAccountIssuedMail;
use App\Models\Transactional\AlumniEmailLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Mengirim SATU email "Terbitkan Akun" dan mencatat hasilnya ke
 * alumni_email_log (kind=account) -- lihat komentar di migrasi tabel itu untuk
 * kenapa pencatatan per-baris ini perlu ada sama sekali.
 *
 * Membungkus Mail::send() di dalam job sendiri (bukan memakai
 * AlumniAccountIssuedMail::ShouldQueue + Mail::queue() langsung) supaya ada
 * satu tempat pasti yang tahu KAPAN percobaan benar-benar berakhir --
 * sukses atau gagal permanen -- dan bisa menulis status itu ke baris log
 * yang sudah dibuat controller sebelum job ini dikirim ke antrean.
 *
 * $password HANYA hidup di payload job ini (di tabel `jobs`, terenkripsi
 * proses ke proses lewat serialisasi PHP biasa) sampai handle() selesai.
 * Tidak pernah ditulis ke alumni_email_log.
 */
class SendAlumniAccountEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * SMTP transient (jaringan, rate limit sesaat) layak dicoba ulang;
     * kredensial SMTP salah atau sender ditolak akan gagal lagi persis sama
     * di setiap percobaan, jadi tiga cukup -- bukan untuk "menang melawan"
     * kegagalan permanen, hanya menyerap gangguan sesaat.
     */
    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 30, 90];

    public function __construct(
        private readonly int $logId,
        private readonly string $nim,
        private readonly string $name,
        private readonly string $password,
        private readonly string $email,
        private readonly string $loginUrl,
    ) {}

    public function handle(): void
    {
        // Baris log bisa sudah ditandai 'canceled' (POST
        // /alumni/email-batches/{batchId}/cancel) di antara saat job ini
        // masuk antrean dan saat worker sungguh memprosesnya -- dicek di
        // sini, bukan cuma di controller, karena controller tidak punya
        // kendali atas job yang sudah terlanjur ada di tabel `jobs`.
        if (AlumniEmailLog::whereKey($this->logId)->value('status') !== 'queued') {
            return;
        }

        Mail::to($this->email)->send(
            new AlumniAccountIssuedMail($this->nim, $this->name, $this->password, $this->loginUrl, $this->email)
        );

        AlumniEmailLog::whereKey($this->logId)->update(['status' => 'sent']);
    }

    /**
     * Dipanggil worker setelah SELURUH percobaan (tries) habis. Baris log
     * baru ditandai gagal DI SINI, bukan di percobaan pertama yang gagal --
     * kalau percobaan kedua/ketiga berhasil, handle() di atas yang menang
     * dan baris ini tidak pernah tersentuh.
     */
    public function failed(Throwable $exception): void
    {
        AlumniEmailLog::whereKey($this->logId)->update([
            'status'        => 'failed',
            'error_message' => Str::limit($exception->getMessage(), 500),
        ]);
    }
}
