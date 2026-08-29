<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Email pengingat isi kuesioner Tracer Study -- untuk alumni yang SUDAH
 * punya akun (password_issued_at terisi) tapi belum menyelesaikan
 * kuesioner. TIDAK membawa kata sandi -- ini bukan penerbitan akun,
 * kredensial yang sudah ada tidak diregenerasi.
 *
 * Struktur mirip AlumniAccountIssuedMail (header logo di-embed, bukan
 * ShouldQueue -- dikirim lewat App\Jobs\SendAlumniReminderEmailJob yang
 * membungkus Mail::send() untuk alasan yang sama: satu tempat pasti yang
 * tahu kapan pengiriman sukses/gagal, ditulis ke alumni_email_log).
 */
class AlumniReminderMail extends Mailable
{
    use SerializesModels;

    public function __construct(
        public readonly string $nim,
        public readonly string $name,
        public readonly string $loginUrl,
    ) {}

    public function build(): self
    {
        return $this
            ->subject('Pengingat: Lengkapi Kuesioner Tracer Study Anda')
            ->view('emails.alumni-reminder');
    }
}
