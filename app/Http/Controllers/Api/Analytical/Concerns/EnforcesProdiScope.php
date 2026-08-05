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
            $program = $user->program;

            // Kaprodi tanpa prodi yang bisa ditelusuri TIDAK boleh diteruskan.
            // Sebelumnya nama_prodi sekadar tidak di-set, dan akibatnya query
            // berjalan tanpa pembatasan sama sekali — kaprodi melihat data
            // seluruh institusi. Gagal terang-terangan lebih baik daripada
            // membocorkan data diam-diam.
            if ($program === null) {
                abort(403, 'Akun kaprodi ini belum tertaut ke program studi mana pun. Hubungi pengelola.');
            }

            // Nama prodi TIDAK unik: tujuh nama dipakai dua prodi sekaligus
            // pada jenjang berbeda (Teknik Informatika D3 dan D4, Akuntansi
            // D3 dan D4, dan seterusnya). Menyaring dengan nama saja membuat
            // kaprodi D3 ikut melihat angka D4 — bukan sekadar salah hitung,
            // tapi data prodi lain.
            //
            // Pasangan (nama, jenjang) unik untuk seluruh 36 prodi, jadi
            // keduanya WAJIB dipasang bersama. Jenjang di sini diambil dari
            // kolom `degree`; baris sebelumnya membaca `$program->jenjang`
            // yang tidak pernah ada di tabel programs maupun model Program,
            // sehingga selalu null dan penyaring jenjang tidak pernah
            // terpasang sama sekali.
            $params['nama_prodi'] = $program->name;
            $params['jenjang']    = $program->degree;
            $params['jurusan']    = $program->jurusan;
        } elseif ($user->isKajur()) {
            // Kajur dibatasi ke jurusannya, sejalan dengan lapisan
            // transaksional yang sudah melakukannya sejak awal — lihat
            // RingkasanTahunController dan AdminAlumniService. Lapisan
            // analitik satu-satunya yang tertinggal: sebelum ini kajur
            // melihat angka seluruh institusi (505 alumni) padahal
            // jurusannya hanya 29.
            //
            // Nama prodi dan jenjang TIDAK dipaksa: kajur memang berhak
            // menyaring antar prodi di dalam jurusannya. Penyaring jurusan
            // tetap terpasang, jadi permintaan ke prodi luar jurusan
            // menghasilkan irisan kosong, bukan data prodi lain.
            if ($user->jurusan === null || $user->jurusan === '') {
                abort(403, 'Akun kajur ini belum tertaut ke jurusan mana pun. Hubungi pengelola.');
            }

            $params['jurusan'] = $user->jurusan;
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
     *
     * Pemeriksaan hanya bisa membandingkan nama, karena itulah yang dikirim
     * pemanggil. Dengan tujuh nama yang dipakai dua jenjang, lolosnya
     * pemeriksaan ini belum menjamin datanya menyempit ke satu prodi —
     * penyempitannya dikerjakan scopedParams() yang memasang nama DAN jenjang.
     * Jadi endpoint drill-down tetap wajib memakai keduanya, bukan hanya
     * memanggil pemeriksaan ini lalu meneruskan nama mentah ke repository.
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