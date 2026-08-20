<?php

namespace App\Http\Controllers\Api\Analytical\Concerns;

use App\Models\Transactional\Role;
use App\Models\Transactional\User;
use App\Repositories\Transactional\OrgUnitRepository;
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
 *
 * ── Dual-mode (Fase 2, DFR-13/DFR-25) ───────────────────────────────────
 *
 * scopedParams() sekarang bercabang di config('institution.structure_template'):
 *
 *   - "politeknik" (bawaan, POLBAN produksi) → scopedParamsLegacy(), badan
 *     metode ASLI yang tidak diubah sedikit pun. Nol regresi terjamin
 *     karena baris kodenya sama persis dengan sebelum Fase 2.
 *   - selain itu (universitas/institut/custom) → scopedParamsGeneric(),
 *     scoping berbasis roles.scope + org_units, tanpa nama role hardcoded.
 *     Belum ada instalasi produksi yang memakai jalur ini -- POLBAN selalu
 *     "politeknik" -- tapi jalurnya sudah nyata dan diuji terhadap pohon
 *     org_units sintetis (lihat EnforcesProdiScopeGenericTest).
 */
trait EnforcesProdiScope
{
    /**
     * Ambil semua query params + enforce scope, dipilih berdasarkan
     * template struktur institusi aktif (DFR-25).
     *
     * @return array<string, mixed>
     */
    protected function scopedParams(Request $request): array
    {
        if (config('institution.structure_template', 'politeknik') !== 'politeknik') {
            return $this->scopedParamsGeneric($request);
        }

        return $this->scopedParamsLegacy($request);
    }

    /**
     * Jalur lama (mode "politeknik") — badan metode identik dengan
     * scopedParams() sebelum Fase 2. JANGAN ubah tanpa retest regresi penuh
     * seluruh endpoint analytical, ini satu-satunya jalur yang benar-benar
     * dipakai produksi POLBAN saat ini.
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
    protected function scopedParamsLegacy(Request $request): array
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
     * Jalur baru (mode generik, DFR-12/13/14/15) — scoping berbasis level
     * unit, dibaca dari roles.scope, TANPA nama role hardcoded di mana pun
     * di metode ini.
     *
     * roles.scope (kolom yang sudah ada sejak RoleSeeder, sebelumnya tidak
     * pernah dibaca) berisi salah satu dari tiga nilai kanonik:
     *
     *   - "Seluruh Jurusan" → tidak dibatasi (setara head_tracer/tracer_team/
     *     wadir saat ini).
     *   - "Jurusan"         → dibatasi ke satu org_unit (level menengah,
     *     mis. Fakultas/Departemen) + seluruh keturunannya (DFR-14),
     *     generalisasi dari kajur.
     *   - "Program Studi"   → dibatasi dengan composite-key org_unit_id +
     *     jenjang (DFR-13), generalisasi dari kaprodi.
     *
     * Nilai lain (typo, atau scope custom yang belum didukung) GAGAL
     * TERANG-TERANGAN (403) alih-alih diam-diam meloloskan akses tanpa
     * batas — pola yang sama dengan precondition kaprodi/kajur di
     * scopedParamsLegacy().
     *
     * @return array<string, mixed>
     */
    protected function scopedParamsGeneric(Request $request): array
    {
        /** @var User $user */
        $user   = $request->user();
        $params = $request->query();

        $role = Role::where('name', $user->role)->first();

        if ($role === null || $role->scope === null || $role->scope === '') {
            abort(403, 'Role akun ini belum memiliki pemetaan level akses. Hubungi pengelola.');
        }

        return match ($role->scope) {
            'Seluruh Jurusan' => $params,
            'Jurusan'         => $this->scopedParamsGenericUnit($user, $params),
            'Program Studi'   => $this->scopedParamsGenericProdi($user, $params),
            default           => abort(403, 'Level akses role ini tidak dikenali sistem. Hubungi pengelola.'),
        };
    }

    /**
     * Generalisasi kajur: user dibatasi ke org_unit yang ditautkan padanya
     * (users.org_unit_id) beserta seluruh keturunannya (DFR-14).
     *
     * Repository analytical existing (BaseAnalyticalRepository dkk.) baru
     * menerima satu nilai `jurusan` (equality, bukan whereIn) -- diisi
     * dengan nama unit sendiri supaya topologi 2 level yang sama dengan
     * kajur (satu org_unit langsung menaungi banyak prodi) tetap tersaring
     * benar. `org_unit_id` dan `org_unit_ids` (self + descendant) ikut
     * disertakan untuk konsumen masa depan yang mendukung filter banyak
     * unit sekaligus -- itu pekerjaan Fase 3/5 (hierarchy path, whereIn),
     * SENGAJA belum dikerjakan di sini.
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function scopedParamsGenericUnit(User $user, array $params): array
    {
        if ($user->org_unit_id === null) {
            abort(403, 'Akun ini belum tertaut ke unit organisasi mana pun. Hubungi pengelola.');
        }

        /** @var OrgUnitRepository $orgUnitRepo */
        $orgUnitRepo = app(OrgUnitRepository::class);
        $unit        = $orgUnitRepo->find((int) $user->org_unit_id);

        if ($unit === null) {
            abort(403, 'Unit organisasi akun ini tidak ditemukan. Hubungi pengelola.');
        }

        $params['org_unit_id']  = (int) $unit->id;
        $params['org_unit_ids'] = $orgUnitRepo->descendantIds((int) $unit->id);
        $params['jurusan']      = $unit->name;

        return $params;
    }

    /**
     * Generalisasi kaprodi: user dibatasi dengan composite-key org_unit_id
     * (kalau program sudah ditautkan) + jenjang (DFR-13) -- nama prodi TIDAK
     * unik, jenjang WAJIB ikut dipasang persis seperti scopedParamsLegacy().
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function scopedParamsGenericProdi(User $user, array $params): array
    {
        $program = $user->program;

        if ($program === null) {
            abort(403, 'Akun ini belum tertaut ke program studi mana pun. Hubungi pengelola.');
        }

        $params['nama_prodi']  = $program->name;
        $params['jenjang']     = $program->degree;
        $params['jurusan']     = $program->jurusan;
        $params['org_unit_id'] = $program->org_unit_id;

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