<?php
namespace App\Services\Transactional;

use App\Exceptions\BusinessException;
use App\Repositories\ETL\SemanticMappingRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Orkestrasi admin API untuk semantic_role_registry + question_semantic_mapping.
 * Ini SISI ADMIN (constraint A/B/type-check + force_replace) -- terpisah
 * dari pemakaian runtime ETL yang ada di ETL\SemanticMappingRepository
 * (dipanggil dim service / AlumniFactBuilderService), walau sama-sama
 * lewat repository yang sama.
 */
class QuestionSemanticMappingService
{
    public function __construct(
        private readonly SemanticMappingRepository $repo,
    ) {}

    public function listRoles(): Collection
    {
        return $this->repo->listRoles();
    }

    /** Kuesioner nasional untuk selector Langkah 1/2 -- lihat catatan repository. */
    public function listQuestionnaires(): Collection
    {
        return $this->repo->listNationalQuestionnaires();
    }

    public function listMappings(?int $questionnaireId, ?bool $isActive): Collection
    {
        return $this->repo->listMappings($questionnaireId, $isActive);
    }

    /**
     * Kode yang punya pertanyaan di questionnaire ini tapi belum punya
     * mapping aktif, plus sample jawaban terkini + saran role naif
     * (keyword match, best-effort, TIDAK PERNAH auto-apply).
     */
    public function unmapped(int $questionnaireId): array
    {
        $questions = $this->repo->getUnmappedQuestionsForQuestionnaire($questionnaireId);
        $roles = $this->repo->listRoles();

        return $questions->map(function ($q) use ($questionnaireId, $roles) {
            return [
                'question_code'  => $q->question_code,
                'question_text'  => $q->question_text,
                'question_type'  => $q->question_type,
                'sample_answers' => $this->repo->getSampleAnswers($questionnaireId, $q->question_code, 5),
                'suggested_role' => $this->suggestRole($q->question_text, $roles),
            ];
        })->values()->all();
    }

    /**
     * Constraint D helper: pertanyaan lain di questionnaire yang sama
     * dengan teks mirip (pg_trgm similarity). Extension pg_trgm sudah
     * dipastikan terpasang lewat database/dump/004_pg_trgm.sql (dijalankan
     * di DatabaseSeeder, additive/idempotent).
     */
    public function similar(int $questionnaireId, string $questionText, ?string $excludeCode): array
    {
        $rows = $this->repo->findSimilarQuestions($questionnaireId, $questionText, $excludeCode);

        return $rows->map(fn ($r) => [
            'question_code'        => $r->question_code,
            'question_text'        => $r->question_text,
            'similarity'           => round((float) $r->similarity, 3),
            'current_semantic_role' => $this->repo->currentRoleForCode($questionnaireId, $r->question_code),
        ])->values()->all();
    }

    /**
     * @throws BusinessException dengan code 409 (role_already_mapped /
     *         code_already_mapped) atau 422 (type_mismatch) -- controller
     *         menangkap dan meneruskan payload detailnya ke response.
     */
    public function store(array $data, ?int $userId): array
    {
        $questionnaireId = (int) $data['questionnaire_id'];
        $questionCode = $data['question_code'];
        $semanticRole = $data['semantic_role'];
        $forceReplace = (bool) ($data['force_replace'] ?? false);

        $roleRow = $this->repo->findRole($semanticRole);
        if ($roleRow === null) {
            throw new BusinessException('Semantic role tidak ditemukan.', 404);
        }

        // ── Constraint B: kode ini SUDAH aktif dipetakan (ke role apapun,
        // termasuk role yang sama) -- HARD block, TIDAK ADA force option.
        // "satu kode cuma boleh berarti satu hal". ──
        $codeConflict = $this->repo->findActiveByCode($questionnaireId, $questionCode);
        if ($codeConflict !== null) {
            throw BusinessException::withPayload(
                "Kode pertanyaan '{$questionCode}' sudah dipetakan ke role '{$codeConflict->semantic_role}'.",
                409,
                [
                    'error' => 'code_already_mapped',
                    'conflicting_mapping' => [
                        'id' => $codeConflict->id,
                        'question_code' => $codeConflict->question_code,
                        'semantic_role' => $codeConflict->semantic_role,
                        'question_text_snapshot' => $codeConflict->question_text_snapshot,
                    ],
                ]
            );
        }

        // ── Constraint A: role narrow ini SUDAH dipegang question_code lain
        // di questionnaire yang sama -- SOFT block, bisa force_replace. ──
        $roleConflict = null;
        if ($roleRow->grain === 'narrow') {
            $roleConflict = $this->repo->findActiveByRole($questionnaireId, $semanticRole);

            if ($roleConflict !== null && !$forceReplace) {
                throw BusinessException::withPayload(
                    "Role '{$semanticRole}' sudah dipegang kode '{$roleConflict->question_code}' di kuesioner ini.",
                    409,
                    [
                        'error' => 'role_already_mapped',
                        'conflicting_mapping' => [
                            'id' => $roleConflict->id,
                            'question_code' => $roleConflict->question_code,
                            'question_text_snapshot' => $roleConflict->question_text_snapshot,
                        ],
                    ]
                );
            }
        }

        // ── Type-compatibility check: sample jawaban vs expected_kind. ──
        // Hanya role integer/decimal yang punya sinyal jelas "pasti salah"
        // (semua sample non-numeric) -- categorical/text/boolean/date tidak
        // divalidasi lebih jauh dari sekadar ada isinya (sama posisi dengan
        // validator ETL di AlumniFactBuilderService::passesRoleValidation()).
        $samples = $this->repo->getSampleAnswers($questionnaireId, $questionCode, 5);
        if (!empty($samples) && in_array($roleRow->expected_kind, ['integer', 'decimal'], true)) {
            $allNonNumeric = collect($samples)->every(fn ($s) => !is_numeric($s));
            if ($allNonNumeric) {
                throw BusinessException::withPayload(
                    "Sample jawaban kode '{$questionCode}' semuanya bukan angka, tidak cocok dengan role '{$semanticRole}' (expected_kind={$roleRow->expected_kind}).",
                    422,
                    ['error' => 'type_mismatch', 'expected_kind' => $roleRow->expected_kind, 'samples' => $samples]
                );
            }
        }

        $questionText = $this->repo->findQuestionText($questionnaireId, $questionCode);

        $newId = DB::connection('oltp')->transaction(function () use (
            $roleConflict, $forceReplace, $questionnaireId, $questionCode, $semanticRole, $roleRow, $questionText, $userId
        ) {
            if ($roleConflict !== null && $forceReplace) {
                $this->repo->deactivateMapping($roleConflict->id, $userId);
            }

            return $this->repo->insertMapping([
                'questionnaire_id'       => $questionnaireId,
                'question_code'          => $questionCode,
                'question_text_snapshot' => $questionText,
                'semantic_role'          => $semanticRole,
                'grain'                  => $roleRow->grain,
                'effective_date'         => now()->toDateString(),
                'is_active'              => true,
                'mapped_by'              => $userId,
            ]);
        });

        return (array) $this->repo->findMappingById($newId);
    }

    public function deactivate(int $id, ?int $userId): void
    {
        $row = $this->repo->findMappingById($id);
        if ($row === null) {
            throw new BusinessException('Mapping tidak ditemukan.', 404);
        }

        $this->repo->deactivateMapping($id, $userId);
    }

    /**
     * Kandidat (option_code, option_label) NYATA untuk role ini -- dipakai
     * Langkah 2 UI supaya admin memilih dari opsi asli OLTP, bukan mengarang
     * option_code dari label (lihat catatan di repository).
     *
     * @throws BusinessException 404 kalau role tidak ada.
     */
    public function optionCandidates(string $roleKey): array
    {
        if ($this->repo->findRole($roleKey) === null) {
            throw new BusinessException('Semantic role tidak ditemukan.', 404);
        }

        return $this->repo->getOptionCandidatesForRole($roleKey)
            ->map(fn ($r) => ['option_code' => $r->option_code, 'option_label' => $r->option_label])
            ->values()->all();
    }

    /** Naive keyword match: jumlah kata question_text yang muncul di label/description role, role dengan skor tertinggi menang (>0). */
    private function suggestRole(string $questionText, Collection $roles): ?string
    {
        $words = collect(preg_split('/\s+/', mb_strtolower($questionText)))
            ->filter(fn ($w) => mb_strlen($w) >= 4) // buang kata sambung pendek
            ->unique();

        if ($words->isEmpty()) {
            return null;
        }

        $best = null;
        $bestScore = 0;

        foreach ($roles as $role) {
            $haystack = mb_strtolower($role->label . ' ' . ($role->description ?? ''));
            $score = $words->filter(fn ($w) => str_contains($haystack, $w))->count();

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $role->role_key;
            }
        }

        return $bestScore > 0 ? $best : null;
    }
}
