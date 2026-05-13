<?php
// app/Services/Transactional/TracerStudySubmitService.php
namespace App\Services\Transactional;

use App\Exceptions\BusinessException;
use App\Repositories\Transactional\AlumniProfileRepository;
use App\Repositories\Transactional\EducationRecordRepository;
use App\Repositories\Transactional\EmploymentRecordRepository;
use App\Repositories\Transactional\ProgramRepository;
use App\Repositories\Transactional\QuestionnaireRepository;
use App\Repositories\Transactional\ResponseRepository;
use Carbon\Carbon;
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

            // 2. Persist response ke kuesioner global (wajib ada)
            $globalQnr = $this->questionnaireRepo->findActiveGlobal();
            if (!$globalQnr) {
                throw new BusinessException('Sistem belum memiliki referensi Kuesioner aktif.', 500);
            }

            $expandedAnswers = $this->expandCheckboxGroups($rawAnswers);

            $this->persistResponse($globalQnr->id, $alumniId, $expandedAnswers);

            // 3. Persist response ke kuesioner prodi (opsional)
            $prodiQnr = $this->questionnaireRepo->findActiveByProgram($program->id);
            if ($prodiQnr) {
                $prodiCodes = $this->questionnaireRepo->getQuestionCodesByQuestionnaireId($prodiQnr->id);
                $this->persistResponse(
                    $prodiQnr->id,
                    $alumniId,
                    $expandedAnswers,
                    filterCodes: $prodiCodes,
                );
            }

            // 4. Normalisasi data ke employment / education records
            $this->persistNormalizedRecords($validated, $alumniId, $globalQnr->id);
        });
    }

    // ═══════════════════════════════════════════════════════════
    // Private helpers
    // ═══════════════════════════════════════════════════════════

    private function upsertAlumni(array $validated, int $programId): int
    {
        return $this->alumniRepo->upsertByNim($validated['nim'], [
            'name'            => $validated['name'],
            'email'           => $validated['email'],
            'phone'           => $validated['phone'],
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
     * Delete response lama (upsert behavior) + insert response baru + bulk insert answers.
     * Kalau $filterCodes diberikan, hanya answer dengan question_code di list itu yang disimpan.
     */
    private function persistResponse(int $questionnaireId, int $alumniId, array $answers, ?array $filterCodes = null): void
    {
        $this->responseRepo->deleteByQuestionnaireAndAlumni($questionnaireId, $alumniId);
        $responseId = $this->responseRepo->createResponse($questionnaireId, $alumniId);

        $records = [];
        foreach ($answers as $key => $value) {
            if (in_array($key, self::IDENTITY_KEYS, strict: true) || $value === null) {
                continue;
            }
            if ($filterCodes !== null && !in_array($key, $filterCodes, strict: true)) {
                continue;
            }
            $records[] = [
                'question_code' => $key,
                'answer_text'   => is_bool($value) ? ($value ? '1' : '0') : (string) $value,
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
    }
}
