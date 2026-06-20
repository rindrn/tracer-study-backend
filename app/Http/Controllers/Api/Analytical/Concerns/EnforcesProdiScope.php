<?php

namespace App\Http\Controllers\Api\Analytical\Concerns;

use App\Models\Transactional\User;
use Illuminate\Http\Request;

/**
 * Trait EnforcesProdiScope
 *
 * Di-use oleh semua analytical controller. Tugasnya satu:
 * kalau yang login adalah kaprodi, paksa filter nama_prodi
 * ke prodinya sendiri — terlepas dari query param apapun
 * yang dikirim frontend.
 *
 * Untuk role lain (admin, p2mpp, head_tracer, wadir):
 * filter nama_prodi dibaca dari query param apa adanya
 * (bisa diisi atau kosong = semua prodi).
 *
 * Cara pakai di controller:
 *
 *   use App\Http\Controllers\Api\Analytical\Concerns\EnforcesProdiScope;
 *
 *   class KeterserapanController extends Controller
 *   {
 *       use EnforcesProdiScope;
 *
 *       public function bar(Request $request): JsonResponse
 *       {
 *           $params = $this->scopedParams($request);
 *           // $params['nama_prodi'] sudah otomatis di-set ke nama prodi
 *           // kaprodi jika yang login kaprodi, atau dari query param jika bukan.
 *           ...
 *       }
 *   }
 */
trait EnforcesProdiScope
{
    /**
     * Ambil semua query params + enforce scope kaprodi.
     *
     * Mengembalikan array params yang aman diteruskan ke repository:
     *   - jenjang
     *   - jurusan
     *   - nama_prodi   ← di-override jika kaprodi
     *   - tahun_lulus
     *   - minggu_snapshot
     *   + semua param lain dari request (untuk endpoint yang punya param tambahan)
     *
     * @return array<string, mixed>
     */
    protected function scopedParams(Request $request): array
    {
        /** @var User $user */
        $user   = $request->user();
        $params = $request->query();

        if ($user->isKaprodi()) {
            // Kaprodi hanya boleh lihat prodinya sendiri.
            // Paksa override nama_prodi — abaikan apapun yang dikirim FE.
            $prodiName = $user->program?->name;

            if ($prodiName !== null) {
                $params['nama_prodi'] = $prodiName;
            }

            // Juga paksa jenjang dan jurusan agar konsisten dengan prodinya,
            // mencegah query "lintas jenjang" yang tidak relevan untuk kaprodi.
            $params['jenjang'] = $user->program?->jenjang ?? ($params['jenjang'] ?? null);
            $params['jurusan'] = $user->program?->jurusan ?? ($params['jurusan'] ?? null);
        }

        return $params;
    }

    /**
     * Helper: ambil satu param dari scopedParams.
     * Shorthand untuk controller yang butuh param satu per satu.
     */
    protected function scopedParam(Request $request, string $key, mixed $default = null): mixed
    {
        return $this->scopedParams($request)[$key] ?? $default;
    }

    /**
     * Validasi bahwa kaprodi tidak bisa request data prodi lain
     * melalui URL param. Lempar 403 kalau ketahuan.
     *
     * Dipakai di endpoint drill-down yang menerima nama_prodi
     * sebagai path/query param eksplisit (bukan dari global filter).
     */
    protected function assertProdiAccess(Request $request, string $requestedProdi): void
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->isKaprodi() && $user->program?->name !== $requestedProdi) {
            abort(403, 'Anda hanya dapat mengakses data program studi Anda sendiri.');
        }
    }
}