<?php
// app/Services/Transactional/TracerStudySubmitService.php
namespace App\Services\Transactional;

use App\Events\AlumniDataChanged;  
use App\Exceptions\BusinessException;
use App\Repositories\Transactional\AlumniProfileRepository;
use App\Repositories\Transactional\EducationRecordRepository;
use App\Repositories\Transactional\EmploymentRecordRepository;
use App\Repositories\Transactional\ProgramRepository;
use App\Repositories\Transactional\QuestionnaireRepository;
use App\Repositories\Transactional\ResponseRepository;
use App\Repositories\Transactional\StakeholderContactRepository;
use App\Support\PhoneNumber;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * TracerStudySubmitService — orchestrator alumni submission.
 *
 * Menyimpan 1 submisi kuesioner Tracer Study lintas 5 tabel dalam 1 transaction:
 *   1. Resolve program dari kode prodi (kdpstmsmh)
 *   2. Upsert alumni profile berdasarkan NIM
 *   3. Replace response + response_answers ke kuesioner global (nasional)
 *   4. Replace response + response_answers ke kuesioner prodi (kalau ada)
 *   5. Replace employment_records (kalau bekerja) atau education_records (kalau lanjut studi)
 *   6. Replace stakeholder_contacts (kalau bekerja, wiraswasta, atau lanjut studi)
 *
 * Semua operasi dibungkus DB transaction di level service. Kalau salah satu
 * gagal, seluruh perubahan di-rollback.
 */
class TracerStudySubmitService
{
    private const CONN = 'oltp';

    /** Kode grup checkbox → list kode individual yang harus di-expand jadi 0/1. */
    private const CHECKBOX_GROUPS = [
        'q16_cara_cari_kerja'   => 401,
        'q21_alasan_tidak_sesuai' => 1601,
    ];

    /** Range end kode per grup. */
    private const CHECKBOX_GROUP_RANGES = [
        'q16_cara_cari_kerja'     => [401, 415],
        'q21_alasan_tidak_sesuai' => [1601, 1613],
    ];

    /** Kode yang tidak disimpan ke response_answers (sudah ada di alumni_profiles). */
    private const IDENTITY_KEYS = ['nim', 'name', 'email', 'phone', 'tahun_lulus', 'kdpstmsmh', 'kode_pt', 'nik', 'npwp'];

    public function __construct(
        private readonly ProgramRepository          $programRepo,
        private readonly AlumniProfileRepository    $alumniRepo,
        private readonly QuestionnaireRepository    $questionnaireRepo,
        private readonly ResponseRepository         $responseRepo,
        private readonly EmploymentRecordRepository $employmentRepo,
        private readonly EducationRecordRepository  $educationRepo,
        private readonly StakeholderContactRepository $stakeholderRepo,
    ) {}

    /**
     * Submit 1 tracer study response.
     *
     * @param array $validated data hasil validasi SubmitTracerStudyRequest
     * @param array $rawAnswers seluruh body request (untuk answer dump ke response_answers)
     *
     * @throws BusinessException 400 kalau kode prodi invalid, 500 kalau tidak ada kuesioner global
     */
    public function submit(array $validated, array $rawAnswers): void
    {
        $program = $this->programRepo->findByCode($validated['kdpstmsmh']);
        if (!$program) {
            throw new BusinessException(
                "Program Studi dengan kode {$validated['kdpstmsmh']} tidak ditemukan.",
                400,
            );
        }

        DB::connection(self::CONN)->transaction(function () use ($validated, $rawAnswers, $program) {
            // 1. Upsert alumni
            $alumniId = $this->upsertAlumni($validated, $program->id);

            // 2. Tentukan kuesioner mana yang benar-benar dikirim kali ini.
            //
            // Kandidatnya diambil dari sumber YANG SAMA dengan halaman
            // pengisian (QuestionnaireFetchService::getActiveForms), bukan
            // lewat findActiveGlobal*/findActiveByProgram* yang hanya
            // mengembalikan satu baris pertama. Kalau keduanya berbeda,
            // kuesioner yang tampil di formulir bisa ditolak saat dikirim —
            // dan alumni tidak punya cara memahami penolakannya.
            $graduationYear = (int) ($validated['tahun_lulus'] ?? 0);
            $candidates = $graduationYear > 0
                ? $this->questionnaireRepo->findActiveForProdiAndYear($program->id, $graduationYear)
                : $this->questionnaireRepo->findActiveForProdi($program->id);

            $globalQnr = $candidates->first(fn ($qnr) => is_null($qnr->program_id));
            if (!$globalQnr) {
                throw new BusinessException('Sistem belum memiliki referensi Kuesioner aktif.', 500);
            }

            $targets = $this->resolveTargets($candidates, $validated);

            // 3. RBAC-18 — satu kali pengisian per kuesioner.
            $this->assertNotYetSubmitted($alumniId, $targets);

            $expandedAnswers = $this->expandCheckboxGroups($rawAnswers);

            // 4. Persist response, hanya ke kuesioner yang jadi sasaran
            foreach ($targets as $qnr) {
                // Kuesioner prodi hanya menerima kode miliknya sendiri;
                // kuesioner umum menerima seluruh jawaban.
                $filterCodes = is_null($qnr->program_id)
                    ? null
                    : $this->questionnaireRepo->getQuestionCodesByQuestionnaireId($qnr->id);

                $this->persistResponse($qnr->id, $alumniId, $expandedAnswers, filterCodes: $filterCodes);
            }

            // 5. Normalisasi data ke employment / education records.
            //
            // HANYA kalau kuesioner umum ikut dikirim. Seluruh isian yang
            // dinormalisasi di sini (f8 status alumni, f502 masa tunggu, f505
            // gaji, f18b/f18c studi lanjut, stk* kontak penilai) adalah milik
            // kuesioner umum. Pada pengiriman yang hanya mencakup kuesioner
            // prodi — mis. setelah Ketua Tracer membuka kembali kuesioner
            // prodi saja — isian itu tidak ada di payload, sehingga f8 terbaca
            // 0 dan persistNormalizedRecords() akan MENGHAPUS kontak penilai
            // serta membiarkan employment/education record lama menggantung.
            // Melewatinya adalah perilaku yang benar: tidak ada pernyataan
            // baru tentang pekerjaan alumni pada kiriman ini.
            if ($this->includesGlobal($targets)) {
                $this->persistNormalizedRecords($validated, $alumniId, $globalQnr->id);
            }
        });
        // Transaction sudah commit (DB::transaction() commit otomatis sebelum
        // closure-nya return tanpa exception). Dispatch di sini — bukan di
        // dalam closure — supaya event cuma nembak kalau seluruh submission
        // beneran tersimpan, dan tidak ikut ke-rollback kalau ada error.
        event(new AlumniDataChanged(
            programId:     $program->id,
            graduatedYear: (int) $validated['tahun_lulus'],
        ));
    }

    // ═══════════════════════════════════════════════════════════
    // Private helpers
    // ═══════════════════════════════════════════════════════════

    /**
     * Kuesioner yang benar-benar disasar kiriman ini.
     *
     * Formulir alumni menggabungkan kuesioner umum dan kuesioner prodi menjadi
     * satu tampilan, tapi pengirimannya menghasilkan baris responses terpisah
     * per kuesioner. Sejak pembukaan kembali bisa bersifat sebagian, "kuesioner
     * mana yang sedang terbuka" tidak lagi selalu berarti keduanya — dan
     * frontend sudah lama mengirimkan jawabannya lewat `questionnaire_ids`
     * (daftar bagian yang benar-benar ditampilkan). Nilai itu dulu dibuang
     * begitu saja di persistResponse(); di sini akhirnya dipakai.
     *
     * Daftar dari klien TIDAK dipercaya apa adanya, hanya dipakai sebagai
     * penyaring atas kuesioner yang memang aktif bagi alumni ini. Dengan
     * begitu id karangan tidak bisa menyelipkan kiriman ke kuesioner milik
     * prodi lain, dan kiriman lama yang belum mengirim field ini tetap
     * berjalan seperti sebelumnya (keduanya jadi sasaran).
     *
     * @param  Collection<int,object> $candidates kuesioner aktif bagi alumni ini
     * @return array<object> kuesioner sasaran
     */
    private function resolveTargets(Collection $candidates, array $validated): array
    {
        $requested = $validated['questionnaire_ids'] ?? null;
        if (!is_array($requested) || empty($requested)) {
            return $candidates->values()->all();
        }

        $requestedIds = array_map('intval', $requested);
        $targets = $candidates
            ->filter(fn ($qnr) => in_array((int) $qnr->id, $requestedIds, strict: true))
            ->values()
            ->all();

        if (empty($targets)) {
            throw new BusinessException(
                'Kuesioner yang dikirim tidak sesuai dengan kuesioner aktif untuk program studi dan tahun lulus Anda.',
                422,
            );
        }

        return $targets;
    }

    /**
     * RBAC-18 — alumni hanya boleh mengisi satu kali per kuesioner.
     *
     * Ditegakkan DI SINI, bukan sekadar disembunyikan di antarmuka. Selama ini
     * satu-satunya penghalang pengiriman kedua adalah layar "Anda Sudah
     * Mengisi" di frontend, sementara jalur submit-nya menghapus baris lama
     * lalu menulis ulang — siapa pun yang mengirim POST langsung bisa menimpa
     * jawabannya sendiri tanpa jejak, dan perubahan itu ikut merembes ke
     * dashboard lewat ETL pada snapshot berikutnya.
     *
     * Pemeriksaan bersifat PER KUESIONER, bukan per alumni. Kalau Ketua Tracer
     * membuka kembali kuesioner prodi saja, kuesioner umum yang masih terkirim
     * tidak boleh membuat seluruh kiriman ditolak — yang ditolak hanya
     * kuesioner yang memang masih terkunci. Baris berstatus 'started' (draf
     * maupun hasil pembukaan kembali) sengaja tidak dihitung; itulah yang
     * membuat alur reset tetap bisa berjalan.
     *
     * @param array<object> $targets
     * @throws BusinessException 409
     */
    private function assertNotYetSubmitted(int $alumniId, array $targets): void
    {
        $targetIds = array_map(fn ($qnr) => (int) $qnr->id, $targets);
        $locked    = $this->responseRepo->findSubmittedQuestionnaireIds($alumniId, $targetIds);

        if (empty($locked)) {
            return;
        }

        $titles = collect($targets)
            ->filter(fn ($qnr) => in_array((int) $qnr->id, $locked, strict: true))
            ->pluck('title')
            ->implode(', ');

        throw new BusinessException(
            "Anda sudah pernah mengirim kuesioner berikut: {$titles}. "
            . 'Pengisian hanya dapat dilakukan satu kali. Hubungi Tim Tracer '
            . 'bila ada jawaban yang perlu diperbaiki.',
            409,
        );
    }

    /** @param array<object> $targets */
    private function includesGlobal(array $targets): bool
    {
        foreach ($targets as $qnr) {
            if (is_null($qnr->program_id)) {
                return true;
            }
        }

        return false;
    }

    private function upsertAlumni(array $validated, int $programId): int
    {
        // Aturan pembakuan dipusatkan di App\Support\PhoneNumber (DATA-09).
        // Salinan yang dulu ada di sini sudah menyimpang dari salinan di
        // AlumniImport: yang ini tidak punya cabang untuk nomor yang sudah
        // berawalan '+62'.
        $phone = PhoneNumber::normalize($validated['phone'] ?? null);

        return $this->alumniRepo->upsertByNim($validated['nim'], [
            'name'            => $validated['name'],
            'email'           => $validated['email'],
            'phone'           => $phone,
            'program_id'      => $programId,
            'graduation_year' => $validated['tahun_lulus'],
            'kode_pt'         => $validated['kode_pt'] ?? null,
            'nik'             => $validated['nik'],
            'npwp'            => $validated['npwp'] ?? null,
        ]);
    }

    /**
     * Expand grouped checkbox answer menjadi list boolean per kode individu.
     * Contoh: q16_cara_cari_kerja = ['f401', 'f403'] → f401='1', f402='0', f403='1', ...
     */
    private function expandCheckboxGroups(array $answers): array
    {
        foreach (self::CHECKBOX_GROUP_RANGES as $groupKey => [$from, $to]) {
            if (isset($answers[$groupKey]) && is_array($answers[$groupKey])) {
                $selected = array_map('strval', $answers[$groupKey]);
                for ($code = $from; $code <= $to; $code++) {
                    $fCode = 'f' . $code;
                    $answers[$fCode] = in_array($fCode, $selected, strict: true) ? '1' : '0';
                }
                unset($answers[$groupKey]);
            }
        }
        return $answers;
    }

    /**
     * Ganti draf yang tertinggal dengan baris submisi + bulk insert answers.
     * Kalau $filterCodes diberikan, hanya answer dengan question_code di list itu yang disimpan.
     *
     * Yang dihapus hanya baris berstatus 'started'. Pengisian yang sudah
     * selesai tidak pernah sampai ke sini — sudah ditolak assertNotYetSubmitted().
     */
    private function persistResponse(int $questionnaireId, int $alumniId, array $answers, ?array $filterCodes = null): void
    {
        // Waktu mulai dipungut dari draf SEBELUM drafnya dihapus (ISI-04).
        // Autosave sudah mencatat kapan alumni pertama kali menjawab; tanpa
        // langkah ini nilai itu ikut terbuang bersama baris draf, dan durasi
        // pengisian di SummaryRepository kehilangan salah satu ujungnya.
        $draft     = $this->responseRepo->findByQuestionnaireAndAlumni($questionnaireId, $alumniId);
        $startedAt = $draft?->started_at ? Carbon::parse($draft->started_at) : null;

        $this->responseRepo->deleteDraftByQuestionnaireAndAlumni($questionnaireId, $alumniId);
        $responseId = $this->responseRepo->createResponse(
            $questionnaireId,
            $alumniId,
            'submitted',
            $startedAt,
        );

        $records = [];
        foreach ($answers as $key => $value) {
            if (in_array($key, self::IDENTITY_KEYS, strict: true) || $value === null) {
                continue;
            }
            if ($key === 'questionnaire_ids') {
                continue;
            }
            if ($filterCodes !== null && !in_array($key, $filterCodes, strict: true)) {
                continue;
            }
            $text = match (true) {
                is_bool($value) => $value ? '1' : '0',
                is_array($value) => json_encode($value),
                default => (string) $value,
            };
            $records[] = [
                'question_code' => $key,
                'answer_text'   => $text,
            ];
        }

        $this->responseRepo->bulkInsertAnswers($responseId, $records);
    }

    /**
     * Berdasarkan f8 (status alumni) — replace employment / education record.
     *   f8 = 1 (pekerja)      → employment_records
     *   f8 = 3 (wiraswasta)   → employment_records
     *   f8 = 4 (lanjut studi) → education_records
     */
    private function persistNormalizedRecords(array $validated, int $alumniId, int $questionnaireId): void
    {
        $status = (int) ($validated['f8'] ?? 0);

        if (in_array($status, [1, 3], strict: true)) {
            $this->employmentRepo->deleteByAlumniId($alumniId);
            $this->employmentRepo->create([
                'alumni_id'         => $alumniId,
                'questionnaire_id'  => $questionnaireId,
                'employment_status' => $status === 1 ? 'employed' : 'entrepreneur',
                'waiting_months'    => $validated['f502'] ?? null,
                'salary_current'    => $validated['f505'] ?? null,
                'work_city'         => $validated['f5a2'] ?? null,
                'company_name'      => $validated['f5b']  ?? null,
                'job_title'         => $validated['f5c']  ?? null,
            ]);
        } elseif ($status === 4) {
            $this->educationRepo->deleteByAlumniId($alumniId);
            $this->educationRepo->create([
                'alumni_id'        => $alumniId,
                'questionnaire_id' => $questionnaireId,
                'is_further_study' => true,
                'institution_name' => $validated['f18b'] ?? null,
                'major'            => $validated['f18c'] ?? null,
            ]);
        }

        $this->persistStakeholderContacts($validated, $alumniId, $questionnaireId, $status);
    }

    /** Nilai f8 → kolom alumni_status di stakeholder_contacts. */
    private const STAKEHOLDER_STATUS = [
        1 => 'bekerja',
        3 => 'wiraswasta',
        4 => 'lanjut_studi',
    ];

    /** Urutan pasangan isian → contact_type. Kunci array = nomor penilai. */
    private const STAKEHOLDER_TYPES = [
        1 => 'atasan',
        2 => 'senior',
        3 => 'rekan',
    ];

    /**
     * Menyimpan kontak penilai yang alumni tuliskan di bagian "Kontak Penilai".
     *
     * Hanya untuk f8 = 1, 3, atau 4 — sama dengan syarat show_if pertanyaannya
     * di kuesioner. Pemeriksaan diulang di sini, bukan sekadar mengandalkan
     * antarmuka, supaya kiriman yang tidak lewat formulir tetap tunduk aturan
     * yang sama.
     *
     * Pasangan yang tidak lengkap (nama ada tapi surel kosong, atau
     * sebaliknya) dilewati: tabel tujuan menuntut keduanya terisi, dan
     * separuh kontak tidak berguna bagi Tim Tracer yang perlu mengirim
     * kuesioner penilaian.
     *
     * Status di luar ketiganya TIDAK sekadar dilewati, melainkan menghapus
     * kontak yang sudah ada. Alumni yang sebelumnya mengisi "Bekerja" lalu
     * mengisi ulang dengan "sedang mencari kerja" tidak lagi punya atasan
     * untuk dinilai — meninggalkan kontak lamanya berarti Tim Tracer
     * mengirim kuesioner penilaian ke orang yang sudah tidak relevan.
     */
    private function persistStakeholderContacts(
        array $validated,
        int $alumniId,
        int $questionnaireId,
        int $status,
    ): void {
        $alumniStatus = self::STAKEHOLDER_STATUS[$status] ?? null;

        $contacts = [];
        foreach ($alumniStatus === null ? [] : self::STAKEHOLDER_TYPES as $no => $type) {
            $name  = trim((string) ($validated["stk{$no}_nama"]  ?? ''));
            $email = trim((string) ($validated["stk{$no}_email"] ?? ''));

            if ($name === '' || $email === '') {
                continue;
            }

            $contacts[] = [
                'contact_type'  => $type,
                'contact_name'  => $name,
                'contact_email' => $email,
                'alumni_status' => $alumniStatus,
            ];
        }

        // Dipanggil juga saat $contacts kosong: bulkUpsert() menghapus kontak
        // lama lebih dulu, sehingga pengisian ulang yang mengosongkan ketiga
        // pasangan — atau yang berganti ke status tanpa penilai — benar-benar
        // menghapus, bukan menyisakan data usang.
        $this->stakeholderRepo->bulkUpsert($alumniId, $questionnaireId, $contacts);
    }
}
