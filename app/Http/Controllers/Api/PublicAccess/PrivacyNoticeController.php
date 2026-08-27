<?php

namespace App\Http\Controllers\Api\PublicAccess;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Ketentuan pemberitahuan privasi untuk halaman publik /kebijakan-privasi.
 *
 * KENAPA PUBLIK
 * -------------
 * Isi pemberitahuan yang sama sudah dikirim lewat `alumni/me/consent`, tapi
 * endpoint itu ber-guard 'alumni': hanya alumni yang SUDAH login yang bisa
 * membacanya. Padahal yang paling butuh membacanya justru orang yang belum
 * memutuskan mau mengisi atau tidak -- dan UU No. 27 Tahun 2022 menuntut
 * pemberitahuan diberikan SEBELUM pemrosesan, bukan setelah orangnya masuk.
 *
 * Dua nilai di sini adalah keputusan kebijakan institusi yang memang
 * diumumkan (versi pemberitahuan dan masa simpan), bukan rahasia operasional.
 * Tidak ada data alumni mana pun yang ikut keluar dari sini.
 */
class PrivacyNoticeController extends Controller
{
    // GET /api/public/privacy-notice
    public function show(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => [
                'notice_version'  => (string) config('privacy.notice_version'),
                'retention_years' => (int) config('privacy.retention_years'),
            ],
        ]);
    }
}
