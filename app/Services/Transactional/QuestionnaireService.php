<?php
// app/Services/Transactional/QuestionnaireService.php
namespace App\Services\Transactional;

use App\Exceptions\BusinessException;
use App\Models\Transactional\User;
use App\Repositories\Transactional\ProgramRepository;
use App\Repositories\Transactional\QuestionnaireRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * QuestionnaireService — CRUD form builder + assembling response untuk admin panel.
 *
 * Tanggung jawab:
 *   - Orchestrate create/update (wrap di DB transaction, sync sections)
 *   - Role-based filtering saat list (kaprodi scope)
 *   - Business rule: tidak boleh hapus kuesioner yang sudah ada respondennya
 *   - Mapping tipe pertanyaan FE ↔ DB
 *   - Assembling struktur nested untuk response
 */
class QuestionnaireService
{
    private const CONN = 'oltp';

    public function __construct(
        private readonly QuestionnaireRepository $questionnaireRepo,
        private readonly ProgramRepository       $programRepo,
    ) {}

    // ═══════════════════════════════════════════════════════════
    // LIST — semua kuesioner (role-scoped)
    // ═══════════════════════════════════════════════════════════
    public function list(?User $user): array
    {
        $programId = ($user && $user->isKaprodi()) ? $user->program_id : null;
        $jurusan = ($user && $user->isKajur()) ? $user->jurusan : null;

        $rows = $this->questionnaireRepo->listHeaders($programId);
        $responseCounts = $this->questionnaireRepo->countResponsesGroupedAll($programId, $jurusan);

        return $rows->map(function ($row) use ($responseCounts) {
            $questionnaire = $this->loadQuestionnaire((int) $row->id);
            if ($questionnaire) {
                $questionnaire['response_count'] = (int) ($responseCounts[$row->id] ?? 0);
            }
            return $questionnaire;
        })->values()->toArray();
    }

    /**
     * Paginated list with filters.
     */
    public function listPaginated(?User $user, array $filters = [], int $perPage = 100): array
    {
        $programId = ($user && $user->isKaprodi()) ? $user->program_id : null;
        $jurusan = ($user && $user->isKajur()) ? $user->jurusan : null;

        $paginator = $this->questionnaireRepo->paginateHeaders($programId, $filters, $perPage);
        $responseCounts = $this->questionnaireRepo->countResponsesGroupedAll($programId, $jurusan);

        $items = collect($paginator->items())->map(function ($row) use ($responseCounts) {
            $questionnaire = $this->loadQuestionnaire((int) $row->id);
            if ($questionnaire) {
                $questionnaire['response_count'] = (int) ($responseCounts[$row->id] ?? 0);
            }
            return $questionnaire;
        })->filter()->values()->toArray();

        return [
            'data'         => $items,
            'current_page' => $paginator->currentPage(),
            'last_page'    => $paginator->lastPage(),
            'per_page'     => $paginator->perPage(),
            'total'        => $paginator->total(),
        ];
    }

    // ═══════════════════════════════════════════════════════════
    // SHOW — detail 1 kuesioner
    // ═══════════════════════════════════════════════════════════
    public function show(int $id): array
    {
        $questionnaire = $this->loadQuestionnaire($id);
        if (!$questionnaire) {
            throw new BusinessException('Kuisioner tidak ditemukan.', 404);
        }
        $questionnaire['response_count'] = $this->questionnaireRepo->countResponses($id);
        return $questionnaire;
    }

    // ═══════════════════════════════════════════════════════════
    // CREATE — wrap transaction di service
    // ═══════════════════════════════════════════════════════════
    public function create(array $validated): array
    {
        $now = Carbon::now();

        $id = DB::connection(self::CONN)->transaction(function () use ($validated, $now) {
            $programId = $this->resolveProgramId($validated);
            $baseCode  = $validated['code']
                ?? Str::slug($validated['title']) . '-' . ($validated['period_year'] ?? (int) $now->format('Y'));
            $version   = $validated['version'] ?? $this->questionnaireRepo->nextVersionForCode($baseCode);

            $id = $this->questionnaireRepo->insertHeader([
                'code'                    => $baseCode,
                'title'                   => $validated['title'],
                'description'             => $validated['description'] ?? null,
                'target'                  => $validated['target'] ?? null,
                'sample_respondents'      => isset($validated['respondents'])
                    ? json_encode(array_values($validated['respondents']))
                    : null,
                'period_year'             => (int) ($validated['period_year'] ?? (int) $now->format('Y')),
                'target_graduation_years' => $this->encodeGraduationYears($validated),
                'version'                 => $version,
                'status'                  => $validated['status'],
                'program_id'              => $programId,
                'published_at'            => $validated['status'] === 'published' ? $now : null,
                'created_by'              => auth()->id(),
            ]);

            $this->syncSections($id, $validated['sections'], $now);

            return $id;
        });

        return $this->loadQuestionnaire($id);
    }

    // ═══════════════════════════════════════════════════════════
    // UPDATE — wrap transaction
    // ═══════════════════════════════════════════════════════════
    public function update(int $id, array $validated): array
    {
        $now = Carbon::now();

        DB::connection(self::CONN)->transaction(function () use ($id, $validated, $now) {
            $existing = $this->questionnaireRepo->findHeaderById($id);
            if (!$existing) {
                throw new BusinessException('Kuisioner tidak ditemukan.', 404);
            }

            // KSN-10 — kuesioner yang sudah dijawab tidak boleh disunting lagi.
            //
            // Penyuntingan di sini menghapus SELURUH bagian dan pertanyaan lalu
            // menyisipkannya ulang (lihat deleteSectionsAndQuestions di bawah).
            // Untuk kuesioner kosong itu tidak berbahaya, tapi begitu ada
            // jawaban, jawaban itu menunjuk kode pertanyaan yang bisa berubah
            // arti, bergeser urutannya, atau lenyap sama sekali — dan tidak ada
            // jejak yang tersisa untuk menyadarinya. Menonaktifkannya pun
            // ditolak: alumni yang draf-nya sedang berjalan akan kehilangan
            // borangnya di tengah pengisian.
            //
            // Aturan ini sejajar dengan delete(), yang sudah lebih dulu menolak
            // kuesioner ber-responden.
            $responseCount = $this->questionnaireRepo->countResponses($id);
            if ($responseCount > 0) {
                throw new BusinessException(
                    "Kuisioner tidak dapat diubah karena sudah memiliki {$responseCount} responden. "
                    . 'Buat versi baru bila pertanyaannya perlu berubah.',
                    422,
                );
            }

            $programId = $this->resolveProgramId($validated, $existing->program_id);
            $code      = $validated['code']    ?? $existing->code;
            $version   = $validated['version'] ?? $existing->version;

            $this->questionnaireRepo->updateHeader($id, [
                'code'                    => $code,
                'title'                   => $validated['title'],
                'description'             => $validated['description'] ?? null,
                'target'                  => $validated['target'] ?? null,
                'sample_respondents'      => isset($validated['respondents'])
                    ? json_encode(array_values($validated['respondents']))
                    : null,
                'period_year'             => (int) ($validated['period_year'] ?? $existing->period_year),
                'target_graduation_years' => $this->encodeGraduationYears($validated, $existing->target_graduation_years ?? null),
                'version'                 => $version,
                'status'                  => $validated['status'],
                'program_id'              => $programId,
                'published_at'            => $validated['status'] === 'published'
                    ? ($existing->published_at ?? $now)
                    : $existing->published_at,
            ]);

            // Metadata lama dipungut SEBELUM pertanyaannya dihapus. Penyuntingan
            // memang menghapus lalu menyisipkan ulang seluruh pertanyaan, dan
            // tanpa langkah ini setiap penyimpanan membuang kunci metadata yang
            // tidak dikenal borang penyunting -- option_hints, divider_label,
            // hint, dan seterusnya. Dicocokkan lewat kode pertanyaan, satu-
            // satunya penanda yang bertahan melewati hapus-buat-ulang.
            $preserved = $this->collectExistingMetadata($id);

            $this->questionnaireRepo->deleteSectionsAndQuestions($id);
            $this->syncSections($id, $validated['sections'], $now, $preserved);
        });

        return $this->loadQuestionnaire($id);
    }

    // ═══════════════════════════════════════════════════════════
    // DELETE — blocked if has responses
    // ═══════════════════════════════════════════════════════════
    public function delete(int $id): void
    {
        $responseCount = $this->questionnaireRepo->countResponses($id);
        if ($responseCount > 0) {
            throw new BusinessException(
                "Kuisioner tidak dapat dihapus karena sudah memiliki {$responseCount} responden.",
                422,
            );
        }

        $deleted = $this->questionnaireRepo->deleteHeader($id);
        if (!$deleted) {
            throw new BusinessException('Kuisioner tidak ditemukan.', 404);
        }
    }

    // ═══════════════════════════════════════════════════════════
    // PRIVATE HELPERS (dipindah dari controller lama)
    // ═══════════════════════════════════════════════════════════

    /** Resolve program_id dari input (program_id langsung atau program_code). */
    private function resolveProgramId(array $validated, ?int $fallback = null): ?int
    {
        if (!empty($validated['program_id'])) {
            return (int) $validated['program_id'];
        }
        if (!empty($validated['program_code'])) {
            return $this->programRepo->findByCode($validated['program_code'])?->id;
        }
        return $fallback;
    }

    /**
     * Encode list tahun lulusan target ke JSON untuk disimpan di kolom jsonb.
     * Empty array → null (artinya "tidak ada filter / berlaku semua alumni").
     * Backward compat: kalau payload tidak ada key, pakai $fallback existing.
     */
    private function encodeGraduationYears(array $validated, ?string $fallback = null): ?string
    {
        if (!array_key_exists('target_graduation_years', $validated)) {
            return $fallback;
        }

        $years = $validated['target_graduation_years'];
        if (!is_array($years) || empty($years)) {
            return null;
        }

        $clean = collect($years)
            ->map(fn ($y) => (int) $y)
            ->filter(fn ($y) => $y > 1900 && $y < 2200)
            ->unique()
            ->sort()
            ->values()
            ->toArray();

        return empty($clean) ? null : json_encode($clean);
    }

    /**
     * Metadata pertanyaan yang tersimpan sekarang, berkunci kode pertanyaan.
     *
     * @return array<string,array<string,mixed>>
     */
    private function collectExistingMetadata(int $questionnaireId): array
    {
        return $this->questionnaireRepo
            ->getQuestionsByQuestionnaireId($questionnaireId)
            ->reduce(function (array $carry, object $q): array {
                $meta = $q->metadata ? json_decode($q->metadata, true) : null;
                if (is_array($meta) && $meta !== []) {
                    $carry[$q->code] = $meta;
                }

                return $carry;
            }, []);
    }

    /**
     * Insert semua section + question + option untuk 1 kuesioner.
     *
     * @param array<string,array<string,mixed>> $preservedMetadata metadata lama
     *        berkunci kode pertanyaan; kosong saat membuat kuesioner baru.
     */
    private function syncSections(int $questionnaireId, array $sections, Carbon $now, array $preservedMetadata = []): void
    {
        // Peta label -> kode opsi per pertanyaan, dipakai menormalkan syarat
        // tampil. Disusun lebih dulu karena pertanyaan pemicu bisa berada di
        // bagian mana pun, termasuk sesudah pertanyaan yang merujuknya.
        $optionCodeByLabel = [];
        // Peta label -> kode pertanyaan untuk deret boolean yang di borang
        // penyunting tampil sebagai SATU daftar centang. Pertanyaan yang
        // merujuknya menyimpan kode kelompok (mis. q16_cara_cari_kerja),
        // padahal yang ada di basis data adalah pertanyaan individualnya
        // (f415). Tanpa terjemahan ini, "Sebutkan cara lainnya" tidak akan
        // pernah muncul betapa pun alumni mencentang "Lainnya".
        $groupLabelToCode = [];
        foreach ($sections as $sectionData) {
            foreach ($sectionData['questions'] ?? [] as $questionData) {
                $questionCode = $questionData['code'] ?? null;
                if (!$questionCode) continue;

                foreach ($questionData['options'] ?? [] as $optionIndex => $optionData) {
                    if (!is_array($optionData)) continue;
                    $label = trim((string) ($optionData['label'] ?? ''));
                    $optionCode = $optionData['code'] ?: 'opt_' . ($optionIndex + 1);
                    if ($label !== '') $optionCodeByLabel[$questionCode][$label] = $optionCode;
                }

                $groupCode  = $questionData['group_code'] ?? null;
                $groupLabel = trim((string) ($questionData['group_label'] ?? ''));
                if ($groupCode && $groupLabel !== '') {
                    $groupLabelToCode[$groupCode][$groupLabel] = $questionCode;
                }
            }
        }

        foreach (array_values($sections) as $sectionIndex => $sectionData) {
            $sectionId = $this->questionnaireRepo->insertSection([
                'questionnaire_id' => $questionnaireId,
                'title'            => $sectionData['title'],
                'description'      => $sectionData['description'] ?? null,
                'order_no'         => (int) ($sectionData['order_no'] ?? ($sectionIndex + 1)),
                'is_active'        => true,
            ]);

            foreach (array_values($sectionData['questions']) as $questionIndex => $questionData) {
                // Determine DB type — override to 'boolean' if group_code present (grouped boolean from template)
                $dbType = $this->mapQuestionTypeToDatabase($questionData['type']);
                if (!empty($questionData['group_code'])) {
                    $dbType = 'boolean';
                }

                $code = $questionData['code'] ?: Str::slug($questionData['question']) . '-' . ($questionIndex + 1);

                $questionId = $this->questionnaireRepo->insertQuestion([
                    'questionnaire_id' => $questionnaireId,
                    'section_id'       => $sectionId,
                    'code'             => $code,
                    'question_text'    => $questionData['question'],
                    'question_type'    => $dbType,
                    'is_required'      => (bool) ($questionData['required'] ?? false),
                    'order_no'         => (int) ($questionData['order_no'] ?? ($questionIndex + 1)),
                    'metadata'         => json_encode($this->buildQuestionMetadata(
                        $questionData,
                        $preservedMetadata[$code] ?? [],
                        $optionCodeByLabel,
                        $groupLabelToCode,
                    )),
                ]);

                foreach (array_values($questionData['options'] ?? []) as $optionIndex => $optionData) {
                    $label = is_array($optionData) ? ($optionData['label'] ?? '') : (string) $optionData;
                    $value = is_array($optionData) ? ($optionData['value'] ?? null) : null;
                    $code  = is_array($optionData) && !empty($optionData['code'])
                        ? $optionData['code']
                        : 'opt_' . ($optionIndex + 1);

                    $this->questionnaireRepo->insertOption([
                        'question_id'  => $questionId,
                        'option_code'  => $code,
                        'option_label' => $label,
                        'option_value' => $value,
                        'order_no'     => (int) ($optionData['order_no'] ?? ($optionIndex + 1)),
                        'is_active'    => true,
                        'is_hidden'    => (bool) ($optionData['is_hidden'] ?? false),
                    ]);
                }
            }
        }
    }

    private function mapQuestionTypeToDatabase(string $frontendType): string
    {
        return match ($frontendType) {
            'short'           => 'short_text',
            'paragraph'       => 'long_text',
            'multiple_choice' => 'single_choice',
            'checkbox'        => 'multiple_choice',
            'dropdown'        => 'single_choice',
            // Isian referensi tetap short_text: nilainya kunci baris tabel
            // master, bukan option_code, jadi tidak punya questionnaire_options
            // untuk disandarkan single_choice.
            'lookup'          => 'short_text',
            'number'          => 'number',
            'linear_scale'    => 'number',
            'rating'          => 'number',
            'boolean'         => 'boolean',
            'date'            => 'date',
            'time'            => 'short_text',
            default           => 'short_text',
        };
    }

    private function mapQuestionTypeToFrontend(string $dbType, array $metadata = []): string
    {
        // number with scale metadata → linear_scale (not short)
        if ($dbType === 'number' && (isset($metadata['scale_min']) || isset($metadata['scaleMin']))) {
            return 'linear_scale';
        }

        return match ($dbType) {
            'short_text'      => 'short',
            'long_text'       => 'paragraph',
            'single_choice'   => 'multiple_choice',
            'multiple_choice' => 'checkbox',
            // Isian angka punya jenisnya sendiri di borang penyunting. Dulu
            // dipetakan ke 'short', sehingga pertanyaan angka bawaan seperti
            // f502 dan f505 terbuka sebagai isian teks biasa — dan kuesioner
            // baru tidak punya cara membuat isian angka sama sekali.
            'number'          => 'number',
            'date'            => 'date',
            'boolean'         => 'multiple_choice',
            default           => 'short',
        };
    }

    /**
     * Kunci metadata yang boleh disunting Tim Tracer lewat borang penyunting.
     *
     * Bertindak sebagai daftar-boleh, bukan sekadar dokumentasi: metadata yang
     * datang dari peramban hanya diterima kalau kuncinya tersebut di sini.
     * Menerima JSON apa adanya berarti siapa pun yang bisa membuka penyunting
     * dapat menyelipkan show_if atau kunci lain yang tidak pernah ditampilkan
     * di layar.
     *
     * option_hints ikut karena dipakai salinan-dari-template: keterangan per
     * opsi harus terbawa walau borang penyunting belum bisa menyuntingnya.
     */
    private const EDITABLE_METADATA_KEYS = [
        'hint', 'description', 'format', 'divider_label', 'option_hints',
        'warn_min', 'warn_max',
        // Metadata semantik ETL. Tidak tampil di layar mana pun, tapi
        // IndikatorEvaluasiDimService memakainya untuk memberi nama indikator
        // kompetensi dan metode pembelajaran di dashboard. Ikut daftar ini
        // supaya salinan-dari-template tidak memutus sambungan itu — pada
        // kuesioner baru tidak ada baris lama yang bisa melestarikannya.
        'competency', 'dimension', 'method',
    ];

    /** Nilai yang sah untuk metadata `format`. */
    private const ALLOWED_FORMATS = ['email', 'phone', 'url', 'currency'];

    /**
     * Kunci yang selalu disusun ulang dari data borang penyunting.
     *
     * Dibedakan dari kunci lain karena keberadaannya bermakna: pertanyaan yang
     * tidak lagi berjenis skala harus KEHILANGAN scale_min, bukan mewarisinya
     * dari metadata lama. Kunci di luar daftar ini dan daftar sunting di atas
     * dibiarkan lewat apa adanya -- itulah yang menyelamatkan metadata yang
     * belum dikenal borang penyunting.
     */
    private const MANAGED_METADATA_KEYS = [
        'original_type', 'allowOther',
        'scale_min', 'scale_max', 'scale_labels',
        'gridRows', 'gridColumns',
        'lookup', 'lookup_value', 'depends_on',
        'group_code',
        'show_if',
    ];

    /**
     * Susun metadata pertanyaan dari tiga sumber.
     *
     * 1. Metadata lama di basis data ($existing) — lewat apa adanya, kecuali
     *    kunci terkelola yang disusun ulang di bawah. Inilah yang menjaga
     *    option_hints dan kunci masa depan tetap hidup melewati penyimpanan.
     * 2. Kunci yang boleh disunting, dari borang penyunting — dipakai juga
     *    saat membuat kuesioner baru dari template, ketika $existing kosong
     *    karena barisnya memang belum ada.
     * 3. Kunci terkelola, selalu dihitung ulang dari bentuk pertanyaannya.
     *
     * @param array<string,mixed> $existing metadata pertanyaan berkode sama
     */
    private function buildQuestionMetadata(
        array $questionData,
        array $existing = [],
        array $optionCodeByLabel = [],
        array $groupLabelToCode = [],
    ): array {
        // Mulai dari yang lama, lalu buang seluruh kunci terkelola. Yang
        // tersisa adalah metadata yang tidak diurus borang penyunting.
        $metadata = $existing;
        foreach (self::MANAGED_METADATA_KEYS as $key) {
            unset($metadata[$key]);
        }

        foreach (self::EDITABLE_METADATA_KEYS as $key) {
            if (!array_key_exists($key, $questionData)) {
                continue;
            }

            $value = $questionData[$key];

            // Dikosongkan pengguna berarti dibuang, bukan disimpan sebagai
            // string kosong yang lalu dirender sebagai keterangan hampa.
            if ($value === null || $value === '' || $value === []) {
                unset($metadata[$key]);
                continue;
            }

            if ($key === 'format') {
                $value = is_string($value) ? strtolower(trim($value)) : '';
                if (!in_array($value, self::ALLOWED_FORMATS, true)) {
                    unset($metadata[$key]);
                    continue;
                }
            } elseif ($key === 'warn_min' || $key === 'warn_max') {
                if (!is_numeric($value)) {
                    unset($metadata[$key]);
                    continue;
                }
                $value = (float) $value;
            } elseif ($key === 'option_hints') {
                if (!is_array($value)) {
                    unset($metadata[$key]);
                    continue;
                }
                $value = array_map(fn ($v) => mb_substr((string) $v, 0, 300), $value);
            } else {
                $value = mb_substr(trim((string) $value), 0, 300);
                if ($value === '') {
                    unset($metadata[$key]);
                    continue;
                }
            }

            $metadata[$key] = $value;
        }

        $metadata['original_type'] = $questionData['type'];
        $metadata['allowOther']    = $questionData['allowOther'] ?? false;

        // Scale metadata — store as snake_case for consistency with seeder.
        //
        // HANYA untuk pertanyaan yang memang berskala. Borang penyunting selalu
        // mengirim scaleMin/scaleMax (bawaannya 1 dan 5) untuk setiap
        // pertanyaan, jadi tanpa penjagaan ini menyimpan kuesioner akan
        // menempelkan skala 1-5 pada isian angka biasa. Akibatnya nyata:
        // pertanyaan pendapatan f505 akan menolak jawaban di luar 1-5, baik di
        // peramban (validateAnswer membaca scale_min) maupun di server
        // (buildDynamicRules menambah aturan between).
        $isScale = in_array($questionData['type'] ?? '', ['linear_scale', 'rating'], true);

        if ($isScale) {
            if (isset($questionData['scaleMin'])) {
                $metadata['scale_min'] = $questionData['scaleMin'];
            }
            if (isset($questionData['scaleMax'])) {
                $metadata['scale_max'] = $questionData['scaleMax'];
            }
            if (!empty($questionData['scaleLabels'])) {
                $metadata['scale_labels'] = $questionData['scaleLabels'];
            }
        }

        foreach (['gridRows', 'gridColumns'] as $key) {
            if (!empty($questionData[$key])) {
                $metadata[$key] = array_values($questionData[$key]);
            }
        }

        // Isian referensi: sumber tabelnya, kolom mana yang disimpan, dan
        // pertanyaan induk yang menyaring pilihan (kab/kota mengikuti
        // provinsi). Perender borang membaca ketiganya untuk memutuskan
        // menampilkan daftar referensi alih-alih kotak ketik.
        if (($questionData['type'] ?? '') === 'lookup' && !empty($questionData['lookup'])) {
            $metadata['lookup'] = $questionData['lookup'];

            // Default 'id' mengikuti kebutuhan AnswerResolverService untuk
            // wilayah; Kode Prodi memakai 'code' demi ekspor Kemdikbud.
            $metadata['lookup_value'] = ($questionData['lookupValue'] ?? 'id') === 'code' ? 'code' : 'id';

            if (!empty($questionData['dependsOn'])) {
                $metadata['depends_on'] = $questionData['dependsOn'];
            }
        }

        // Metadata pertanyaan berkelompok (deret boolean yang dirender sebagai
        // satu daftar centang).
        //
        // group_code menentukan keanggotaan kelompok, jadi ia yang memutuskan:
        // tanpa group_code, ketiganya dibuang. Sebaliknya group_label dan
        // group_title DIPERTAHANKAN kalau pengirimnya tidak menyebutkannya
        // sama sekali — keduanya tidak punya isian di borang penyunting,
        // sehingga payload yang tidak menyertakannya berarti "tidak diubah",
        // bukan "dikosongkan". Tanpa perbedaan itu, satu penyimpanan cukup
        // untuk mengubah daftar centang menjadi lima belas baris berisi teks
        // pertanyaan yang berulang.
        if (!empty($questionData['group_code'])) {
            $metadata['group_code'] = $questionData['group_code'];

            foreach (['group_label', 'group_title'] as $key) {
                if (array_key_exists($key, $questionData)) {
                    if (!empty($questionData[$key])) {
                        $metadata[$key] = $questionData[$key];
                    } else {
                        unset($metadata[$key]);
                    }
                }
            }
        } else {
            unset($metadata['group_code'], $metadata['group_label'], $metadata['group_title']);
        }

        // Simpan pertanyaan bersyarat (logic) sebagai show_if di metadata.
        //
        // Nilainya SELALU disimpan sebagai kode opsi, tidak pernah sebagai
        // label. Borang penyunting bekerja dengan label karena itu yang dibaca
        // manusia, tapi label bisa disunting kapan saja — begitu redaksinya
        // diperbaiki, syarat yang menyimpan label berhenti cocok dan
        // pertanyaan bersyaratnya diam-diam tidak pernah muncul lagi. ETL dan
        // ekspor Kemdikbud juga membaca kode, bukan label.
        if (!empty($questionData['logic']) && ($questionData['logic']['type'] ?? '') === 'in_array') {
            $depCode = $questionData['logic']['dependsOn'] ?? '';
            $values  = $questionData['logic']['values'] ?? [];
            if ($depCode && !empty($values)) {
                // Pemicunya sebuah kelompok daftar centang: kode kelompok
                // tidak pernah ada sebagai pertanyaan, jadi ditukar dengan
                // kode pertanyaan individual yang labelnya dipilih, dan
                // nilainya menjadi 1 (tercentang).
                if (isset($groupLabelToCode[$depCode])) {
                    $pertama = (string) reset($values);
                    $kodeIndividual = $groupLabelToCode[$depCode][$pertama] ?? null;

                    if ($kodeIndividual) {
                        $metadata['show_if'] = [$kodeIndividual => [1]];
                        return $metadata;
                    }
                }

                $kamus = $optionCodeByLabel[$depCode] ?? [];
                $metadata['show_if'] = [
                    $depCode => array_values(array_map(
                        function ($v) use ($kamus) {
                            $kode = $kamus[(string) $v] ?? $v;

                            // Kode opsi yang berupa angka disimpan sebagai
                            // angka, seragam dengan seeder. Perbandingannya
                            // memang berbasis teks di kedua sisi, tapi bentuk
                            // yang seragam membuat salinan kuesioner benar-
                            // benar sama dengan sumbernya — dan perbedaan
                            // yang tersisa jadi berarti.
                            return is_numeric($kode) ? (int) $kode : $kode;
                        },
                        $values,
                    )),
                ];
            }
        }

        return $metadata;
    }

    /**
     * Load 1 kuesioner lengkap (header + sections + questions + options) sebagai array
     * siap-konsumsi untuk response JSON.
     */
    private function loadQuestionnaire(int $id): ?array
    {
        $questionnaire = $this->questionnaireRepo->findHeaderById($id);
        if (!$questionnaire) {
            return null;
        }

        $sections  = $this->questionnaireRepo->getSectionsByQuestionnaireId($id);
        $questions = $this->questionnaireRepo->getQuestionsByQuestionnaireId($id);
        $options   = $this->questionnaireRepo->getOptionsGrouped(
            $questions->pluck('id')->all()
        );

        $questionsBySection = $questions->groupBy(fn ($q) => $q->section_id ?? 0);

        $mappedSections = $sections->map(function ($section) use ($questionsBySection, $options) {
            $sectionQuestions = ($questionsBySection[$section->id] ?? collect())->map(
                fn ($q) => $this->mapQuestionFull($q, $options)
            )->values()->toArray();

            return [
                'id'          => $section->id,
                'title'       => $section->title,
                'description' => $section->description,
                'questions'   => $sectionQuestions,
            ];
        })->values()->toArray();

        // Fallback: tidak ada sections tapi ada questions → bungkus ke 1 section default
        if (empty($mappedSections) && $questions->isNotEmpty()) {
            $mappedSections[] = [
                'id'          => 0,
                'title'       => 'Bagian 1',
                'description' => null,
                'questions'   => $questions->map(fn ($q) => $this->mapQuestionFull($q, $options))
                    ->values()->toArray(),
            ];
        }

        return [
            'id'           => $questionnaire->id,
            'code'         => $questionnaire->code,
            'title'        => $questionnaire->title,
            'description'  => $questionnaire->description,
            'target'       => $questionnaire->target,
            'respondents'  => $questionnaire->sample_respondents
                ? json_decode($questionnaire->sample_respondents, true)
                : [],
            'period_year'             => (int) $questionnaire->period_year,
            'target_graduation_years' => isset($questionnaire->target_graduation_years) && $questionnaire->target_graduation_years
                ? json_decode($questionnaire->target_graduation_years, true)
                : null,
            'version'      => (int) $questionnaire->version,
            'status'       => $questionnaire->status,
            'program_id'   => $questionnaire->program_id,
            'is_global'    => is_null($questionnaire->program_id),
            'sections'     => $mappedSections,
        ];
    }

    /** Shape 1 question full (untuk admin panel) — versi "full" dengan scale/grid. */
    private function mapQuestionFull(object $question, \Illuminate\Support\Collection $optionsGrouped): array
    {
        $metadata = $question->metadata ? json_decode($question->metadata, true) : [];

        return [
            'id'            => $question->id,
            'code'          => $question->code,
            'question'      => $question->question_text,
            'question_text' => $question->question_text,
            // Pertanyaan lookup bawaan seeder/migrasi tidak punya
            // original_type — penanda 'lookup' di metadata yang menentukan,
            // supaya Form Builder membukanya sebagai isian referensi, bukan
            // kotak teks yang lalu menimpa metadata-nya saat disimpan ulang.
            'type'          => isset($metadata['lookup'])
                ? 'lookup'
                : ($metadata['original_type'] ?? $this->mapQuestionTypeToFrontend($question->question_type, $metadata)),
            // Medan sunting bebas. Dulu 'description' dipatok null sehingga
            // keterangan pertanyaan tidak pernah sampai ke borang penyunting
            // maupun ke salinan-dari-template, walau nilainya ada di metadata.
            'description'   => $metadata['description'] ?? null,
            'hint'          => $metadata['hint'] ?? null,
            'format'        => $metadata['format'] ?? null,
            'divider_label' => $metadata['divider_label'] ?? null,
            'warn_min'      => $metadata['warn_min'] ?? null,
            'warn_max'      => $metadata['warn_max'] ?? null,
            'options'       => ($optionsGrouped->get($question->id, collect()))->map(fn ($o) => [
                'id'       => $o->id,
                'code'     => $o->option_code,
                'label'    => $o->option_label,
                'value'    => $o->option_value,
                'order_no' => $o->order_no,
                'is_hidden' => (bool) ($o->is_hidden ?? false),
            ])->values()->toArray(),
            'required'    => (bool) $question->is_required,
            'allowOther'  => $metadata['allowOther'] ?? false,
            'scaleMin'    => $metadata['scaleMin']   ?? $metadata['scale_min'] ?? 1,
            'scaleMax'    => $metadata['scaleMax']   ?? $metadata['scale_max'] ?? 5,
            'scaleLabels' => $metadata['scale_labels'] ?? [],
            'gridRows'    => $metadata['gridRows']   ?? [],
            'gridColumns' => $metadata['gridColumns'] ?? [],
            'lookup'      => $metadata['lookup'] ?? null,
            'lookupValue' => $metadata['lookup_value'] ?? null,
            'dependsOn'   => $metadata['depends_on'] ?? null,
            'metadata'    => $metadata, // includes show_if for conditional logic
        ];
    }
}
