<?php

namespace App\Services\ETL;

use App\Repositories\ETL\OltpExtractRepository;
use Illuminate\Support\Collection;

/**
 * Resolve jawaban mentah dari response_answers menjadi nilai yang siap
 * dipakai. SUMBER NILAI TUNGGAL: answer_text (dikonfirmasi dari data
 * nyata -- nilai numerik seperti take_home_pay, skor 1-5, dst SEMUA
 * disimpan sebagai string di answer_text, BUKAN di answer_number/
 * answer_date/answer_option_code). Kolom-kolom itu tidak dipakai sama
 * sekali di resolver ini.
 *
 * Perlakuan berbeda PER question_type (dikonfirmasi dari data aktual):
 *   - short_text / long_text / date  -> answer_text dipakai langsung
 *   - number                          -> answer_text di-cast ke angka.
 *                                        Jika metadata punya scale_min/max,
 *                                        ini KANDIDAT fact_range_evaluasi.
 *   - boolean                         -> answer_text di-resolve ke true/false.
 *                                        Jika metadata punya group_code,
 *                                        ini KANDIDAT fact_multi_select
 *                                        (hanya di-insert jika true).
 *   - single_choice / multiple_choice -> answer_text berisi option_code,
 *                                        HARUS di-lookup ke questionnaire_options
 *                                        via (questionnaire_id, question_code,
 *                                        option_code) untuk dapat label.
 *
 * Cache di-key per questionnaire_id supaya tidak query berulang untuk
 * alumni-alumni dari questionnaire yang sama.
 */
class AnswerResolverService
{
    /** @var array<int, Collection> cache: questionnaire_id => questionnaire_options */
    private array $optionsCache = [];

    /** @var array<int, Collection> cache: questionnaire_id => question meta (type+metadata) */
    private array $questionMetaCache = [];

    public function __construct(
        private readonly OltpExtractRepository $oltpRepo,
    ) {}

    public function getQuestionMeta(int $questionnaireId, string $questionCode): ?object
    {
        $metas = $this->getQuestionMetaForQuestionnaire($questionnaireId);
        $row = $metas->firstWhere('question_code', $questionCode);

        if ($row === null) {
            return null;
        }

        return (object) [
            'question_type' => $row->question_type,
            'metadata'      => $row->metadata !== null ? json_decode($row->metadata, true) : null,
        ];
    }

    /**
     * Resolve nilai final untuk satu jawaban, sesuai question_type-nya.
     * $rawAnswer adalah row hasil getAnswersForResponses() -- hanya
     * punya properti: id, response_id, question_code, answer_text.
     */
    public function resolveValue(int $questionnaireId, string $questionCode, object $rawAnswer): mixed
    {
        $meta = $this->getQuestionMeta($questionnaireId, $questionCode);

        if ($meta === null) {
            return $this->truncateFreeText($rawAnswer->answer_text);
        }

        return match ($meta->question_type) {
            'number' => $rawAnswer->answer_text !== null && $rawAnswer->answer_text !== ''
                ? (float) $rawAnswer->answer_text
                : null,
            'boolean' => $this->resolveBoolean($rawAnswer->answer_text),
            'single_choice', 'multiple_choice' => $this->resolveChoiceLabel($questionnaireId, $questionCode, $rawAnswer->answer_text),
            default => $this->truncateFreeText($rawAnswer->answer_text), // short_text, long_text, date
        };
    }

    /**
     * Truncate defensif untuk jawaban free-text (short_text/long_text)
     * SEBELUM masuk ke dim/fact. Ini mengatasi requirement user: alumni
     * yang mengisi opsi "Lainnya" dengan teks sangat panjang (kalimat
     * penuh, bukan satu kata) sebelumnya membuat ETL BERHENTI karena
     * gagal INSERT (truncate error dari Postgres saat melebihi panjang
     * kolom varchar).
     *
     * Batas 180 karakter dipilih dengan margin aman di bawah kolom
     * terbesar yang dipakai field ini (company_name varchar(200),
     * perguruan_tinggi varchar(200) setelah migrasi 003) -- bukan exact
     * limit, supaya tidak gagal lagi walau migrasi belum sempat
     * dijalankan di semua environment.
     *
     * "..." ditambahkan sebagai sinyal visual bahwa teks dipotong --
     * data asli LENGKAP tetap ada di OLTP (response_answers), ETL
     * hanya memotong representasi yang masuk ke OLAP untuk laporan KPI,
     * bukan menghapus data sumber.
     */
    private function truncateFreeText(?string $value, int $maxLength = 180): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        if (mb_strlen($value) <= $maxLength) {
            return $value;
        }

        return mb_substr($value, 0, $maxLength - 3) . '...';
    }

    private function resolveBoolean(?string $rawValue): ?bool
    {
        if ($rawValue === null || $rawValue === '') {
            return null;
        }
        return in_array(strtolower($rawValue), ['1', 'true', 'ya', 'yes'], true);
    }

    private function resolveChoiceLabel(int $questionnaireId, string $questionCode, ?string $rawOptionCode): ?string
    {
        if ($rawOptionCode === null || $rawOptionCode === '') {
            return null;
        }

        $options = $this->getOptionsForQuestionnaire($questionnaireId);
        $match = $options->first(
            fn ($opt) => $opt->question_code === $questionCode && $opt->option_code === $rawOptionCode
        );

        return $match?->option_label ?? $rawOptionCode;
    }

    /**
     * Ambil option_code MENTAH (sebelum di-resolve ke label) langsung
     * dari answer_text -- dipakai untuk derive business key
     * dim_status_alumni dkk yang butuh kode asli, bukan label.
     */
    public function getRawOptionCode(object $rawAnswer): ?string
    {
        return $rawAnswer->answer_text ?: null;
    }

    private function getOptionsForQuestionnaire(int $questionnaireId): Collection
    {
        return $this->optionsCache[$questionnaireId]
            ??= $this->oltpRepo->getOptionsForQuestionnaire($questionnaireId);
    }

    private function getQuestionMetaForQuestionnaire(int $questionnaireId): Collection
    {
        return $this->questionMetaCache[$questionnaireId]
            ??= $this->oltpRepo->getQuestionMetaForQuestionnaire($questionnaireId);
    }
}