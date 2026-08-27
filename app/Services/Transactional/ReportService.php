<?php
// app/Services/Transactional/ReportService.php
namespace App\Services\Transactional;

use App\Exports\TracerStudyMultiSheetExport;
use App\Exports\Sheets\MinistrySheetExport;
use App\Exports\Support\AnswerValueResolver;
use App\Models\Transactional\User;
use App\Repositories\Transactional\AlumniProfileRepository;
use App\Repositories\Transactional\ProgramRepository;
use App\Repositories\Transactional\QuestionnaireRepository;
use App\Repositories\Transactional\ResponseRepository;
use Illuminate\Support\Facades\DB;

/**
 * ReportService — build data untuk export Excel tracer study.
 *
 * CAKUPAN SHEET mengikuti kuesioner yang diekspor (lihat resolveScope()):
 * tombol Export di daftar kuesioner selalu menunjuk SATU kuesioner, jadi
 * file yang dihasilkan hanya berisi sheet milik kuesioner itu — kuesioner
 * Kementerian menghasilkan sheet "Data Kementrian" saja, kuesioner tambahan
 * prodi menghasilkan sheet "Data Khusus {PRODI}" saja.
 *
 * PERBAIKAN PERFORMA (root cause timeout 60 detik, lihat riwayat
 * diskusi untuk detail investigasi):
 *
 * 1. buildProdiQuestionsByProgram() SEBELUMNYA memanggil 2 query
 *    terpisah PER PRODI di dalam loop. Sekarang di-batch: SATU query
 *    untuk semua questionnaire_id prodi sekaligus, di-group di memori.
 *
 * 2. Sheet "Data Kementrian" (yang menampung SEMUA alumni, bisa puluhan
 *    ribu baris) TIDAK LAGI dibangun dari Collection yang di-load penuh
 *    ke memory. MinistrySheetExport sekarang menerima QUERY BUILDER
 *    MENTAH (via AlumniProfileRepository::getForReportQuery()) dan
 *    membaca data dalam chunk (FromQuery + WithChunkReading) -- lihat
 *    MinistrySheetExport untuk detail.
 *
 * 3. Sheet per-prodi TETAP pakai Collection biasa (TIDAK diubah) --
 *    karena datanya sudah pasti jauh lebih kecil (alumni 1 prodi saja,
 *    bukan seluruh institusi), risiko memory di sana rendah dan tidak
 *    perlu kerumitan tambahan FromQuery.
 *
 * 4. Kalau cakupannya hanya sheet prodi, query alumni untuk sheet
 *    Kementrian tidak dijalankan sama sekali (dan sebaliknya).
 */
class ReportService
{
    public function __construct(
        private readonly AlumniProfileRepository $alumniRepo,
        private readonly ResponseRepository      $responseRepo,
        private readonly QuestionnaireRepository $questionnaireRepo,
        private readonly ProgramRepository       $programRepo,
        private readonly AuditLogService         $audit,
    ) {}

    /**
     * @param int  $tahunLulus WAJIB diisi -- UI tidak lagi menyediakan
     *             opsi "Semua Tahun" (keputusan eksplisit, lihat catatan
     *             di ReportController soal alasan: campuran questionnaire_id
     *             berbeda dalam satu sheet bisa membingungkan kolom header).
     * @param bool $rawCode    true = tampilkan option_code/angka mentah
     *             (format untuk diunggah ke portal Kementerian);
     *             false (default) = tampilkan teks jawaban.
     * @param string|null $jurusan TIDAK LAGI DIPAKAI -- dipertahankan hanya
     *             supaya signature tetap kompatibel dengan ReportController
     *             yang masih boleh mengirim param `jurusan` lama. Scope
     *             Kajur & Dekan sekarang murni dari
     *             User::scopedProgramIds() (keanggotaan FK eksplisit),
     *             sehingga keduanya langsung mengekspor data gabungan
     *             seluruh prodi dalam cakupannya tanpa perlu memilih satu
     *             jurusan dulu (Fase 5).
     */
    public function buildAlumniResponsesExport(
        User $user,
        ?int $questionnaireId,
        int $tahunLulus,
        bool $rawCode = false,
        ?string $jurusan = null,
    ): TracerStudyMultiSheetExport {
        $programIdIn = ($user->isKajur() || $user->isDekan()) ? $user->scopedProgramIds() : null;
        if ($programIdIn !== null && empty($programIdIn)) {
            $label = $user->isKajur() ? 'jurusan' : 'fakultas';
            abort(403, "Akun ini belum memiliki {$label} dengan prodi yang di-assign. Hubungi pengelola.");
        }

        $questionnaire = $questionnaireId !== null
            ? $this->questionnaireRepo->findHeaderById($questionnaireId)
            : null;

        // Ekspor adalah perbuatan yang memindahkan data pribadi KELUAR dari
        // sistem: satu berkas bisa membawa ribuan NIK ke perangkat siapa pun
        // yang mengunduhnya, dan sejak itu sistem tidak lagi mengendalikannya.
        // Karena itu ia dicatat setara dengan perubahan data, bukan dianggap
        // pembacaan biasa yang tak perlu jejak. Dicatat SEBELUM berkasnya
        // dirakit supaya percobaan yang gagal di tengah pun tetap terlihat.
        $this->audit->record('export.ministry', [
            'entity_type' => 'alumni_profiles',
            'context'     => [
                'questionnaire_id' => $questionnaireId,
                'tahun_lulus'      => $tahunLulus,
                // Ikut dicatat karena inilah yang membedakan berkas untuk
                // dibaca manusia dari berkas berisi kode mentah yang siap
                // diunggah ke portal Kementerian.
                'format'           => $rawCode ? 'code' : 'label',
                'scope'            => $user->isKaprodi()
                    ? 'prodi'
                    : ($user->isKajur() || $user->isDekan() ? 'jurusan' : 'institusi'),
            ],
        ]);

        [$includeMinistry, $includeProdi] = $this->resolveScope($questionnaire);

        $filters = array_filter([
            'program_id'       => $user->isKaprodi() ? $user->program_id : null,
            'program_id_in'    => $programIdIn,
            'questionnaire_id' => $questionnaireId,
            'graduation_year'  => $tahunLulus,
        ], fn ($v) => $v !== null);

        // ── Sheet "Data Kementrian": query builder mentah, dibaca chunk
        //    oleh MinistrySheetExport sendiri, TIDAK di-execute di sini. ──
        $ministrySheet = null;
        if ($includeMinistry) {
            $ministryQnrIds = $this->resolveMinistryQuestionnaireIds($questionnaire, $tahunLulus);

            $ministrySheet = new MinistrySheetExport(
                $this->alumniRepo->getForReportQuery($filters),
                $this->responseRepo,
                $this->questionnaireRepo,
                $this->buildValueResolver(MinistrySheetExport::MINISTRY_QUESTION_CODES, $ministryQnrIds, $rawCode),
                $ministryQnrIds,
                $rawCode,
            );
        }

        if (!$includeProdi) {
            return new TracerStudyMultiSheetExport($ministrySheet, []);
        }

        [$prodiQuestionsByProgram, $prodiQnrIds] = $this->buildProdiQuestionsByProgram(
            restrictToProgramId: $user->isKaprodi() ? $user->program_id : null,
            questionnaire:       $questionnaire,
        );

        $prodiQuestionsGrouped = $this->attachAlumniToProdiQuestions($filters, $prodiQuestionsByProgram);

        $prodiCodes = $this->collectQuestionCodes($prodiQuestionsByProgram);

        return new TracerStudyMultiSheetExport(
            $ministrySheet,
            $prodiQuestionsGrouped,
            $this->buildValueResolver($prodiCodes, $prodiQnrIds, $rawCode),
        );
    }

    // ═══════════════════════════════════════════════════════════
    // Private helpers
    // ═══════════════════════════════════════════════════════════

    /**
     * Tentukan sheet mana yang ikut, dari kuesioner yang diekspor.
     *
     * questionnaires.program_id NULL berarti kuesioner global (Kementerian),
     * terisi berarti kuesioner tambahan milik satu prodi. Tanpa
     * questionnaire_id sama sekali (mis. tombol unduh di dashboard yang
     * hanya memfilter tahun), cakupannya tetap keduanya seperti dulu.
     *
     * @return array{0: bool, 1: bool} [includeMinistry, includeProdi]
     */
    private function resolveScope(?object $questionnaire): array
    {
        if ($questionnaire === null) {
            return [true, true];
        }

        return $questionnaire->program_id === null
            ? [true, false]
            : [false, true];
    }

    /**
     * Muat alumni + jawabannya, lalu tempelkan ke masing-masing prodi.
     * Prodi yang tidak punya daftar pertanyaan tetap dimasukkan dengan
     * questions kosong; TracerStudyMultiSheetExport yang memutuskan sheet
     * kosong tidak usah dibuat.
     */
    private function attachAlumniToProdiQuestions(array $filters, array $prodiQuestionsByProgram): array
    {
        $alumniProfiles = $this->alumniRepo->getForReport($filters);

        $responseIds = $alumniProfiles->pluck('response_id')->filter()->toArray();
        $answers     = $this->responseRepo->getAnswersByResponseIds($responseIds);

        $answersGrouped = $answers
            ->groupBy('response_id')
            ->map(fn ($items) => $items->pluck('answer_text', 'question_code')->toArray());

        $alumniData = $alumniProfiles->map(function ($item) use ($answersGrouped) {
            $item->answers = $item->response_id ? ($answersGrouped->get($item->response_id) ?? []) : [];
            return $item;
        });

        $grouped = [];
        foreach ($alumniData->groupBy('program_code') as $prodiCode => $prodiAlumni) {
            if (!$prodiCode || !isset($prodiQuestionsByProgram[$prodiCode])) {
                continue;
            }

            $grouped[$prodiCode] = [
                'name'      => $prodiAlumni->first()->program_name ?? $prodiCode,
                'questions' => $prodiQuestionsByProgram[$prodiCode],
                'alumni'    => $prodiAlumni,
            ];
        }

        return $grouped;
    }

    /** @return string[] semua question_code yang muncul di sheet prodi */
    private function collectQuestionCodes(array $prodiQuestionsByProgram): array
    {
        $codes = [];
        foreach ($prodiQuestionsByProgram as $questions) {
            foreach ($questions as $q) {
                $codes[] = $q['code'];
            }
        }

        return array_values(array_unique($codes));
    }

    /**
     * Questionnaire mana yang jadi sumber label/metadata untuk sheet
     * Kementrian. Kalau export dipicu dari tombol di daftar kuesioner,
     * itu kuesioner yang diklik. Kalau dipicu dari tombol unduh dashboard
     * (tanpa questionnaire_id), dipakai kuesioner global yang berlaku untuk
     * tahun lulus tersebut -- bukan dibiarkan kosong, karena tanpa pembatas
     * lookup metadata jadi tidak deterministik.
     *
     * @return int[]
     */
    private function resolveMinistryQuestionnaireIds(?object $questionnaire, int $tahunLulus): array
    {
        if ($questionnaire !== null) {
            return [$questionnaire->id];
        }

        $global = $this->questionnaireRepo->findActiveGlobalForYear($tahunLulus);

        return $global !== null ? [$global->id] : [];
    }

    /**
     * Untuk mode kode mentah, resolver "kosong" dipakai supaya query
     * options/provinces/cities tidak dijalankan sama sekali.
     *
     * @param string[] $questionCodes
     * @param int[]    $questionnaireIds pembatas asal pertanyaan
     */
    private function buildValueResolver(array $questionCodes, array $questionnaireIds, bool $rawCode): AnswerValueResolver
    {
        if ($rawCode || $questionCodes === []) {
            return AnswerValueResolver::raw();
        }

        return new AnswerValueResolver(
            $this->questionnaireRepo->getOptionsGroupedByCode($questionCodes, $questionnaireIds),
            $this->questionnaireRepo->getQuestionMetaByCode($questionCodes, $questionnaireIds),
            DB::connection('oltp')->table('provinces')->pluck('name', 'id')->toArray(),
            DB::connection('oltp')->table('cities')->pluck('name', 'id')->toArray(),
        );
    }

    private function buildHeaderList(array $codes, array $labels): array
    {
        return array_map(function ($code) use ($labels) {
            $text = $labels[$code] ?? $code;
            if (mb_strlen($text) > 80) {
                $text = mb_substr($text, 0, 77) . '...';
            }
            return ['code' => $code, 'label' => "{$text} ({$code})"];
        }, $codes);
    }

    /**
     * Daftar pertanyaan per kode prodi.
     *
     * Kalau $questionnaire diberikan dan memang kuesioner prodi, pertanyaan
     * diambil dari kuesioner ITU -- bukan dari listActiveProdiQuestionnaires()
     * yang memilih satu kuesioner published per prodi secara sembarang dan
     * bisa menunjuk kuesioner tahun lain.
     *
     * Kalau tidak, jalur lama dipakai: SATU query untuk semua questionnaire_id
     * prodi sekaligus (lihat catatan perbaikan N+1 di docblock kelas).
     *
     * @return array{0: array<string,array>, 1: int[]} [pertanyaan per kode prodi, questionnaire_id yang dipakai]
     */
    private function buildProdiQuestionsByProgram(
        ?int $restrictToProgramId = null,
        ?object $questionnaire = null,
    ): array {
        if ($questionnaire !== null && $questionnaire->program_id !== null) {
            if ($restrictToProgramId !== null && $questionnaire->program_id !== $restrictToProgramId) {
                return [[], []];
            }

            $program = $this->programRepo->allIndexedById()[$questionnaire->program_id] ?? null;
            if (!$program) {
                return [[], []];
            }

            $questions = $this->questionnaireRepo->getQuestionsByQuestionnaireId($questionnaire->id);

            return [
                [
                    $program->code => $this->buildHeaderList(
                        $questions->pluck('code')->toArray(),
                        $questions->pluck('question_text', 'code')->toArray(),
                    ),
                ],
                [$questionnaire->id],
            ];
        }

        $prodiQnrs  = $this->questionnaireRepo->listActiveProdiQuestionnaires();
        $programMap = $this->programRepo->allIndexedById();

        if ($restrictToProgramId !== null) {
            $prodiQnrs = $prodiQnrs->only([$restrictToProgramId]);
        }

        if ($prodiQnrs->isEmpty()) {
            return [[], []];
        }

        $questionnaireIds = $prodiQnrs->pluck('id')->toArray();
        $allQuestions = $this->questionnaireRepo->getQuestions($questionnaireIds);
        $questionsByQnrId = $allQuestions->groupBy('questionnaire_id');

        $result = [];
        foreach ($prodiQnrs as $programId => $qnr) {
            $program = $programMap[$programId] ?? null;
            if (!$program) {
                continue;
            }

            $questionsForQnr = $questionsByQnrId->get($qnr->id, collect());
            $codes  = $questionsForQnr->pluck('code')->toArray();
            $labels = $questionsForQnr->pluck('question_text', 'code')->toArray();

            $result[$program->code] = $this->buildHeaderList($codes, $labels);
        }

        return [$result, $questionnaireIds];
    }
}
