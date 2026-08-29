<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Email "Terbitkan Akun" -- membawa kredensial alumni yang baru diterbitkan
 * (App\Services\Transactional\AlumniCredentialService::issue()).
 *
 * BUKAN ShouldQueue di sini -- pengantreannya dipegang App\Jobs\
 * SendAlumniAccountEmailJob yang membungkus Mail::send() (bukan
 * Mail::queue() atas Mailable ini secara langsung), supaya ada satu tempat
 * pasti yang tahu kapan pengiriman satu alumni benar-benar sukses/gagal dan
 * bisa menuliskannya ke alumni_email_log. Lihat job itu untuk
 * detailnya.
 *
 * $password HANYA hidup selama job pembungkusnya diproses. Ia datang
 * langsung dari teks polos yang dibangkitkan
 * AlumniCredentialService::issue() dan tidak pernah ditulis ke tabel apa
 * pun -- lihat catatan di kelas itu.
 */
class AlumniAccountIssuedMail extends Mailable
{
    use SerializesModels;

    public function __construct(
        public readonly string $nim,
        public readonly string $name,
        public readonly string $password,
        public readonly string $loginUrl,
        public readonly string $email,
    ) {}

    public function build(): self
    {
        return $this
            ->subject('Akun SmartTracer Anda Sudah Aktif')
            ->view('emails.alumni-account-issued');
    }
}
