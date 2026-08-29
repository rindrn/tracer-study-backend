<?php
// app/Repositories/Transactional/AlumniProfileRepository.php
namespace App\Repositories\Transactional;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Query\Builder;
use App\Support\PersonalData;

/**
 * AlumniProfileRepository — query persistence untuk aggregate root AlumniProfile.
 *
 * Aggregate ini meliputi tabel utama `alumni_profiles`. Akses ke tabel lain
 * (employment_records, education_records, responses) di-delegasikan ke
 * repository masing-masing. Repo ini hanya bertugas persistence, tidak tahu
 * soal business rules atau role-based filtering.
 *
 * BATAS ENKRIPSI DATA PRIBADI
 * ---------------------------
 * NIK dan NPWP tersimpan terenkripsi (lihat App\Support\PersonalData beserta
 * alasan lengkapnya). Repository inilah batasnya: setiap method tulis
 * memanggil PersonalData::protectAttributes(), setiap method baca yang
 * mengembalikan baris memanggil PersonalData::revealRow(). Di luar berkas ini
 * kedua kolom itu selalu sudah polos, sehingga tidak ada service maupun
 * controller yang perlu tahu soal enkripsi sama sekali.
 *
 * SATU LUBANG YANG DISENGAJA: getForReportQuery() mengembalikan query builder
 * yang belum dijalankan, sehingga tidak ada baris untuk didekripsi di sini.
 * Pemanggilnya — MinistrySheetExport — yang wajib memanggil
 * PersonalData::reveal() sendiri. Kalau menambah pemanggil baru untuk method
 * itu, ingat kewajiban yang sama.
 */
class AlumniProfileRepository
{
    private const CONN = 'oltp';

    // ═══════════════════════════════════════════════════════════
    // READ
    // ═══════════════════════════════════════════════════════════

    /**
     * Cari alumni berdasarkan NIM atau email (untuk keperluan login alumni),
     * sekaligus join ke programs supaya FE dapat info prodi.
     */
    public function findByNimOrEmailWithProgram(string $identifier): ?object
    {
        $row = DB::connection(self::CONN)
            ->table('alumni_profiles')
            ->leftJoin('programs', 'alumni_profiles.program_id', '=', 'programs.id')
            ->select(
                'alumni_profiles.id',
                'alumni_profiles.nim',
                'alumni_profiles.name',
                'alumni_profiles.email',
                'alumni_profiles.phone',
                'alumni_profiles.program_id',
                'alumni_profiles.entry_year',
                'alumni_profiles.graduation_year',
                'alumni_profiles.is_active',
                'alumni_profiles.nik',
                'alumni_profiles.npwp',
                // Cincangan kata sandi, dipakai AlumniAuthService::login().
                // Tanpa kolom ini Hash::check() selalu membandingkan dengan
                // null dan SETIAP alumni gagal masuk — tanpa gejala lain
                // selain "kata sandi salah" yang menyesatkan.
                'alumni_profiles.password',
                // Tanpa tiga kolom ini, ConsentService::isCurrent()/state()
                // membaca null dari objek ini walau nilainya benar terisi di
                // DB — respons login SELALU melaporkan consent.granted:false
                // (bug #18, HASIL_TESTING_2026-08-23.md §T.7).
                'alumni_profiles.consent_at',
                'alumni_profiles.consent_version',
                'alumni_profiles.consent_ip',
                'programs.name as program_name',
                'programs.degree as program_degree',
                'programs.code as program_code',
                'programs.degree as program_degree',
            )
            ->where(function ($q) use ($identifier) {
                $q->where('alumni_profiles.nim', $identifier)
                  ->orWhere('alumni_profiles.email', $identifier);
            })
            ->first();

        return PersonalData::revealRow($row);
    }

    /** Alumni by NIM — dipakai saat submit kuesioner (upsert check). */
    public function findByNim(string $nim): ?object
    {
        $row = DB::connection(self::CONN)
            ->table('alumni_profiles')
            ->where('nim', $nim)
            ->first();

        return PersonalData::revealRow($row);
    }

    /** Detail alumni + info prodi untuk halaman admin. */
    public function findByIdWithProgram(int $id): ?object
    {
        $row = DB::connection(self::CONN)
            ->table('alumni_profiles')
            ->leftJoin('programs', 'alumni_profiles.program_id', '=', 'programs.id')
            ->select(
                'alumni_profiles.*',
                'programs.name as program_name',
                'programs.degree as program_degree',
                'programs.jurusan as jurusan_name',
            )
            ->where('alumni_profiles.id', $id)
            ->first();

        return PersonalData::revealRow($row);
    }

    /**
     * Paginasi alumni + data employment/education untuk panel admin.
     *
     * @param array{program_id?: int, search?: string, questionnaire_id?: int} $filters
     */
    public function paginateForAdmin(array $filters, int $perPage): LengthAwarePaginator
    {
        $query = DB::connection(self::CONN)->table('alumni_profiles')
            ->leftJoin('programs', 'alumni_profiles.program_id', '=', 'programs.id')
            ->leftJoin('employment_records', 'alumni_profiles.id', '=', 'employment_records.alumni_id')
            ->leftJoin('education_records', 'alumni_profiles.id', '=', 'education_records.alumni_id')
            ->select(
                'alumni_profiles.*',
                'programs.name as program_name',
                'programs.degree as program_degree',
                'programs.jurusan as jurusan_name',
                'employment_records.employment_status',
                'employment_records.waiting_months',
                'employment_records.salary_current',
                'employment_records.company_name',
                'employment_records.job_title',
                'employment_records.work_city',
                'education_records.is_further_study',
                'education_records.institution_name',
            );

        if (!empty($filters['program_id'])) {
            $query->where('alumni_profiles.program_id', $filters['program_id']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('alumni_profiles.nim', 'like', "%{$search}%")
                  ->orWhere('alumni_profiles.name', 'like', "%{$search}%");
            });
        }

        $page = $query->paginate($perPage);

        // Isi halaman didekripsi di tempat; objek paginator-nya sendiri yang
        // dikembalikan supaya metadata halaman (total, tautan) tidak hilang.
        PersonalData::revealRows($page->items());

        return $page;
    }

    /**
     * Sama seperti paginateForAdmin tapi ditambah kolom `response_status`:
     *   - 'finished'    → punya response berstatus submitted/verified
     *   - 'ongoing'     → punya response berstatus started, belum ada yang selesai
     *   - 'not_started' → tidak punya response sama sekali
     *
     * Dipakai di halaman Data Alumni Prodi (kaprodi).
     *
     * Kosakata ketiga nilai itu SENGAJA sama dengan yang dipakai
     * paginateRespondentsByQuestionnaire() dan dibaca dua halaman frontend.
     * Sebelumnya method ini mengirim 'finish' dan 'belum_mengisi', sementara
     * frontend memeriksa 'finished' dan 'not_started' — dua dari tiga nilai
     * tidak pernah cocok, sehingga alumni yang sudah mengisi pun tampil
     * "Not Started". Kartu ringkasan di halaman yang sama benar karena
     * sumbernya endpoint lain, jadi angkanya saling bertentangan di satu layar.
     *
     * Status dihitung lewat subquery berkorelasi, BUKAN leftJoin. Dengan
     * leftJoin, satu alumni yang mengisi lebih dari satu kuesioner global
     * menghasilkan lebih dari satu baris: namanya muncul berulang di tabel,
     * hitungan "Daftar Alumni (n)" menggelembung, dan paginate() memotong
     * berdasarkan jumlah baris hasil join alih-alih jumlah alumni. Saat ini
     * tiap kuesioner global menyasar tahun lulus yang berbeda sehingga
     * belum ada yang ganda, tapi itu kebetulan bentuk data — bukan jaminan
     * dari query-nya.
     *
     * @param array{program_id?: int, search?: string, jurusan?: string, graduation_year?: int} $filters
     */
    public function paginateForAdminWithResponseStatus(array $filters, int $perPage): LengthAwarePaginator
    {
        $conn = DB::connection(self::CONN);

        // Subquery: ambil id kuesioner global published
        $globalQnrIds = $conn->table('questionnaires')
            ->whereNull('program_id')
            ->where('status', 'published')
            ->pluck('id')
            ->all();

        // Placeholder yang aman untuk daftar kosong — tidak ada id 0.
        $globalQnrIds = $globalQnrIds ?: [0];
        $inList       = implode(',', array_fill(0, count($globalQnrIds), '?'));

        // Selesai menang atas sedang mengisi: alumni yang sudah mengirim satu
        // kuesioner tapi masih mendraf kuesioner global lain tetap dihitung
        // sekali sebagai "finished", tidak bocor ke dua status. Urutan ini
        // sama dengan yang dipakai ResponseRateRepository.
        $statusSql = "CASE
            WHEN EXISTS (
                SELECT 1 FROM responses rf
                WHERE rf.alumni_id = alumni_profiles.id
                  AND rf.questionnaire_id IN ({$inList})
                  AND rf.status IN ('submitted','verified')
            ) THEN 'finished'
            WHEN EXISTS (
                SELECT 1 FROM responses ro
                WHERE ro.alumni_id = alumni_profiles.id
                  AND ro.questionnaire_id IN ({$inList})
                  AND ro.status = 'started'
            ) THEN 'ongoing'
            ELSE 'not_started'
        END as response_status";

        // Kolom disebut satu per satu, BUKAN `alumni_profiles.*`.
        //
        // Tanda bintang ikut membawa kolom `password` ke antarmuka. Selama
        // kolom itu masih kosong di seluruh baris, kebocorannya tidak
        // kelihatan; sejak kredensial alumni benar-benar diterbitkan, setiap
        // pemuatan daftar alumni mengirimkan cincangan bcrypt milik ratusan
        // orang ke peramban. Cincangan memang tidak dapat dibalik, tetapi
        // tetap bahan mentah untuk penebakan luring dan tidak ada satu pun
        // bagian antarmuka yang membutuhkannya.
        //
        // `password_issued_at` sengaja tetap disertakan: nilainya bukan
        // rahasia dan berguna untuk menandai alumni yang kredensialnya belum
        // pernah diterbitkan.
        $query = $conn->table('alumni_profiles')
            ->leftJoin('programs', 'alumni_profiles.program_id', '=', 'programs.id')
            ->select(
                'alumni_profiles.id',
                'alumni_profiles.nim',
                'alumni_profiles.name',
                'alumni_profiles.email',
                'alumni_profiles.phone',
                'alumni_profiles.program_id',
                'alumni_profiles.entry_year',
                'alumni_profiles.graduation_year',
                'alumni_profiles.gpa',
                'alumni_profiles.is_active',
                'alumni_profiles.created_at',
                'alumni_profiles.updated_at',
                'alumni_profiles.nik',
                'alumni_profiles.npwp',
                'alumni_profiles.kode_pt',
                'alumni_profiles.password_issued_at',
                'programs.name as program_name',
                'programs.degree as program_degree',
                'programs.jurusan as jurusan_name',
                // Dibutuhkan ekspor alumni: templat impor memakai Kode Prodi,
                // bukan nama program studi, sehingga tanpa kolom ini berkas
                // ekspor tidak akan pernah bisa diunggah kembali.
                'programs.code as program_code',
                // Kode prodi versi PDDIKTI (5 digit angka). Dipakai HANYA oleh
                // ekspor format=code yang berkasnya diunggah ke portal
                // Kementerian -- portal tidak mengenal singkatan internal
                // seperti "TKG". Mode label tetap memakai programs.code supaya
                // berkasnya bisa diimpor balik lewat templat impor alumni.
                'programs.dikti_code as program_dikti_code',
            )
            ->selectRaw($statusSql, array_merge($globalQnrIds, $globalQnrIds));

        if (!empty($filters['program_id'])) {
            $query->where('alumni_profiles.program_id', $filters['program_id']);
        }

        if (!empty($filters['program_id_in'])) {
            $query->whereIn('alumni_profiles.program_id', $filters['program_id_in']);
        } elseif (!empty($filters['jurusan'])) {
            $query->where('programs.jurusan', $filters['jurusan']);
        }

        if (!empty($filters['graduation_year'])) {
            $query->where('alumni_profiles.graduation_year', $filters['graduation_year']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('alumni_profiles.nim', 'like', "%{$search}%")
                  ->orWhere('alumni_profiles.name', 'like', "%{$search}%");
            });
        }

        $page = $query->orderByDesc('alumni_profiles.graduation_year')
            ->orderByDesc('alumni_profiles.id')
            ->paginate($perPage);

        PersonalData::revealRows($page->items());

        return $page;
    }

    /**
     * List semua alumni yang ditargetkan oleh kuesioner tertentu,
     * termasuk yang belum mengisi (LEFT JOIN responses).
     *
     * Status ada 3, sama dengan paginateForAdminWithResponseStatus():
     *   - 'finished'    → status IN ('submitted','verified')
     *   - 'ongoing'     → status = 'started' (draf berjalan)
     *   - 'not_started' → tidak ada baris response sama sekali
     *
     * Sebelumnya hanya 2: cabang ELSE menyapu 'started' DAN "tidak ada baris"
     * menjadi 'ongoing' yang sama, sehingga alumni yang belum menyentuh
     * kuesioner tampil "Ongoing". Frontend halaman ini sudah menyediakan tiga
     * kartu, tiga pilihan filter, dan tiga lencana — kartu "Not Started" dan
     * opsi filternya karena itu selalu nol, apa pun keadaan datanya.
     *
     * Duplikasi baris tidak jadi soal di sini, berbeda dengan
     * paginateForAdminWithResponseStatus(): join-nya dipatok ke SATU
     * questionnaire_id, dan pasangan (questionnaire_id, alumni_id) sudah
     * dijamin unik oleh indeks. Jadi leftJoin tetap dipakai — kolom
     * response_id dan submitted_at memang perlu ditampilkan.
     *
     * @param array{program_id?: int, search?: string, questionnaire_id: int,
     *              jurusan?: string, graduation_year?: int,
     *              response_status?: string} $filters
     */
    public function paginateRespondentsByQuestionnaire(array $filters, int $perPage): LengthAwarePaginator
    {
        $query = $this->respondentsBaseQuery($filters)
            ->select(
                'alumni_profiles.id',
                'alumni_profiles.nim',
                'alumni_profiles.name',
                'alumni_profiles.email',
                'alumni_profiles.program_id',
                'alumni_profiles.graduation_year',
                'programs.name as program_name',
                'programs.degree as program_degree',
                'programs.jurusan as jurusan_name',
                'responses.id as response_id',
                DB::raw(self::RESPONSE_STATUS_SQL . ' as response_status'),
                'responses.submitted_at as response_submitted_at',
                'responses.created_at as response_created_at',
                'responses.updated_at as response_updated_at',
            );

        return $query->orderByRaw("CASE WHEN responses.status IN ('submitted','verified') THEN 2 ELSE 1 END")
            ->orderBy('alumni_profiles.name')
            ->paginate($perPage);
    }

    /**
     * Jumlah responden per status untuk SATU kuesioner, tunduk pada penyaring
     * yang sama dengan paginateRespondentsByQuestionnaire().
     *
     * Dipakai kartu ringkasan di halaman Daftar Responden. Kartu itu tidak
     * boleh dihitung dari baris yang kebetulan termuat di halaman aktif:
     * begitu daftarnya dipaginasi, angkanya hanya akan mencerminkan satu
     * halaman dan berubah-ubah tiap kali pengguna berpindah halaman.
     *
     * Penyaring response_status sengaja TIDAK ikut diterapkan — kartunya
     * memang harus tetap menampilkan ketiga angka meski daftarnya sedang
     * disaring ke salah satu status.
     *
     * @param array{program_id?: int, search?: string, questionnaire_id: int,
     *              jurusan?: string, graduation_year?: int} $filters
     *
     * @return array{total: int, finished: int, ongoing: int, not_started: int}
     */
    public function countRespondentStatusByQuestionnaire(array $filters): array
    {
        unset($filters['response_status']);

        $rows = $this->respondentsBaseQuery($filters)
            ->select(DB::raw(self::RESPONSE_STATUS_SQL . ' as response_status'), DB::raw('count(*) as jumlah'))
            ->groupBy(DB::raw(self::RESPONSE_STATUS_SQL))
            ->pluck('jumlah', 'response_status');

        $finished   = (int) ($rows['finished'] ?? 0);
        $ongoing    = (int) ($rows['ongoing'] ?? 0);
        $notStarted = (int) ($rows['not_started'] ?? 0);

        return [
            'total'       => $finished + $ongoing + $notStarted,
            'finished'    => $finished,
            'ongoing'     => $ongoing,
            'not_started' => $notStarted,
        ];
    }

    /**
     * Turunan status responden dari baris responses hasil LEFT JOIN.
     * Satu definisi dipakai bersama oleh daftar dan kartu ringkasan supaya
     * keduanya tidak bisa berbeda pendapat soal arti "sedang mengisi".
     */
    private const RESPONSE_STATUS_SQL = "CASE
        WHEN responses.status IN ('submitted','verified') THEN 'finished'
        WHEN responses.status = 'started' THEN 'ongoing'
        ELSE 'not_started'
    END";

    /**
     * Rangka query responden satu kuesioner: scope target kuesioner plus
     * seluruh penyaring dari request. Tanpa select dan tanpa urutan — pemanggil
     * yang menentukan, karena daftar butuh kolom lengkap sedangkan kartu
     * ringkasan hanya butuh hitungan.
     */
    private function respondentsBaseQuery(array $filters): Builder
    {
        $conn = DB::connection(self::CONN);
        $questionnaireId = $filters['questionnaire_id'];

        // Ambil info kuesioner untuk filter target
        $questionnaire = $conn->table('questionnaires')
            ->where('id', $questionnaireId)
            ->select('program_id', 'target_graduation_years')
            ->first();

        $query = $conn->table('alumni_profiles')
            ->leftJoin('programs', 'alumni_profiles.program_id', '=', 'programs.id')
            ->leftJoin('responses', function ($join) use ($questionnaireId) {
                $join->on('responses.alumni_id', '=', 'alumni_profiles.id')
                     ->where('responses.questionnaire_id', '=', $questionnaireId);
            });

        // Scope berdasarkan target kuesioner
        if ($questionnaire && $questionnaire->program_id) {
            $query->where('alumni_profiles.program_id', $questionnaire->program_id);
        }

        if ($questionnaire && $questionnaire->target_graduation_years) {
            $years = json_decode($questionnaire->target_graduation_years, true);
            if (!empty($years)) {
                $query->whereIn('alumni_profiles.graduation_year', $years);
            }
        }

        // Filter tambahan dari request
        if (!empty($filters['program_id'])) {
            $query->where('alumni_profiles.program_id', $filters['program_id']);
        }

        if (!empty($filters['program_id_in'])) {
            $query->whereIn('alumni_profiles.program_id', $filters['program_id_in']);
        } elseif (!empty($filters['jurusan'])) {
            $query->where('programs.jurusan', $filters['jurusan']);
        }

        if (!empty($filters['graduation_year'])) {
            $query->where('alumni_profiles.graduation_year', $filters['graduation_year']);
        }

        // Penyaring status dijalankan di basis data, bukan di frontend. Selama
        // daftarnya belum dipaginasi hal ini tidak terasa; sesudahnya, menyaring
        // di sisi klien hanya menyaring halaman yang sedang terbuka.
        if (!empty($filters['response_status'])) {
            match ($filters['response_status']) {
                'finished'    => $query->whereIn('responses.status', ['submitted', 'verified']),
                'ongoing'     => $query->where('responses.status', 'started'),
                'not_started' => $query->whereNull('responses.id'),
                default       => null,
            };
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('alumni_profiles.nim', 'like', "%{$search}%")
                  ->orWhere('alumni_profiles.name', 'like', "%{$search}%");
            });
        }

        return $query;
    }

    /**
     * Hitung stats alumni per prodi (atau semua kalau $programId null).
     *
     * Return: ['total', 'finished', 'ongoing', 'not_started', 'answered', 'unanswered']
     *
     * Kunci 'finished' dan 'not_started' dulu bernama 'finish' dan
     * 'belum_mengisi' — dialek keempat untuk tiga keadaan yang sama.
     * KaprodiAlumniStats di frontend sudah mendeklarasikan trio kanonik, jadi
     * kedua kunci lama tidak pernah terbaca siapa pun: yang dipakai halaman
     * hanya 'answered' dan 'unanswered'. Nama diseragamkan supaya dialeknya
     * tinggal satu dan medan yang sudah dideklarasikan FE benar-benar terisi.
     */
    /**
     * @param array<int>|null $programIdIn Scope Kajur/Dekan (daftar
     *        program_id dari User::scopedProgramIds()) -- diprioritaskan di
     *        atas $jurusan kalau keduanya somehow terisi.
     */
    public function countStatsByProgram(?int $programId, ?int $graduationYear = null, ?array $programIdIn = null): array
    {
        $conn = DB::connection(self::CONN);

        // Total alumni
        $totalQuery = $conn->table('alumni_profiles');
        if ($programId !== null) {
            $totalQuery->where('program_id', $programId);
        } elseif (!empty($programIdIn)) {
            $totalQuery->whereIn('program_id', $programIdIn);
        }
        if ($graduationYear !== null) {
            $totalQuery->where('alumni_profiles.graduation_year', $graduationYear);
        }
        $total = $totalQuery->count();

        // Kuesioner global published
        $globalQnrIds = $conn->table('questionnaires')
            ->whereNull('program_id')
            ->where('status', 'published')
            ->pluck('id');

        if ($globalQnrIds->isEmpty()) {
            return ['total' => $total, 'finished' => 0, 'ongoing' => 0, 'not_started' => $total, 'answered' => 0, 'unanswered' => $total];
        }

        // Finish: submitted or verified
        $finishQuery = $conn->table('alumni_profiles')
            ->join('responses', 'responses.alumni_id', '=', 'alumni_profiles.id')
            ->whereIn('responses.questionnaire_id', $globalQnrIds->toArray())
            ->whereIn('responses.status', ['submitted', 'verified']);
        if ($programId !== null) {
            $finishQuery->where('alumni_profiles.program_id', $programId);
        } elseif (!empty($programIdIn)) {
            $finishQuery->whereIn('alumni_profiles.program_id', $programIdIn);
        }
        if ($graduationYear !== null) {
            $finishQuery->where('alumni_profiles.graduation_year', $graduationYear);
        }
        $finish = $finishQuery->distinct('alumni_profiles.id')->count('alumni_profiles.id');

        // Ongoing: started
        $ongoingQuery = $conn->table('alumni_profiles')
            ->join('responses', 'responses.alumni_id', '=', 'alumni_profiles.id')
            ->whereIn('responses.questionnaire_id', $globalQnrIds->toArray())
            ->where('responses.status', 'started');
        if ($programId !== null) {
            $ongoingQuery->where('alumni_profiles.program_id', $programId);
        } elseif (!empty($programIdIn)) {
            $ongoingQuery->whereIn('alumni_profiles.program_id', $programIdIn);
        }
        if ($graduationYear !== null) {
            $ongoingQuery->where('alumni_profiles.graduation_year', $graduationYear);
        }
        $ongoing = $ongoingQuery->distinct('alumni_profiles.id')->count('alumni_profiles.id');

        $notStarted = max($total - $finish - $ongoing, 0);

        return [
            'total'       => $total,
            'finished'    => $finish,
            'ongoing'     => $ongoing,
            'not_started' => $notStarted,
            'answered'    => $finish,
            'unanswered'  => $total - $finish,
        ];
    }

    /**
     * Stats alumni DIPECAH per prodi, untuk layar kartu prodi.
     *
     * Bentuknya sengaja tiga kueri beragregasi, bukan pemanggilan
     * countStatsByProgram() sekali per prodi: prodinya puluhan, dan versi
     * per-prodi berarti puluhan kali tiga perjalanan ke basis data untuk
     * satu layar.
     *
     * Definisi "sudah mengisi" dijaga sama persis dengan
     * countStatsByProgram() -- kuesioner global published, dihitung alumni
     * distinct, bukan jumlah baris response. Kalau keduanya berbeda, kartu
     * prodi dan kartu ringkasan di halaman yang sama akan saling
     * bertentangan untuk data yang sama.
     *
     * Alumni tanpa program_id tidak diikutkan: tidak ada kartu yang bisa
     * mewakilinya, dan memaksakannya ke satu kartu "lain-lain" membuat
     * jumlah seluruh kartu tidak lagi cocok dengan angka mana pun.
     *
     * @param array<int>|null $programIdIn Scope Kajur/Dekan.
     * @return array<int, array<string, mixed>> satu baris per prodi
     */
    public function countStatsGroupedByProgram(?int $programId, ?int $graduationYear = null, ?array $programIdIn = null): array
    {
        $conn = DB::connection(self::CONN);

        $scope = function ($query, string $column = 'alumni_profiles.program_id') use ($programId, $programIdIn, $graduationYear) {
            $query->whereNotNull($column);
            if ($programId !== null) {
                $query->where($column, $programId);
            } elseif (!empty($programIdIn)) {
                $query->whereIn($column, $programIdIn);
            }
            if ($graduationYear !== null) {
                $query->where('alumni_profiles.graduation_year', $graduationYear);
            }

            return $query;
        };

        // select eksplisit + alias, bukan pluck(DB::raw('count(*)'), ...):
        // pluck() dengan ekspresi mentah mencoba membaca properti bernama
        // seluruh ekspresi itu pada baris hasil, dan di PostgreSQL kolomnya
        // kembali sebagai "count" — hasilnya nol di mana-mana disertai
        // peringatan properti tak dikenal, bukan galat yang terlihat.
        $totals = $scope($conn->table('alumni_profiles'))
            ->groupBy('alumni_profiles.program_id')
            ->select('alumni_profiles.program_id', DB::raw('count(*) as jumlah'))
            ->pluck('jumlah', 'program_id');

        if ($totals->isEmpty()) {
            return [];
        }

        $globalQnrIds = $conn->table('questionnaires')
            ->whereNull('program_id')
            ->where('status', 'published')
            ->pluck('id')
            ->toArray();

        $countByStatus = function (array $statuses) use ($conn, $scope, $globalQnrIds) {
            if (empty($globalQnrIds)) {
                return collect();
            }

            return $scope(
                $conn->table('alumni_profiles')
                    ->join('responses', 'responses.alumni_id', '=', 'alumni_profiles.id')
                    ->whereIn('responses.questionnaire_id', $globalQnrIds)
                    ->whereIn('responses.status', $statuses),
            )
                ->groupBy('alumni_profiles.program_id')
                ->select('alumni_profiles.program_id', DB::raw('count(distinct alumni_profiles.id) as jumlah'))
                ->pluck('jumlah', 'program_id');
        };

        $finished = $countByStatus(['submitted', 'verified']);
        $ongoing  = $countByStatus(['started']);

        $programs = $conn->table('programs')
            ->whereIn('id', $totals->keys()->all())
            ->get(['id', 'name', 'code', 'degree', 'jurusan'])
            ->keyBy('id');

        $rows = [];

        foreach ($totals as $pid => $total) {
            $program  = $programs->get($pid);
            $total    = (int) $total;
            $answered = (int) ($finished[$pid] ?? 0);
            $running  = (int) ($ongoing[$pid] ?? 0);

            $rows[] = [
                'program_id'     => (int) $pid,
                'program_name'   => $program->name ?? 'Prodi #' . $pid,
                'program_code'   => $program->code ?? null,
                'program_degree' => $program->degree ?? null,
                'jurusan_name'   => $program->jurusan ?? null,
                'total'          => $total,
                'answered'       => $answered,
                'unanswered'     => $total - $answered,
                'ongoing'        => $running,
                'not_started'    => max($total - $answered - $running, 0),
                'response_rate'  => $total > 0 ? round($answered / $total * 100, 1) : 0.0,
            ];
        }

        usort($rows, fn (array $a, array $b) => strcmp((string) $a['program_name'], (string) $b['program_name']));

        return $rows;
    }

    /** @param array<int>|null $programIdIn Scope Kajur/Dekan. */
    public function getAvailableGraduationYears(?int $programId, ?array $programIdIn = null): array
    {
        $query = DB::connection(self::CONN)->table('alumni_profiles')
            ->whereNotNull('graduation_year');

        if ($programId !== null) {
            $query->where('program_id', $programId);
        } elseif (!empty($programIdIn)) {
            $query->whereIn('program_id', $programIdIn);
        }

        return $query->distinct()->orderByDesc('graduation_year')->pluck('graduation_year')->toArray();
    }

    /**
     * Tahun lulusan yang benar-benar punya alumni, dibatasi rentang.
     * Dipakai halaman publik: pengunjung tidak boleh ditawari tahun yang
     * datanya kosong, atau tahun di luar rentang pengarsipan.
     *
     * @param array{start?: int, end?: int} $yearRange
     * @return int[] terbaru dulu
     */
    public function getGraduationYearsInRange(array $yearRange = []): array
    {
        $query = DB::connection(self::CONN)->table('alumni_profiles')
            ->whereNotNull('graduation_year');

        if (isset($yearRange['start'])) {
            $query->where('graduation_year', '>=', $yearRange['start']);
        }
        if (isset($yearRange['end'])) {
            $query->where('graduation_year', '<=', $yearRange['end']);
        }

        return $query->distinct()
            ->orderByDesc('graduation_year')
            ->pluck('graduation_year')
            ->map(fn ($y) => (int) $y)
            ->all();
    }

    /**
     * Tahun lulusan terkecil dan terbesar yang ada di database. Dipakai form
     * pengaturan rentang publik supaya admin tahu batas yang masuk akal.
     *
     * @return array{min: int|null, max: int|null}
     */
    public function getGraduationYearBounds(): array
    {
        $row = DB::connection(self::CONN)->table('alumni_profiles')
            ->selectRaw('MIN(graduation_year) as min_year, MAX(graduation_year) as max_year')
            ->first();

        return [
            'min' => $row?->min_year !== null ? (int) $row->min_year : null,
            'max' => $row?->max_year !== null ? (int) $row->max_year : null,
        ];
    }

    /**
     * Ambil semua alumni untuk laporan export (join programs + responses).
     *
     * @param array{program_id?: int, questionnaire_id?: int, graduation_year?: int} $filters
     *
     * Tambah filter graduation_year (tahun lulus),
     * dipakai untuk dropdown "Pilih tahun yang ingin diunduh" di UI Unduh Data
     * Alumni. Filter ini diterapkan ke alumni_profiles.graduation_year, BUKAN
     * ke responses.submitted_at -- karena yang dimaksud "tahun" di UI adalah
     * tahun KELULUSAN alumni, bukan tahun alumni mengisi kuesioner.
     */
    public function getForReport(array $filters = []): Collection
    {
        $query = DB::connection(self::CONN)->table('alumni_profiles')
            ->leftJoin('responses', 'alumni_profiles.id', '=', 'responses.alumni_id')
            ->leftJoin('programs', 'alumni_profiles.program_id', '=', 'programs.id')
            ->select(
                'alumni_profiles.*',
                'responses.id as response_id',
                'programs.name as program_name',
                'programs.degree as program_degree',
                'programs.code as program_code',
                'programs.jurusan as jurusan_name',
            );
    
        if (!empty($filters['questionnaire_id'])) {
            $query->where('responses.questionnaire_id', $filters['questionnaire_id']);
        }
        if (!empty($filters['program_id'])) {
            $query->where('alumni_profiles.program_id', $filters['program_id']);
        }
        if (!empty($filters['graduation_year'])) {
            $query->where('alumni_profiles.graduation_year', $filters['graduation_year']);
        }
        // Scope kajur/dekan, sama seperti di getForReportQuery().
        // Tanpa klausa ini filter 'program_id_in' yang dikirim ReportService
        // diabaikan diam-diam dan kajur/dekan ikut melihat alumni
        // di luar cakupannya di sheet per-prodi.
        if (!empty($filters['program_id_in'])) {
            $query->whereIn('alumni_profiles.program_id', $filters['program_id_in']);
        }

        return PersonalData::revealRows(collect($query->get()));
    }

    /**
    * Sama seperti getForReport(), tapi MENGEMBALIKAN QUERY BUILDER MENTAH
    * (belum dipanggil ->get()), bukan Collection. Dipakai oleh
    * MinistrySheetExport yang mengimplementasikan FromQuery + WithChunkReading
    * -- maatwebsite/excel akan menjalankan query ini sendiri dalam potongan
    * kecil (chunk), bukan menarik semua baris ke memory PHP sekaligus.
    *
    * PENTING: query ini TIDAK di-order secara default. WithChunkReading
    * Laravel/maatwebsite MEWAJIBKAN query punya urutan yang stabil (biasanya
    * by primary key) supaya chunk tidak melewatkan/menduplikasi baris saat
    * dibaca bertahap -- karena itu ->orderBy('alumni_profiles.id') WAJIB
    * ada di sini, jangan dihapus.
    *
    * @param array{program_id?: int, questionnaire_id?: int, graduation_year?: int, jurusan?: string} $filters
    */
    public function getForReportQuery(array $filters = []): Builder
    {
        $query = DB::connection(self::CONN)->table('alumni_profiles')
            ->leftJoin('responses', 'alumni_profiles.id', '=', 'responses.alumni_id')
            ->leftJoin('programs', 'alumni_profiles.program_id', '=', 'programs.id')
            ->select(
                'alumni_profiles.*',
                'responses.id as response_id',
                'programs.name as program_name',
                'programs.code as program_code',
                // Kode prodi versi PDDIKTI, dipakai MinistrySheetExport saat
                // format=code: portal Kementerian tidak mengenal singkatan
                // internal seperti "TKG". Tanpa kolom ini ekspor kode mentah
                // mati dengan "Undefined property: $program_dikti_code".
                'programs.dikti_code as program_dikti_code',
                'programs.jurusan as jurusan_name',
            )
            ->orderBy('alumni_profiles.id');
    
        if (!empty($filters['questionnaire_id'])) {
            $query->where('responses.questionnaire_id', $filters['questionnaire_id']);
        }
        if (!empty($filters['program_id'])) {
            $query->where('alumni_profiles.program_id', $filters['program_id']);
        }
        if (!empty($filters['graduation_year'])) {
            $query->where('alumni_profiles.graduation_year', $filters['graduation_year']);
        }
        // Scope kajur/dekan: dibatasi ke cakupannya sendiri (daftar
        // program_id dari User::scopedProgramIds()). Tanpa klausa ini filter
        // 'program_id_in' yang dikirim ReportService diabaikan diam-diam dan
        // kajur/dekan ikut melihat alumni di luar cakupannya.
        if (!empty($filters['program_id_in'])) {
            $query->whereIn('alumni_profiles.program_id', $filters['program_id_in']);
        }

        return $query;
    }
    

    // ═══════════════════════════════════════════════════════════
    // WRITE
    // ═══════════════════════════════════════════════════════════

    public function create(array $data): int
    {
        $now = now();
        return DB::connection(self::CONN)->table('alumni_profiles')->insertGetId(
            array_merge(PersonalData::protectAttributes($data), ['created_at' => $now, 'updated_at' => $now])
        );
    }

    public function updateById(int $id, array $data): bool
    {
        return DB::connection(self::CONN)->table('alumni_profiles')
            ->where('id', $id)
            ->update(array_merge(PersonalData::protectAttributes($data), ['updated_at' => now()])) > 0;
    }

    public function deleteById(int $id): bool
    {
        return DB::connection(self::CONN)->table('alumni_profiles')
            ->where('id', $id)
            ->delete() > 0;
    }

    /**
     * Upsert alumni by NIM — kalau sudah ada, update; kalau belum, insert.
     * Return alumni_id.
     */
    public function upsertByNim(string $nim, array $data): int
    {
        $existing = $this->findByNim($nim);
        $now = now();
        $data = PersonalData::protectAttributes($data);

        if ($existing) {
            DB::connection(self::CONN)->table('alumni_profiles')
                ->where('id', $existing->id)
                ->update(array_merge($data, ['updated_at' => $now]));
            return $existing->id;
        }

        return DB::connection(self::CONN)->table('alumni_profiles')->insertGetId(
            array_merge($data, ['nim' => $nim, 'created_at' => $now, 'updated_at' => $now])
        );
    }

    /** Bulk insert alumni rows (used by Excel import). */
    public function bulkInsert(array $rows): void
    {
        $now = now();
        $records = array_map(
            fn ($row) => array_merge(PersonalData::protectAttributes($row), ['created_at' => $now, 'updated_at' => $now]),
            $rows,
        );

        foreach (array_chunk($records, 100) as $chunk) {
            DB::connection(self::CONN)->table('alumni_profiles')->insert($chunk);
        }
    }
}
