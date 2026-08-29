<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Pengingat: Lengkapi Kuesioner Tracer Study Anda</title>
</head>
<body style="margin:0;padding:0;background-color:#eef2f7;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#eef2f7;padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="480" cellpadding="0" cellspacing="0" style="background-color:#ffffff;border-radius:12px;overflow:hidden;">

                    {{-- Header sama seperti alumni-account-issued.blade.php: logo di-embed
                         langsung ke email ($message->embed(...)), tidak bergantung URL. --}}
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
                            {{-- Lencana pengingat -- lonceng, warna amber. Unicode+CSS, sama
                                 alasannya seperti lencana centang di alumni-account-issued.blade.php. --}}
                            <table role="presentation" cellpadding="0" cellspacing="0" align="center" style="margin:0 auto 20px;">
                                <tr>
                                    <td width="64" height="64" align="center" valign="middle" style="background-color:#d97706;border-radius:50%;color:#ffffff;font-size:28px;line-height:64px;">
                                        &#128276;
                                    </td>
                                </tr>
                            </table>

                            <h1 style="margin:0 0 8px;font-size:22px;color:#111827;">Kuesioner Anda Menunggu</h1>
                            <p style="margin:0;font-size:14px;color:#6b7280;line-height:1.6;">
                                Beberapa menit saja untuk melengkapinya.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:0 32px 32px;">
                            <p style="margin:0 0 4px;font-size:15px;font-weight:bold;color:#111827;">Halo, {{ $name }}!</p>
                            <p style="margin:0 0 20px;font-size:14px;color:#374151;line-height:1.6;">
                                Kami belum menerima jawaban Anda untuk <strong>kuesioner Tracer Study</strong>
                                &mdash; survei alumni {{ config('institution.name') }} yang menelusuri jejak karier
                                dan kesesuaian bidang kerja dengan pendidikan yang telah Anda tempuh. Data yang
                                Anda berikan menjadi masukan langsung bagi kampus untuk meningkatkan kualitas
                                kurikulum dan proses pembelajaran.
                            </p>
                            <p style="margin:0 0 20px;font-size:14px;color:#374151;line-height:1.6;">
                                Akun Anda (NIM <strong>{{ $nim }}</strong>) sudah aktif. Silakan masuk dan lengkapi
                                kuesionernya menggunakan kata sandi yang sudah Anda miliki.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:0 32px 32px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 24px;">
                                <tr>
                                    <td align="center" style="background-color:#1d4ed8;border-radius:8px;">
                                        <a href="{{ $loginUrl }}" style="display:block;padding:14px 24px;color:#ffffff;font-size:14px;font-weight:bold;text-decoration:none;letter-spacing:0.3px;">
                                            &#8594;&nbsp; MASUK &amp; ISI KUESIONER
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <hr style="border:none;border-top:1px solid #e5e7eb;margin:0 0 20px;">

                            <p style="margin:0 0 16px;font-size:13px;color:#6b7280;line-height:1.6;">
                                Lupa kata sandi? Hubungi petugas tracer study untuk menerbitkan ulang akun Anda.
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
