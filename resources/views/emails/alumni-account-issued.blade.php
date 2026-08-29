<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Akun SmartTracer Anda Sudah Aktif</title>
</head>
<body style="margin:0;padding:0;background-color:#eef2f7;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#eef2f7;padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="480" cellpadding="0" cellspacing="0" style="background-color:#ffffff;border-radius:12px;overflow:hidden;">

                    {{-- Header: logo + nama institusi. $message->embed() DIBAWA BERSAMA
                         email itu sendiri (lampiran inline/cid), bukan <img src> ke URL
                         jarak jauh -- makanya selalu tampil, di lokal maupun produksi,
                         terlepas FRONTEND_URL bisa dijangkau publik atau tidak. Berkasnya
                         disalin ke resources/mail-assets/logo-mark.png supaya backend tidak
                         perlu menjangkau layanan fe-tracer-study sama sekali untuk mengirim
                         satu email. alt="" sengaja kosong (dekoratif) -- nama institusi
                         sudah tertulis sebagai teks di sebelahnya. --}}
                    <tr>
                        <td style="background-color:#1d4ed8;padding:20px 32px;">
                            <table role="presentation" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="padding-right:12px;">
                                        <img src="{{ $message->embed(resource_path('mail-assets/logo-mark.png')) }}" alt="" width="32" height="32" style="display:block;border-radius:6px;background-color:#ffffff;">
                                    </td>
                                    <td>
                                        <span style="color:#ffffff;font-size:18px;font-weight:bold;">{{ config('institution.name') }}</span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:40px 32px 32px;text-align:center;">
                            {{-- Lencana "berhasil" -- lingkaran hijau + centang, dibangun dari
                                 CSS+Unicode, bukan gambar. Tidak bergantung URL apa pun, jadi
                                 selalu tampil sama persis di lokal maupun produksi. --}}
                            <table role="presentation" cellpadding="0" cellspacing="0" align="center" style="margin:0 auto 20px;">
                                <tr>
                                    <td width="64" height="64" align="center" valign="middle" style="background-color:#16a34a;border-radius:50%;color:#ffffff;font-size:30px;font-weight:bold;line-height:64px;">
                                        &#10003;
                                    </td>
                                </tr>
                            </table>

                            <h1 style="margin:0 0 8px;font-size:22px;color:#111827;">Akun Anda Berhasil Dibuat</h1>
                            <p style="margin:0;font-size:14px;color:#6b7280;line-height:1.6;">
                                Satu langkah lagi menuju pengisian kuesioner Tracer Study.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:0 32px 32px;">
                            <p style="margin:0 0 4px;font-size:15px;font-weight:bold;color:#111827;">Halo, {{ $name }}!</p>
                            <p style="margin:0 0 12px;font-size:14px;color:#374151;line-height:1.6;">
                                Akun SmartTracer Anda telah diterbitkan. Akun ini digunakan untuk mengisi
                                <strong>kuesioner Tracer Study</strong> &mdash; survei alumni {{ config('institution.name') }}
                                yang menelusuri jejak karier, masa tunggu kerja, dan kesesuaian bidang kerja dengan
                                pendidikan yang telah Anda tempuh.
                            </p>
                            <p style="margin:0 0 20px;font-size:14px;color:#374151;line-height:1.6;">
                                Kami mohon kesediaan Anda untuk login dan melengkapi kuesioner tersebut. Data yang
                                Anda berikan menjadi masukan langsung bagi kampus untuk mengevaluasi dan meningkatkan
                                kualitas kurikulum serta proses pembelajaran. Gunakan kredensial berikut untuk masuk:
                            </p>

                            {{-- Kartu info: Email, Username (NIM), Password. Ikon lingkaran
                                 dibangun dari Unicode juga, alasan sama seperti lencana di atas. --}}
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;margin:0 0 24px;">
                                <tr>
                                    <td style="padding:16px 20px;border-bottom:1px solid #e5e7eb;">
                                        <table role="presentation" cellpadding="0" cellspacing="0" width="100%">
                                            <tr>
                                                <td width="36" valign="middle">
                                                    <table role="presentation" cellpadding="0" cellspacing="0">
                                                        <tr><td width="28" height="28" align="center" valign="middle" style="background-color:#dbeafe;border-radius:50%;color:#1d4ed8;font-size:14px;line-height:28px;">&#9993;</td></tr>
                                                    </table>
                                                </td>
                                                <td valign="middle" style="padding-left:8px;">
                                                    <span style="display:block;font-size:12px;color:#6b7280;">Email</span>
                                                    <span style="display:block;font-size:14px;font-weight:bold;color:#111827;">{{ $email }}</span>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:16px 20px;border-bottom:1px solid #e5e7eb;">
                                        <table role="presentation" cellpadding="0" cellspacing="0" width="100%">
                                            <tr>
                                                <td width="36" valign="middle">
                                                    <table role="presentation" cellpadding="0" cellspacing="0">
                                                        <tr><td width="28" height="28" align="center" valign="middle" style="background-color:#dbeafe;border-radius:50%;color:#1d4ed8;font-size:14px;line-height:28px;">&#128100;</td></tr>
                                                    </table>
                                                </td>
                                                <td valign="middle" style="padding-left:8px;">
                                                    <span style="display:block;font-size:12px;color:#6b7280;">Username (NIM)</span>
                                                    <span style="display:block;font-size:14px;font-weight:bold;color:#111827;">{{ $nim }}</span>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:16px 20px;">
                                        <table role="presentation" cellpadding="0" cellspacing="0" width="100%">
                                            <tr>
                                                <td width="36" valign="middle">
                                                    <table role="presentation" cellpadding="0" cellspacing="0">
                                                        <tr><td width="28" height="28" align="center" valign="middle" style="background-color:#dbeafe;border-radius:50%;color:#1d4ed8;font-size:14px;line-height:28px;">&#128274;</td></tr>
                                                    </table>
                                                </td>
                                                <td valign="middle" style="padding-left:8px;">
                                                    <span style="display:block;font-size:12px;color:#6b7280;margin-bottom:2px;">Password</span>
                                                    <span style="display:inline-block;padding:4px 10px;background-color:#eef2f7;border-radius:4px;font-family:'Courier New',monospace;font-size:14px;font-weight:bold;color:#111827;letter-spacing:0.5px;">{{ $password }}</span>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            {{-- Tombol login --}}
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 24px;">
                                <tr>
                                    <td align="center" style="background-color:#1d4ed8;border-radius:8px;">
                                        <a href="{{ $loginUrl }}" style="display:block;padding:14px 24px;color:#ffffff;font-size:14px;font-weight:bold;text-decoration:none;letter-spacing:0.3px;">
                                            &#8594;&nbsp; LOGIN KE AKUN
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            {{-- Tip keamanan --}}
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#eff6ff;border-radius:8px;margin:0 0 24px;">
                                <tr>
                                    <td style="padding:16px 18px;">
                                        <table role="presentation" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td width="36" valign="top">
                                                    <table role="presentation" cellpadding="0" cellspacing="0">
                                                        <tr><td width="28" height="28" align="center" valign="middle" style="background-color:#dbeafe;border-radius:50%;color:#1d4ed8;font-size:14px;line-height:28px;">&#128737;</td></tr>
                                                    </table>
                                                </td>
                                                <td valign="top" style="padding-left:8px;">
                                                    <span style="display:block;font-size:14px;font-weight:bold;color:#1d4ed8;margin-bottom:4px;">Demi keamanan akun</span>
                                                    <span style="display:block;font-size:13px;color:#374151;line-height:1.6;">
                                                        Kata sandi ini tidak dapat ditampilkan ulang setelah email ini. Jika lupa,
                                                        hubungi petugas tracer study untuk menerbitkan ulang.
                                                    </span>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <hr style="border:none;border-top:1px solid #e5e7eb;margin:0 0 20px;">

                            <p style="margin:0 0 16px;font-size:13px;color:#6b7280;line-height:1.6;">
                                Jika Anda tidak merasa meminta akun ini, abaikan email ini.
                            </p>

                            <p style="margin:0;font-size:13px;color:#374151;line-height:1.6;">
                                Terima kasih,<br>
                                <strong>Tim SmartTracer</strong>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="background-color:#f9fafb;padding:16px 32px;text-align:center;border-top:1px solid #e5e7eb;">
                            <p style="margin:0;font-size:11px;color:#9ca3af;">
                                &copy; {{ date('Y') }} SmartTracer &mdash; Sistem Tracer Study {{ config('institution.name') }}
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
