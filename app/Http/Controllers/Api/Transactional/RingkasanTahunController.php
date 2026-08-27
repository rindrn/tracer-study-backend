<?php

namespace App\Http\Controllers\Api\Transactional;

use App\Http\Controllers\Controller;
use App\Repositories\Transactional\RingkasanTahunRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/meta/ringkasan-tahun
 *
 * Ringkasan agregat per tahun lulusan untuk halaman kartu tahun.
 * Payload sengaja dibuat kecil (~300 byte untuk 6 tahun) supaya bisa
 * dipanggil setiap kali halaman dibuka tanpa terasa.
 *
 * Cakupan data mengikuti jabatan pemanggil:
 *   - Kaprodi        : hanya program studinya
 *   - Kajur          : gabungan seluruh prodi dalam jurusan yang di-assign
 *                      admin (User::scopedProgramIds())
 *   - Dekan : gabungan seluruh prodi dalam fakultas yang di-assign
 *                      admin -- langsung agregat, tidak perlu memilih satu
 *                      jurusan dulu (Fase 5)
 *   - lainnya        : seluruh institusi
 *
 * Pembatasan dilakukan di sini, bukan di frontend, supaya sejalan dengan
 * pembatasan pada endpoint daftar alumni dan laporan.
 */
class RingkasanTahunController extends Controller
{
    public function __construct(
        private readonly RingkasanTahunRepository $repo,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $programIdIn = null;
        if ($user->isKajur() || $user->isDekan()) {
            $programIdIn = $user->scopedProgramIds();
            if (empty($programIdIn)) {
                $label = $user->isKajur() ? 'jurusan' : 'fakultas';
                abort(403, "Akun ini belum memiliki {$label} dengan prodi yang di-assign. Hubungi pengelola.");
            }
        }

        $scope = [
            'program_id'    => $user->isKaprodi() ? $user->program_id : null,
            'program_id_in' => $programIdIn,
        ];

        return response()->json([
            'success' => true,
            'data'    => $this->repo->byGraduationYear($scope),
        ]);
    }
}
