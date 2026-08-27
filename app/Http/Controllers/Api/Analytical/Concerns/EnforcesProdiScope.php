<?php

namespace App\Http\Controllers\Api\Analytical\Concerns;

use App\Models\Transactional\Program;
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
            // Kajur dibatasi ke jurusan yang di-assign admin (FK
            // `users.jurusan_id` → keanggotaan eksplisit lewat
            // `jurusan_program_scopes`), bukan lagi cocokkan teks
            // `users.jurusan`. `id_prodi_in` dikirim ke repository
            // analitik sebagai filter whereIn — kajur otomatis melihat
            // gabungan seluruh prodi dalam jurusannya (agregat), dan
            // permintaan ke prodi luar jurusan menghasilkan irisan kosong,
            // bukan data prodi lain.
            $ids = $user->scopedProgramIds();
            if (empty($ids)) {
                abort(403, 'Akun kajur ini belum tertaut ke jurusan dengan prodi yang di-assign. Hubungi pengelola.');
            }

            $params['id_prodi_in'] = $ids;

            // Kompatibilitas mundur: 9 repository Cube.js (Keterserapan,
            // Kesesuaian, Pendapatan, MasaTunggu, Pembiayaan, KompetensiGap,
            // MetodePembelajaran, Wirausaha, SebaranInstansi -- dashboard
            // Overview/Employment/Education/KPI) memanggil buildGlobalFilters()
            // langsung dengan argumen bernama per repo method, BUKAN lewat
            // buildGlobalFiltersFromArray() yang membaca id_prodi_in. Mengubah
            // ~48 titik pemanggilan di 9 berkas itu di luar cakupan sesi ini.
            // Supaya dashboard itu TIDAK meregresi (kajur melihat data
            // institusi), `jurusan` scalar tetap dipasang seperti sebelumnya
            // -- nilainya sekarang diambil dari relasi FK `jurusanEntity`
            // (nama Jurusan yang di-assign admin), bukan lagi kolom teks
            // `users.jurusan` mentah. Endpoint yang SUDAH dikonversi ke
            // id_prodi_in (RingkasanTahun, AdminAlumniService) mengabaikan
            // key ini.
            $params['jurusan'] = $user->jurusanEntity?->name;
        } elseif ($user->isDekan()) {
            // Dekan membawahi BEBERAPA jurusan sekaligus lewat
            // fakultas yang di-assign admin (`users.fakultas_id` →
            // `fakultas_jurusan_scopes` → `jurusan_program_scopes`).
            // Sama seperti kajur, scope-nya sekarang berupa daftar
            // program_id nyata (bukan cocokkan nama).
            $ids = $user->scopedProgramIds();
            if (empty($ids)) {
                abort(403, 'Akun dekan ini belum memiliki fakultas dengan prodi yang di-assign. Hubungi pengelola.');
            }

            $params['id_prodi_in'] = $ids;

            // Dekan TIDAK lagi wajib memilih satu jurusan.
            //
            // Dulu wajib, karena seluruh repository analitik menyaring lewat
            // `jurusan` skalar yang hanya menampung SATU nama -- tidak pernah
            // bisa menyatakan cakupan yang membawahi beberapa jurusan. Supaya
            // endpoint-endpoint itu tidak berjalan tanpa batas sama sekali,
            // jalan amannya waktu itu memaksa memilih satu.
            //
            // Sekarang seluruh repository sudah meneruskan `id_prodi_in` ke
            // `buildGlobalFilters()` (dan ke `whereIn('p.id', ...)` pada dua
            // repository yang bertanya langsung ke OLTP), jadi cakupan penuh
            // fakultas sudah bisa dinyatakan apa adanya. Tanpa pilihan jurusan,
            // yang tampil adalah agregat seluruh fakultas -- sejajar dengan
            // kajur yang melihat agregat seluruh prodi di jurusannya.
            //
            // Kalau jurusan TETAP dipilih, ia dihormati sebagai penyempit di
            // dalam cakupan, dan pilihan di luar cakupan tetap ditolak.
            $requested = $params['jurusan'] ?? null;
            if ($requested !== null && $requested !== '') {
                $scopeNames = $user->fakultas?->jurusans->pluck('name')->all() ?? [];
                if (!in_array($requested, $scopeNames, true)) {
                    abort(403, 'Anda tidak memiliki akses ke jurusan tersebut.');
                }
                $params['jurusan'] = $requested;
            } else {
                // Dibuang supaya `buildGlobalFilters()` tidak menerima nilai
                // kosong yang bisa terbaca sebagai penyaring; batasnya
                // sepenuhnya dari id_prodi_in.
                unset($params['jurusan']);
            }
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

        if ($user->isKajur() || $user->isDekan()) {
            // Drill-down masih menerima nama_prodi sebagai path/query param,
            // jadi pencocokan tetap harus lewat nama -- tapi daftar yang
            // dibandingkan sekarang berasal dari scopedProgramIds() (FK
            // eksplisit), bukan lagi cocokkan teks jurusan.
            $allowedIds = $user->scopedProgramIds();
            $program    = Program::where('name', $requestedProdi)->first();

            if ($program === null || !in_array($program->id, $allowedIds, true)) {
                $label = $user->isKajur() ? 'jurusan' : 'fakultas';
                abort(403, "Anda hanya dapat mengakses data program studi dalam {$label} yang menjadi cakupan Anda.");
            }
        }
    }
}