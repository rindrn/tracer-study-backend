<?php
// app/Services/Transactional/AlumniCredentialService.php
namespace App\Services\Transactional;

use App\Exceptions\BusinessException;
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
     * Batas jumlah alumni per penerbitan.
     *
     * Bcrypt sengaja lambat — itu justru gunanya — dan pada mesin ini satu
     * cincangan memakan sekitar sepertiga detik. Biayanya lurus terhadap
     * jumlah baris, sehingga satu angkatan penuh dengan mudah melewati batas
     * waktu eksekusi PHP maupun batas tunggu proksi di depannya, dan yang
     * terjadi bukan galat yang rapi melainkan permintaan yang mati di tengah.
     *
     * Membatasi di sini membuat kegagalannya jelas dan bisa ditindaklanjuti:
     * pesan galatnya menyebut berapa yang cocok dan menyarankan mempersempit
     * penyaring. Itu juga sejalan dengan cara kerjanya di lapangan — kiriman
     * surel memang dilakukan bertahap per angkatan atau per program studi,
     * bukan sekali tembak untuk semua.
     *
     * Naikkan hanya bersamaan dengan menaikkan batas waktu di PHP DAN di
     * proksi; kalau hanya salah satu, gejalanya sulit dilacak.
     */
    public const MAX_BATCH = 250;

    /**
     * Terbitkan kata sandi baru untuk sekumpulan alumni.
     *
     * Dijalankan dalam satu transaksi: kalau ada satu baris yang gagal
     * diperbarui, tidak boleh ada alumni yang kata sandinya sudah berganti
     * sementara berkas unduhannya tidak pernah sampai ke tangan Tim Tracer.
     *
     * Token Sanctum yang masih aktif ikut dicabut — kata sandi lama sudah tidak
     * berlaku, jadi sesi yang lahir darinya juga tidak boleh terus hidup.
     *
     * @param  array{graduation_year?: int|null, program_id?: int|null, only_without_credentials?: bool} $filters
     * @return Collection<int, array{nim: string, name: string, email: string, password: string}>
     */
    public function issue(array $filters): Collection
    {
        $targets = $this->findTargets($filters);

        if ($targets->isEmpty()) {
            return collect();
        }

        if ($targets->count() > self::MAX_BATCH) {
            throw new BusinessException(sprintf(
                'Penyaring ini menjangkau %d alumni, melebihi batas %d per penerbitan. '
                . 'Persempit dengan memilih tahun lulus atau program studi, lalu terbitkan bertahap. '
                . 'Batas ini ada karena pencincangan kata sandi sengaja lambat, sehingga kelompok '
                . 'yang terlalu besar akan mati di tengah jalan.',
                $targets->count(),
                self::MAX_BATCH,
            ), 422);
        }

        return DB::connection(self::CONN)->transaction(function () use ($targets) {
            $issued = collect();
            $now    = now();

            foreach ($targets as $row) {
                $plain = $this->generatePassword();

                $alumni = AlumniProfile::findOrFail($row->id);
                $alumni->password           = $plain;   // cast 'hashed' yang mencincang
                $alumni->password_issued_at = $now;
                $alumni->save();

                $alumni->tokens()->delete();

                $issued->push([
                    'nim'      => (string) $row->nim,
                    'name'     => (string) ($row->name ?? ''),
                    'email'    => (string) ($row->email ?? ''),
                    'password' => $plain,
                ]);
            }

            return $issued;
        });
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
        $query = DB::connection(self::CONN)->table('alumni_profiles')
            ->where('is_active', true);

        if (!empty($filters['graduation_year'])) {
            $query->where('graduation_year', (int) $filters['graduation_year']);
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

        return collect($query->orderBy('nim')->get(['id', 'nim', 'name', 'email']));
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
