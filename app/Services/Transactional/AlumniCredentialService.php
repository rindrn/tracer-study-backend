<?php
// app/Services/Transactional/AlumniCredentialService.php
namespace App\Services\Transactional;

use App\Models\Transactional\AlumniProfile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * AlumniCredentialService — menerbitkan kata sandi alumni (RBAC-16, NFR-01).
 *
 * KENAPA ACAK, BUKAN DITURUNKAN DARI DATA
 *
 * Sebelumnya alumni masuk memakai NIM sebagai pengenal SEKALIGUS kata sandi,
 * dengan enam digit terakhir NIK sebagai cadangan (lihat riwayat
 * AlumniAuthService::isValidPassword). Keduanya bukan rahasia: NIM tercetak di
 * ijazah dan ada di setiap berkas impor, sementara NIK ikut tertulis di lembar
 * ekspor kementerian — artinya setiap staf yang berwenang mengekspor memegang
 * kata sandi cadangan seluruh alumni dalam cakupannya.
 *
 * Menggantinya dengan turunan lain dari data yang sama (nama, surel, tanggal
 * lahir, atau kombinasinya) tidak memperbaiki apa pun, hanya menyamarkan.
 * Kelemahannya juga borongan: begitu satu orang menyadari polanya, pola itu
 * berlaku untuk semua akun sekaligus. Karena itu kata sandi di sini dibangkitkan
 * acak per alumni, dan satu-satunya bentuk tersimpannya adalah cincangan.
 *
 * TEKS POLOSNYA HANYA HIDUP DI MEMORI selama permintaan yang membangkitkannya,
 * lalu ikut ke berkas unduhan. Tidak pernah ditulis ke basis data, tidak pernah
 * masuk log. Konsekuensinya disengaja: kata sandi TIDAK BISA diekspor
 * belakangan, karena cincangan bcrypt tidak dapat dibalik. Kalau berkasnya
 * hilang, jalan satu-satunya adalah menerbitkan ulang.
 */
class AlumniCredentialService
{
    private const CONN = 'oltp';

    public function __construct(
        private readonly AuditLogService $audit,
    ) {}

    /**
     * Abjad kata sandi. Karakter yang mudah tertukar saat dibaca atau diketik
     * ulang sengaja dibuang seluruhnya: tidak ada 0/O, 1/l/I. Alumni menerima
     * kata sandi ini lewat surel dan sebagian akan mengetiknya, bukan menyalin.
     */
    private const ALPHABET = 'abcdefghjkmnpqrstuvwxyz23456789';

    /** Panjang tiap kelompok dan jumlah kelompok: xxxx-xxxx-xxxx. */
    private const GROUP_SIZE  = 4;
    private const GROUP_COUNT = 3;

    /**
     * Batas jumlah alumni per PERMINTAAN — bukan per penerbitan.
     *
     * Bcrypt sengaja lambat, itu justru gunanya, dan pada mesin ini satu
     * cincangan memakan sekitar sepertiga detik. Biayanya lurus terhadap
     * jumlah baris: seribu alumni berarti enam menit dalam satu permintaan
     * HTTP, yang akan mati di batas waktu proksi jauh sebelum selesai.
     *
     * Membatasi jumlah alumni yang boleh diterbitkan sekali jalan BUKAN
     * jawabannya — satu angkatan di politeknik bisa mencapai ribuan orang,
     * dan menyuruh petugas memecahnya sendiri per program studi hanya
     * memindahkan pekerjaan yang seharusnya dilakukan mesin.
     *
     * Karena itu penerbitan dipecah menjadi beberapa permintaan pendek yang
     * berjalan berurutan, dikendalikan kursor `after_nim`. Tiap permintaan
     * selesai dalam hitungan puluhan detik, dan pemanggil mengulang sampai
     * `remaining` habis. Konstanta ini adalah batas ATAS yang boleh diminta
     * per permintaan; nilai bawaannya lebih kecil, lihat DEFAULT_LIMIT.
     */
    public const MAX_BATCH = 250;

    /**
     * Jumlah bawaan per permintaan bila pemanggil tidak menentukan.
     *
     * Seratus baris ≈ 36 detik pada mesin ini — di bawah batas tunggu proksi
     * yang lazim (60 detik) dengan margin yang masuk akal, sambil menjaga
     * jumlah bolak-balik tetap wajar untuk angkatan besar.
     */
    public const DEFAULT_LIMIT = 100;

    /**
     * Terbitkan kata sandi baru untuk SATU POTONG alumni.
     *
     * Pemanggil mengulang sampai `remaining` bernilai nol, membawa `last_nim`
     * potongan sebelumnya sebagai `after_nim`. Kursor memakai NIM, bukan
     * OFFSET: penerbitan mengubah baris yang sedang dilalui, sehingga OFFSET
     * akan bergeser dan melewatkan orang tanpa gejala apa pun.
     *
     * Dijalankan dalam satu transaksi PER POTONG. Potongan yang gagal
     * dibatalkan seluruhnya, sementara potongan yang sudah selesai tetap
     * berlaku — itu memang yang diinginkan, karena penerbitan yang terputus
     * dapat dilanjutkan alih-alih diulang dari nol.
     *
     * Token Sanctum yang masih aktif ikut dicabut — kata sandi lama sudah tidak
     * berlaku, jadi sesi yang lahir darinya juga tidak boleh terus hidup.
     *
     * @param  array{graduation_year?: int|null, jurusan?: string|null, program_id?: int|null, only_without_credentials?: bool, limit?: int|null, after_nim?: string|null} $filters
     * @return array{issued: Collection<int, array{nim: string, name: string, email: string, password: string}>, remaining: int, last_nim: ?string}
     */
    public function issue(array $filters): array
    {
        $limit = (int) ($filters['limit'] ?? self::DEFAULT_LIMIT);
        $limit = max(1, min($limit, self::MAX_BATCH));

        $targets = $this->findTargets($filters)->take($limit);

        if ($targets->isEmpty()) {
            return ['issued' => collect(), 'remaining' => 0, 'last_nim' => null];
        }

        $issued = DB::connection(self::CONN)->transaction(function () use ($targets) {
            $out = collect();
            $now = now();

            foreach ($targets as $row) {
                $plain = $this->generatePassword();

                $alumni = AlumniProfile::findOrFail($row->id);
                $alumni->password           = $plain;   // cast 'hashed' yang mencincang
                $alumni->password_issued_at = $now;
                $alumni->save();

                $alumni->tokens()->delete();

                $out->push([
                    'nim'      => (string) $row->nim,
                    'name'     => (string) ($row->name ?? ''),
                    'email'    => (string) ($row->email ?? ''),
                    'password' => $plain,
                ]);
            }

            return $out;
        });

        $lastNim = (string) $targets->last()->nim;

        // Penerbitan kredensial mengubah siapa yang bisa masuk sebagai
        // alumni, jadi ia dicatat. Yang disimpan CACAHNYA saja: daftar NIM
        // beserta kata sandi memang beredar di berkas unduhan petugas, tapi
        // tidak boleh ikut mengendap di tabel yang dibaca lewat API.
        $this->audit->record('alumni.credentials_issued', [
            'entity_type' => 'alumni_profiles',
            'context'     => [
                'count'           => $issued->count(),
                'graduation_year' => $filters['graduation_year'] ?? null,
                'program_id'      => $filters['program_id'] ?? null,
                'jurusan'         => $filters['jurusan'] ?? null,
            ],
        ]);

        return [
            'issued'    => $issued,
            'remaining' => $this->countRemaining($filters, $lastNim),
            'last_nim'  => $lastNim,
        ];
    }

    /**
     * Berapa alumni lagi yang tersisa setelah potongan ini.
     *
     * Dihitung ULANG setiap potong, bukan dikurangi dari cacah awal. Pada mode
     * `only_without_credentials`, baris yang baru saja diterbitkan otomatis
     * keluar dari himpunan — menghitung ulang membuat angkanya tetap benar
     * tanpa perlu memodelkan efek itu secara terpisah.
     */
    private function countRemaining(array $filters, string $afterNim): int
    {
        return $this->baseQuery($filters)->where('nim', '>', $afterNim)->count();
    }

    /**
     * Alumni yang jadi sasaran penerbitan.
     *
     * Alumni nonaktif sengaja dilewati: mereka tidak akan bisa masuk walau
     * punya kata sandi (AlumniAuthService menolaknya lebih dulu), jadi
     * menerbitkan kredensial untuk mereka hanya menambah baris di berkas
     * unduhan yang tidak berguna dan tidak seharusnya beredar.
     */
    private function findTargets(array $filters): Collection
    {
        $query = $this->baseQuery($filters);

        // Kursor. Urutan NIM menaik dipakai konsisten di sini dan di
        // countRemaining(), sehingga potongan berikutnya selalu melanjutkan
        // persis dari tempat potongan sebelumnya berhenti.
        if (!empty($filters['after_nim'])) {
            $query->where('nim', '>', (string) $filters['after_nim']);
        }

        $limit = (int) ($filters['limit'] ?? self::DEFAULT_LIMIT);
        $limit = max(1, min($limit, self::MAX_BATCH));

        return collect(
            $query->orderBy('nim')->limit($limit)->get(['id', 'nim', 'name', 'email'])
        );
    }

    /** Penyaring bersama findTargets() dan countRemaining(), tanpa kursor. */
    private function baseQuery(array $filters): \Illuminate\Database\Query\Builder
    {
        $query = DB::connection(self::CONN)->table('alumni_profiles')
            ->where('is_active', true);

        if (!empty($filters['graduation_year'])) {
            $query->where('graduation_year', (int) $filters['graduation_year']);
        }

        // Jurusan disaring lewat subkueri ke `programs`, bukan JOIN. Alasannya
        // praktis: findTargets() memilih kolom tanpa awalan nama tabel, dan
        // sebuah JOIN akan membuat `id` maupun `name` menjadi ambigu di
        // PostgreSQL. Subkueri menjaga kueri utamanya tetap satu tabel.
        //
        // Penyaring ini INDEPENDEN dari program_id. Memilih jurusan saja
        // berarti seluruh program studi di bawahnya; memilih keduanya berarti
        // irisannya — dan karena tiap program studi hanya bernaung di satu
        // jurusan, irisan itu sama dengan program studi tersebut.
        if (!empty($filters['jurusan'])) {
            $jurusan = (string) $filters['jurusan'];
            $query->whereIn('program_id', function ($sub) use ($jurusan) {
                $sub->from('programs')->select('id')->where('jurusan', $jurusan);
            });
        }

        if (!empty($filters['program_id'])) {
            $query->where('program_id', (int) $filters['program_id']);
        }

        // Penerbitan bertahap: lewati yang kredensialnya sudah pernah dikirim,
        // supaya menjalankan ulang untuk menjangkau alumni baru tidak
        // mengacak kata sandi orang yang sudah terlanjur menerima surel.
        if (!empty($filters['only_without_credentials'])) {
            $query->whereNull('password_issued_at');
        }

        return $query;
    }

    /**
     * Satu kata sandi acak, mis. "k7fm-3xqp-2rvn".
     *
     * random_int() dipakai, bukan rand()/mt_rand(): keduanya tidak layak untuk
     * keperluan keamanan karena keluarannya dapat diramalkan dari keadaan
     * dalamnya.
     */
    public function generatePassword(): string
    {
        $max = strlen(self::ALPHABET) - 1;

        $groups = [];
        for ($g = 0; $g < self::GROUP_COUNT; $g++) {
            $chunk = '';
            for ($i = 0; $i < self::GROUP_SIZE; $i++) {
                $chunk .= self::ALPHABET[random_int(0, $max)];
            }
            $groups[] = $chunk;
        }

        return implode('-', $groups);
    }
}
