<?php

namespace App\Jobs;

use App\Mail\AlumniReminderMail;
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
 * Mengirim SATU email pengingat kuesioner dan mencatat hasilnya ke
 * alumni_email_log (kind=reminder). Struktur identik
 * App\Jobs\SendAlumniAccountEmailJob -- lihat komentar di sana untuk
 * alasan tries/backoff dan kenapa Mail::send() dibungkus job sendiri alih-
 * alih ShouldQueue di Mailable. Duplikasi kecil ini sengaja tidak
 * diabstraksi ke kelas dasar bersama: dua job kecil, kejelasan lokal lebih
 * berguna di sini daripada DRY.
 */
class SendAlumniReminderEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 30, 90];

    public function __construct(
        private readonly int $logId,
        private readonly string $nim,
        private readonly string $name,
        private readonly string $email,
        private readonly string $loginUrl,
    ) {}

    public function handle(): void
    {
        // Lihat komentar identik di SendAlumniAccountEmailJob::handle().
        if (AlumniEmailLog::whereKey($this->logId)->value('status') !== 'queued') {
            return;
        }

        Mail::to($this->email)->send(
            new AlumniReminderMail($this->nim, $this->name, $this->loginUrl)
        );

        AlumniEmailLog::whereKey($this->logId)->update(['status' => 'sent']);
    }

    public function failed(Throwable $exception): void
    {
        AlumniEmailLog::whereKey($this->logId)->update([
            'status'        => 'failed',
            'error_message' => Str::limit($exception->getMessage(), 500),
        ]);
    }
}
