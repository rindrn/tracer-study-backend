<?php

namespace App\Repositories\ETL;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Sumber tracer_oltp.semantic_role_registry + tracer_oltp.question_semantic_mapping.
 * Dipakai DUA konsumen berbeda:
 *
 *   1. ETL runtime (StatusAlumniDimService, KesesuaianBidangDimService,
 *      KesesuaianLevelDimService, AlumniFactBuilderService) -- method
 *      getActiveMappingsForQuestionnaires()/getActiveRoleRegistry()/
 *      questionCodeFor(). Di-load SEKALI per run (bukan per-alumni):
 *      method-method ini memoize hasilnya secara internal per (set)
 *      questionnaire_id, pola caching yang sama dipakai AnswerResolverService
 *      untuk options/meta.
 *
 *   2. API admin (QuestionSemanticMappingController / KpiCategoryMapping
 *      tidak lewat sini -- itu di schema public/olap, lihat
 *      Repositories\Config\KpiCategoryMappingRepository) -- method
 *      list/find/insert/deactivate untuk CRUD mapping question_code<->role.
 *      Method-method ini TIDAK memoize (data admin harus selalu fresh).
 */
class SemanticMappingRepository
{
    /** @var array<string,Collection> cache: sorted questionnaireIds key -> Collection{by_code,by_role} */
    private array $mappingsCache = [];

    /** @var Collection|null cache: semantic_role_registry aktif, keyed by role_key */
    private ?Collection $roleRegistryCache = null;

    private function oltp(): \Illuminate\Database\Connection
    {
        return DB::connection('oltp');
    }

    // ═══════════════════════════════════════════════════════════
    // ETL runtime — di-load sekali per run, di-memoize di sini
    // ═══════════════════════════════════════════════════════════

    /**
     * Semua mapping AKTIF untuk sekumpulan questionnaire_id, di-index dua
     * arah supaya lookup O(1) baik dari sisi (questionnaire, question_code)
     * -- dipakai AlumniFactBuilderService memivot jawaban -> role -- maupun
     * (questionnaire, semantic_role) -- dipakai dim service resolve
     * role -> question_code aktif.
     *
     * @return Collection{by_code: Collection<string,object>, by_role: Collection<string,object>}
     */
    public function getActiveMappingsForQuestionnaires(array $questionnaireIds): Collection
    {
        $questionnaireIds = array_values(array_unique($questionnaireIds));
        sort($questionnaireIds);
        $cacheKey = implode(',', $questionnaireIds);

        if (isset($this->mappingsCache[$cacheKey])) {
            return $this->mappingsCache[$cacheKey];
        }

        if (empty($questionnaireIds)) {
            return $this->mappingsCache[$cacheKey] = collect(['by_code' => collect(), 'by_role' => collect()]);
        }

        $rows = $this->oltp()->table('question_semantic_mapping')
            ->whereIn('questionnaire_id', $questionnaireIds)
            ->where('is_active', true)
            ->select(['id', 'questionnaire_id', 'question_code', 'semantic_role', 'grain'])
            ->get();

        $indexed = collect([
            'by_code' => $rows->keyBy(fn ($r) => $r->questionnaire_id . ':' . $r->question_code),
            // AMAN di-keyBy langsung (last-wins tidak pernah kejadian) karena
            // constraint uq_qsm_active_narrow_role menjamin unik per
            // (questionnaire_id, semantic_role) UNTUK grain='narrow'. Role
            // wide-grain (banyak question_code sah berbagi 1 role) TIDAK
            // dipakai lewat index ini -- fact_multi_select/fact_range_evaluasi
            // tetap resolve identitas per-item via dim_indikator_evaluasi
            // seperti sebelumnya, bukan lewat mapping ini.
            'by_role' => $rows->keyBy(fn ($r) => $r->questionnaire_id . ':' . $r->semantic_role),
        ]);

        return $this->mappingsCache[$cacheKey] = $indexed;
    }

    public function getActiveRoleRegistry(): Collection
    {
        return $this->roleRegistryCache ??= $this->oltp()->table('semantic_role_registry')
            ->where('is_active', true)
            ->get()
            ->keyBy('role_key');
    }

    /**
     * Kode pertanyaan AKTIF untuk (questionnaire, role) tertentu. HANYA
     * valid dipakai untuk role grain='narrow' (lihat catatan by_role di
     * getActiveMappingsForQuestionnaires()) -- ketiga dim service pemanggil
     * (status_pekerjaan/relevansi_bidang/kesesuaian_level) semua memakai
     * role narrow, jadi aman. Null berarti tidak ada mapping aktif --
     * caller WAJIB soft-fail (skip questionnaire ini), bukan throw.
     */
    public function questionCodeFor(int $questionnaireId, string $roleKey): ?string
    {
        $indexed = $this->getActiveMappingsForQuestionnaires([$questionnaireId]);
        return $indexed['by_role']->get($questionnaireId . ':' . $roleKey)?->question_code;
    }

    // ═══════════════════════════════════════════════════════════
    // API admin — CRUD question_semantic_mapping (tidak di-memoize)
    // ═══════════════════════════════════════════════════════════

    /**
     * Kuesioner "nasional" (program_id IS NULL) untuk selector di halaman
     * Pemetaan Pertanyaan -- HANYA kuesioner ini yang pernah punya
     * question_code dari RELEVANT_QUESTION_CODES lama (dikonfirmasi dari
     * data live: 108 "Kuesioner Tambahan" per-prodi memakai kode yang sama
     * sekali berbeda, di luar cakupan fitur ini). Query ringan
     * (id/code/title/period_year saja) -- SENGAJA tidak lewat
     * QuestionnaireService::list(), yang memuat struktur penuh tiap
     * kuesioner (mahal, dirancang untuk halaman Form Management, bukan
     * untuk sekadar mengisi dropdown).
     */
    public function listNationalQuestionnaires(): Collection
    {
        return $this->oltp()->table('questionnaires')
            ->whereNull('program_id')
            ->select(['id', 'code', 'title', 'period_year'])
            ->orderByDesc('period_year')
            ->get();
    }

    public function listRoles(): Collection
    {
        return $this->oltp()->table('semantic_role_registry')
            ->where('is_active', true)
            ->orderBy('category')
            ->orderBy('label')
            ->get();
    }

    public function listMappings(?int $questionnaireId, ?bool $isActive): Collection
    {
        $q = $this->oltp()->table('question_semantic_mapping as qsm')
            ->leftJoin('users as u', 'u.id', '=', 'qsm.mapped_by')
            ->select([
                'qsm.id', 'qsm.questionnaire_id', 'qsm.question_code', 'qsm.question_text_snapshot',
                'qsm.semantic_role', 'qsm.grain', 'qsm.effective_date', 'qsm.is_active', 'qsm.deactivated_at',
                'qsm.mapped_by as mapped_by_id', 'u.name as mapped_by_name',
            ]);

        if ($questionnaireId !== null) {
            $q->where('qsm.questionnaire_id', $questionnaireId);
        }
        if ($isActive !== null) {
            $q->where('qsm.is_active', $isActive);
        }

        return $q->orderByDesc('qsm.id')->get();
    }

    public function findMappingById(int $id): ?object
    {
        return $this->oltp()->table('question_semantic_mapping')->where('id', $id)->first();
    }

    /** Constraint B: baris aktif lain untuk (questionnaire_id, question_code) yang sama, kalau ada. */
    public function findActiveByCode(int $questionnaireId, string $questionCode): ?object
    {
        return $this->oltp()->table('question_semantic_mapping')
            ->where('questionnaire_id', $questionnaireId)
            ->where('question_code', $questionCode)
            ->where('is_active', true)
            ->first();
    }

    /** Constraint A: baris aktif lain yang sudah memegang role narrow ini di questionnaire yang sama. */
    public function findActiveByRole(int $questionnaireId, string $semanticRole): ?object
    {
        return $this->oltp()->table('question_semantic_mapping')
            ->where('questionnaire_id', $questionnaireId)
            ->where('semantic_role', $semanticRole)
            ->where('is_active', true)
            ->first();
    }

    public function insertMapping(array $data): int
    {
        return $this->oltp()->table('question_semantic_mapping')->insertGetId(array_merge($data, [
            'created_at' => now(),
            'updated_at' => now(),
        ]));
    }

    public function deactivateMapping(int $id, ?int $userId): bool
    {
        return $this->oltp()->table('question_semantic_mapping')->where('id', $id)->update([
            'is_active'      => false,
            'deactivated_at' => now(),
            'deactivated_by' => $userId,
            'updated_at'     => now(),
        ]) > 0;
    }

    /**
     * Kode pertanyaan di questionnaire_questions untuk questionnaire ini
     * yang TIDAK punya baris aktif di question_semantic_mapping -- dipakai
     * endpoint /unmapped. Sample jawaban (up to 5) diambil terpisah per
     * kode oleh caller supaya query ini tetap ringan.
     */
    public function getUnmappedQuestionsForQuestionnaire(int $questionnaireId): Collection
    {
        return $this->oltp()->table('questionnaire_questions as qq')
            ->where('qq.questionnaire_id', $questionnaireId)
            ->whereNotIn('qq.code', function ($sub) use ($questionnaireId) {
                $sub->select('question_code')
                    ->from('question_semantic_mapping')
                    ->where('questionnaire_id', $questionnaireId)
                    ->where('is_active', true);
            })
            ->select(['qq.code as question_code', 'qq.question_text', 'qq.question_type'])
            ->orderBy('qq.order_no')
            ->get();
    }

    /** Sample jawaban terkini (distinct, up to $limit) untuk satu question_code -- dipakai /unmapped dan type-check POST. */
    public function getSampleAnswers(int $questionnaireId, string $questionCode, int $limit = 5): array
    {
        return $this->oltp()->table('response_answers as ra')
            ->join('responses as r', 'r.id', '=', 'ra.response_id')
            ->where('r.questionnaire_id', $questionnaireId)
            ->where('ra.question_code', $questionCode)
            ->whereNotNull('ra.answer_text')
            ->where('ra.answer_text', '!=', '')
            ->orderByDesc('ra.id')
            ->limit(50) // ambil lebih banyak dulu supaya distinct() punya variasi untuk dipilih
            ->pluck('ra.answer_text')
            ->unique()
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * Constraint D helper: pertanyaan lain di questionnaire yang sama dengan
     * teks mirip (pg_trgm similarity), dipakai memperingatkan admin sebelum
     * memetakan kode yang mungkin duplikat makna. Caller (Service) yang
     * bertanggung jawab memastikan extension pg_trgm sudah terpasang.
     *
     * similarity() DI-QUALIFY "public." secara eksplisit -- CREATE EXTENSION
     * pg_trgm menaruh fungsinya di schema public (default session psql saat
     * bootstrap, lihat database/dump/004_pg_trgm.sql), sedangkan koneksi
     * 'oltp' punya search_path='tracer_oltp' SAJA (TIDAK termasuk public,
     * lihat config/database.php) -- tanpa qualifier ini query akan gagal
     * dengan "function similarity(text, text) does not exist".
     */
    public function findSimilarQuestions(int $questionnaireId, string $questionText, ?string $excludeCode, float $threshold = 0.3, int $limit = 5): Collection
    {
        return $this->oltp()->table('questionnaire_questions as qq')
            ->selectRaw('qq.code as question_code, qq.question_text, public.similarity(qq.question_text, ?) as similarity', [$questionText])
            ->where('qq.questionnaire_id', $questionnaireId)
            ->whereRaw('public.similarity(qq.question_text, ?) >= ?', [$questionText, $threshold])
            ->when($excludeCode !== null, fn ($query) => $query->where('qq.code', '!=', $excludeCode))
            ->orderByRaw('public.similarity(qq.question_text, ?) DESC', [$questionText])
            ->limit($limit)
            ->get();
    }

    /** Role aktif yang sedang dipegang question_code ini di questionnaire ini, kalau ada -- dipakai render kandidat similar. */
    public function currentRoleForCode(int $questionnaireId, string $questionCode): ?string
    {
        return $this->oltp()->table('question_semantic_mapping')
            ->where('questionnaire_id', $questionnaireId)
            ->where('question_code', $questionCode)
            ->where('is_active', true)
            ->value('semantic_role');
    }

    public function findRole(string $roleKey): ?object
    {
        return $this->oltp()->table('semantic_role_registry')->where('role_key', $roleKey)->first();
    }

    /**
     * Pasangan (option_code, option_label) NYATA dari questionnaire_options,
     * bersumber dari question_code aktif yang sedang memegang role ini.
     * WAJIB dipakai sebagai satu-satunya sumber option_code saat admin
     * mengelompokkan status baru ke kategori KPI (Langkah 2 UI) -- option_code
     * di kpi_category_mapping HARUS sama persis dengan SPLIT_PART(id_status_alumni,
     * ':', 3) yang dibaca Cube.js, jadi tidak boleh dikarang (mis. slug dari
     * label) atau kategorisasi itu akan tersimpan tapi tidak pernah match
     * apapun saat query -- gagal senyap, persis kelas bug yang ingin dicegah
     * fitur ini.
     */
    public function getOptionCandidatesForRole(string $roleKey): Collection
    {
        return $this->oltp()->table('question_semantic_mapping as qsm')
            ->join('questionnaire_questions as qq', function ($join) {
                $join->on('qq.questionnaire_id', '=', 'qsm.questionnaire_id')
                     ->on('qq.code', '=', 'qsm.question_code');
            })
            ->join('questionnaire_options as qo', 'qo.question_id', '=', 'qq.id')
            ->where('qsm.semantic_role', $roleKey)
            ->where('qsm.is_active', true)
            ->select(['qo.option_code', 'qo.option_label'])
            ->distinct()
            ->orderBy('qo.option_code')
            ->get();
    }

    /** Snapshot teks pertanyaan pada saat mapping dibuat -- disimpan di question_text_snapshot. */
    public function findQuestionText(int $questionnaireId, string $questionCode): ?string
    {
        return $this->oltp()->table('questionnaire_questions')
            ->where('questionnaire_id', $questionnaireId)
            ->where('code', $questionCode)
            ->value('question_text');
    }
}
