<?php
// app/Services/Transactional/AdminAlumniService.php
namespace App\Services\Transactional;

use App\Exceptions\BusinessException;
use App\Models\Transactional\User;
use App\Repositories\Transactional\AlumniProfileRepository;
use App\Support\PersonalData;
use App\Support\PhoneNumber;
use App\Traits\WithCache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * AdminAlumniService — business logic CRUD alumni untuk panel admin.
 *
 * Tanggung jawab:
 * - Translate role user (head_tracer, tracer_team, wadir, kajur, kaprodi) jadi filter scope.
 * - Enforce business rule: P2MPP read-only; kaprodi hanya bisa akses prodinya.
 * - Orkestrasi ke AlumniProfileRepository.
 */
class AdminAlumniService
{
    use WithCache;

    public function __construct(
        private readonly AlumniProfileRepository $alumniRepo,
        private readonly AuditLogService         $audit,
    ) {}

    // ═══════════════════════════════════════════════════════════
    // LIST (paginate) — with has_responded flag for Kaprodi dashboard
    // ═══════════════════════════════════════════════════════════
    public function list(User $user, array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $filters = $this->applyRoleScope($user, $filters);
        if (!empty($filters['questionnaire_id'])) {
            return $this->alumniRepo->paginateRespondentsByQuestionnaire($filters, $perPage);
        }

        return $this->alumniRepo->paginateForAdminWithResponseStatus($filters, $perPage);
    }

    /**
     * Ringkasan status responden satu kuesioner (total / finished / ongoing /
     * not_started), tunduk pada scope role dan penyaring yang sama dengan
     * list(). Dipakai kartu di halaman Daftar Responden, yang harus tetap
     * benar meski daftarnya sedang menampilkan satu halaman saja.
     */
    public function respondentStats(User $user, array $filters): array
    {
        return $this->alumniRepo->countRespondentStatusByQuestionnaire(
            $this->applyRoleScope($user, $filters),
        );
    }

    // ═══════════════════════════════════════════════════════════
    // STATS — total / sudah mengisi / belum mengisi / response rate
    // ═══════════════════════════════════════════════════════════
    /**
     * Return statistik alumni untuk dashboard (kaprodi = prodinya saja, admin = semua).
     *
     * Kuesioner yang dipakai sebagai baseline "sudah mengisi" adalah
     * kuesioner global (program_id NULL, status published) — lihat
     * AlumniProfileRepository::countStatsByProgram.
     */
    public function getStats(User $user, ?int $graduationYear = null, ?int $requestedProgramId = null): array
    {
        $programId   = $user->isKaprodi() ? $user->program_id : null;
        $programIdIn = ($user->isKajur() || $user->isDekan()) ? $user->scopedProgramIds() : null;

        // Penyempitan ke satu prodi atas permintaan pemanggil, dipakai kartu
        // ringkasan setelah sebuah kartu prodi dipilih.
        //
        // Diperiksa terhadap cakupan peran DULU, bukan diteruskan begitu saja:
        // countStatsByProgram() memprioritaskan $programId di atas
        // $programIdIn, jadi meneruskannya mentah-mentah membuat Kajur atau
        // Dekan bisa membaca angka prodi di luar jangkauannya hanya dengan
        // menyunting query string. Kaprodi tidak pernah bisa menggesernya:
        // prodinya sudah dipatok di atas.
        if ($requestedProgramId !== null && $programId === null) {
            // Dicek hanya kalau daftarnya benar-benar berisi. Kajur atau Dekan
            // yang belum punya jurusan/fakultas menghasilkan daftar kosong,
            // dan seluruh lapisan repositori memperlakukan daftar kosong
            // sebagai "tanpa penyaring" -- lihat countStatsByProgram(). Kalau
            // di sini diperlakukan sebagai "tidak boleh apa pun", kartu prodi
            // tampil berisi tetapi kartu ringkasannya nol begitu diklik.
            if (!empty($programIdIn) && !in_array($requestedProgramId, $programIdIn, strict: true)) {
                return $this->emptyStats($programIdIn);
            }

            $programId   = $requestedProgramId;
            $programIdIn = null;
        }

        $stats = $this->alumniRepo->countStatsByProgram($programId, $graduationYear, $programIdIn);

        $stats['response_rate'] = $stats['total'] > 0
            ? round($stats['answered'] / $stats['total'] * 100, 1)
            : 0.0;

        $stats['graduation_years'] = $this->alumniRepo->getAvailableGraduationYears($programId, $programIdIn);

        return $stats;
    }

    /**
     * Angka nol untuk prodi di luar jangkauan pemanggil.
     *
     * Daftar tahun lulusan tetap diisi sesuai cakupan perannya supaya
     * dropdown angkatan di halaman tidak ikut kosong hanya karena satu
     * prodi yang tidak boleh dibaca sempat diminta.
     *
     * @param array<int>|null $programIdIn
     */
    private function emptyStats(?array $programIdIn): array
    {
        return [
            'total'            => 0,
            'finished'         => 0,
            'ongoing'          => 0,
            'not_started'      => 0,
            'answered'         => 0,
            'unanswered'       => 0,
            'response_rate'    => 0.0,
            'graduation_years' => $this->alumniRepo->getAvailableGraduationYears(null, $programIdIn),
        ];
    }

    /**
     * Stats alumni per prodi, untuk layar kartu prodi di halaman Data Alumni.
     *
     * Cakupannya mengikuti getStats() persis: Kaprodi hanya prodinya sendiri,
     * Kajur dan Dekan sebatas prodi dalam jangkauannya, peran lain seluruhnya.
     * Menuliskannya ulang di sini, bukan memanggil getStats(), karena yang
     * berbeda hanya bentuk keluarannya -- aturan cakupannya harus tetap satu.
     */
    public function getStatsByProgram(User $user, ?int $graduationYear = null): array
    {
        $programId   = $user->isKaprodi() ? $user->program_id : null;
        $programIdIn = ($user->isKajur() || $user->isDekan()) ? $user->scopedProgramIds() : null;

        return $this->alumniRepo->countStatsGroupedByProgram($programId, $graduationYear, $programIdIn);
    }

    // ═══════════════════════════════════════════════════════════
    // IMPORT TEMPLATE — download Excel kosongan berisi header
    // ═══════════════════════════════════════════════════════════
    /**
     * Bangun export object untuk template Excel kosong (header saja).
     *
     * Dipakai oleh admin / kepala tracer untuk dapat "blueprint" kolom yang
     * harus diisi sebelum meng-import data alumni. Fitur upload/parse akan
     * diimplementasikan di round berikutnya.
     */
    public function buildImportTemplate(): \App\Exports\AlumniImportTemplateExport
    {
        return new \App\Exports\AlumniImportTemplateExport();
    }

    // ═══════════════════════════════════════════════════════════
    // SHOW
    // ═══════════════════════════════════════════════════════════
    public function show(User $user, int $id): object
    {
        $alumni = $this->alumniRepo->findByIdWithProgram($id);

        if (!$alumni) {
            throw new BusinessException('Alumni tidak ditemukan.', 404);
        }

        $this->assertKaprodiCanAccessProgram($user, $alumni->program_id);

        return $alumni;
    }

    // ═══════════════════════════════════════════════════════════
    // CREATE
    // ═══════════════════════════════════════════════════════════
    public function create(User $user, array $data): int
    {
        $this->assertCanWrite($user);

        // Kaprodi: paksa program_id ke prodinya
        if ($user->isKaprodi()) {
            $data['program_id'] = $user->program_id;
        }

        $data = $this->normalizePhone($data);
        $data = $this->hashPassword($data);

        $id = $this->alumniRepo->create($data);

        $this->audit->record('alumni.created', [
            'entity_type'       => 'alumni_profiles',
            'entity_id'         => $id,
            'subject_alumni_id' => $id,
            'context'           => ['nim' => $data['nim'] ?? null],
        ]);

        $this->forgetDashboardCache();

        return $id;
    }

    // ═══════════════════════════════════════════════════════════
    // UPDATE
    // ═══════════════════════════════════════════════════════════
    public function update(User $user, int $id, array $data): void
    {
        $this->assertCanWrite($user);

        $alumni = $this->alumniRepo->findByIdWithProgram($id);
        if (!$alumni) {
            throw new BusinessException('Alumni tidak ditemukan.', 404);
        }

        $this->assertKaprodiCanAccessProgram($user, $alumni->program_id, forWrite: true);

        // Kaprodi tidak boleh memindahkan alumni ke prodi lain
        if ($user->isKaprodi() && isset($data['program_id'])) {
            unset($data['program_id']);
        }

        $data = $this->normalizePhone($data);
        $data = $this->hashPassword($data);

        $this->alumniRepo->updateById($id, $data);

        // Yang dicatat NAMA KOLOM yang berubah, bukan nilainya. Menyimpan
        // nilai lama dan baru akan menjadikan tabel audit salinan kedua data
        // pribadi — salinan yang justru tidak terenkripsi, sehingga
        // mencatatnya melemahkan perlindungan yang sedang dibangun.
        $this->audit->record('alumni.updated', [
            'entity_type'       => 'alumni_profiles',
            'entity_id'         => $id,
            'subject_alumni_id' => $id,
            'context'           => ['fields' => array_keys($data)],
        ]);

        $this->forgetDashboardCache();
    }

    /**
     * Bakukan nomor telepon sebelum disimpan (DATA-09).
     *
     * Jalur impor massal dan jalur pengisian oleh alumni sudah lama
     * melakukannya, sedangkan jalur ini tidak — sehingga staf yang mengetik
     * `08123456789` atau `0812-3456-789` di borang Tambah Alumni menyimpannya
     * apa adanya, dan aturan validasinya pun tidak menahannya.
     *
     * Kuncinya diperiksa dengan array_key_exists, bukan isset: pada
     * pembaruan, `phone` bernilai null berarti staf sengaja mengosongkannya,
     * dan itu harus tetap tersimpan sebagai kosong. Memakai isset akan
     * membuat pengosongan diam-diam terabaikan.
     */
    private function normalizePhone(array $data): array
    {
        if (array_key_exists('phone', $data)) {
            $data['phone'] = PhoneNumber::normalize($data['phone']);
        }

        return $data;
    }

    /**
     * Cincang kata sandi yang ditetapkan staf lewat borang, sebelum disimpan.
     *
     * Repository di jalur ini menulis dengan query builder, bukan model, jadi
     * cast 'hashed' tidak ikut berlaku — tanpa langkah ini kata sandinya
     * masuk ke kolom apa adanya dan Hash::check() saat masuk selalu gagal.
     *
     * Kolom kosong berarti "jangan sentuh", bukan "kosongkan": borang Edit
     * tidak pernah bisa memperlihatkan kata sandi yang berlaku sekarang
     * (yang tersimpan hanya cincangannya), sehingga menyimpan borang tanpa
     * mengetik apa pun tidak boleh mencabut akses alumni.
     *
     * Biaya bcrypt-nya disamakan dengan penerbitan massal
     * (AlumniCredentialService::BCRYPT_ROUNDS) supaya kedua jalur menghasilkan
     * cincangan sejenis; biaya ikut tertulis di dalam cincangan, jadi
     * Hash::check() membacanya sendiri.
     *
     * `password_issued_at` ikut disetel karena kolom itulah penanda "sudah
     * punya kredensial masuk" yang dibaca penerbitan massal — tanpa itu,
     * alumni yang kata sandinya baru saja ditetapkan manual akan ikut
     * terbawa lagi pada penerbitan berikutnya dan kata sandinya tertimpa.
     */
    private function hashPassword(array $data): array
    {
        if (!array_key_exists('password', $data)) {
            return $data;
        }

        if ($data['password'] === null || $data['password'] === '') {
            unset($data['password']);

            return $data;
        }

        $data['password']           = Hash::make($data['password'], ['rounds' => 10]);
        $data['password_issued_at'] = now();

        return $data;
    }

    // ═══════════════════════════════════════════════════════════
    // DELETE
    // ═══════════════════════════════════════════════════════════
    public function delete(User $user, int $id): void
    {
        $this->assertCanWrite($user);

        $alumni = $this->alumniRepo->findByIdWithProgram($id);
        if (!$alumni) {
            throw new BusinessException('Alumni tidak ditemukan.', 404);
        }

        $this->assertKaprodiCanAccessProgram($user, $alumni->program_id, forWrite: true);

        $this->alumniRepo->deleteById($id);

        // NIM disamarkan: barisnya sudah hilang, jadi log inilah satu-satunya
        // jejak yang tersisa, dan jejak yang memuat pengenal utuh dari data
        // yang baru saja dihapus mengalahkan tujuan penghapusannya.
        $this->audit->record('alumni.deleted', [
            'entity_type'       => 'alumni_profiles',
            'entity_id'         => $id,
            'subject_alumni_id' => $id,
            'context'           => ['nim' => PersonalData::mask($alumni->nim ?? null)],
        ]);

        $this->forgetDashboardCache();
    }

    // ═══════════════════════════════════════════════════════════
    // IMPORT FROM EXCEL
    // ═══════════════════════════════════════════════════════════
    /**
     * Import alumni dari file Excel yang di-upload.
     * Return: ['imported' => int, 'errors' => string[]]
     */
    public function importFromExcel(\Illuminate\Http\UploadedFile $file): array
    {
        $import = new \App\Imports\AlumniImport($this->alumniRepo);
        \Maatwebsite\Excel\Facades\Excel::import($import, $file);

        $this->audit->record('alumni.imported', [
            'entity_type' => 'alumni_profiles',
            'context'     => [
                'imported' => $import->getImportedCount(),
                'errors'   => count($import->getErrors()),
            ],
        ]);

        if ($import->getImportedCount() > 0) {
            $this->forgetDashboardCache();
        }

        return [
            'imported' => $import->getImportedCount(),
            'errors'   => $import->getErrors(),
        ];
    }

    // ═══════════════════════════════════════════════════════════
    // Helpers (private)
    // ═══════════════════════════════════════════════════════════

    /**
     * Buang cache kartu & grafik dasbor sesudah jumlah alumni berubah.
     *
     * Kartu "Total Alumni" dan penyebut response rate dihitung langsung dari
     * alumni_profiles (mode Realtime), tapi hasilnya disimpan satu jam penuh
     * di bawah tag 'analytics-dashboard'. Sebelum ini hanya jalannya ETL dan
     * penyuntingan pemetaan semantik yang membuang tag itu, sehingga impor
     * atau penghapusan alumni tidak terlihat di Overview sampai TTL-nya habis
     * — petugas mengimpor 1.800 baris lalu melihat angka lama dan mengira
     * impornya gagal.
     */
    private function forgetDashboardCache(): void
    {
        $this->forgetTag('analytics-dashboard');
    }

    /**
     * Tambahkan filter scope berdasarkan role user.
     *
     * Kajur & Dekan dibatasi lewat `program_id_in` (daftar
     * program_id dari User::scopedProgramIds(), berdasar keanggotaan FK
     * eksplisit di jurusan_program_scopes / fakultas_jurusan_scopes) --
     * bukan lagi cocokkan teks `jurusan`. Efek sampingnya sekaligus jadi
     * tujuan Fase 5: kedua role langsung melihat data gabungan seluruh
     * prodi dalam cakupannya, tanpa wajib memilih satu jurusan dulu.
     */
    private function applyRoleScope(User $user, array $filters): array
    {
        if ($user->isKaprodi() && $user->program_id) {
            $filters['program_id'] = $user->program_id;
        } elseif ($user->isKajur() || $user->isDekan()) {
            $ids = $user->scopedProgramIds();

            if (empty($ids)) {
                $label = $user->isKajur() ? 'jurusan' : 'fakultas';
                throw new BusinessException("Akun ini belum memiliki {$label} dengan prodi yang di-assign. Hubungi pengelola.", 403);
            }

            $filters['program_id_in'] = $ids;
        }
        return $filters;
    }

    /** Viewer roles (wadir, kajur, kaprodi, dekan) — block semua operasi tulis. */
    private function assertCanWrite(User $user): void
    {
        if ($user->isWadir() || $user->isKajur() || $user->isKaprodi() || $user->isDekan()) {
            throw new BusinessException('Role Anda tidak diizinkan mengubah data alumni.', 403);
        }
    }

    /** Kaprodi tidak boleh akses alumni di luar prodinya. */
    private function assertKaprodiCanAccessProgram(User $user, ?int $programId, bool $forWrite = false): void
    {
        if ($user->isKaprodi() && $programId !== $user->program_id) {
            $verb = $forWrite ? 'mengubah' : 'mengakses';
            throw new BusinessException(
                "Anda tidak memiliki hak akses untuk {$verb} alumni prodi lain.",
                403,
            );
        }
    }
}
