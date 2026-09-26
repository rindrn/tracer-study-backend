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
    /** Question_code yang isinya ID provinsi (FK ke provinces.id), bukan teks bebas. */
    private const PROVINCE_ID_CODES = ['f5a1'];

    /** Question_code yang isinya ID kota (FK ke cities.id), bukan teks bebas. */
    private const CITY_ID_CODES = ['f5a2'];

    /**
     * Batas panjang teks pengganti dari companion "Lainnya, tuliskan" --
     * konservatif, disetel ke kolom OLAP TERSEMPIT yang diketahui saat ini
     * dipakai role narrow (dim_alumni.label_sumber_biaya_dipolban,
     * dim_perusahaan.label_jenis_perusahaan -- keduanya varchar(100),
     * dikonfirmasi di database/dump/init.sql). LEBIH KETAT dari default
     * truncateFreeText() 180 char (disetel untuk kolom company_name/
     * perguruan_tinggi varchar(200)) supaya tidak overflow kolom sempit
     * ini. Kalau nanti ada role narrow baru dengan kolom target lebih
     * sempit dari 100, cek ulang lebar kolomnya langsung di
     * database/dump/init.sql -- JANGAN percaya
     * semantic_role_registry.target_column sebagai acuan lebar kolom,
     * sudah ditemukan tidak sinkron dengan nama kolom OLAP sesungguhnya
     * untuk role jenis_perusahaan (registry bilang 'jenis_perusahaan',
     * kolom aslinya 'label_jenis_perusahaan').
     */
    private const COMPANION_TEXT_MAX_LENGTH = 100;

    /** @var array<int, Collection> cache: questionnaire_id => questionnaire_options */
    private array $optionsCache = [];

    /** @var array<int, Collection> cache: questionnaire_id => companion definitions (lihat applyCompanionSubstitutions()) */
    private array $companionDefsCache = [];

    /** @var array<int, Collection> cache: questionnaire_id => question meta (type+metadata) */
    private array $questionMetaCache = [];

    /** @var Collection|null cache: semua provinces (34 baris, tetap, di-load sekali) */
    private ?Collection $provincesCache = null;

    /** @var Collection|null cache: semua cities (di-load sekali) */
    private ?Collection $citiesCache = null;

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
     *
     * f5a1/f5a2 DIPERLAKUKAN KHUSUS di luar percabangan question_type
     * biasa: walau type-nya 'short_text' di skema (sehingga secara
     * default akan diteruskan mentah), isinya SEBENARNYA adalah FK
     * numerik ke provinces.id/cities.id -- jadi perlu lookup nama
     * sebelum diteruskan ke dim_perusahaan/dim_wirausaha.
     */
    public function resolveValue(int $questionnaireId, string $questionCode, object $rawAnswer): mixed
    {
        if (in_array($questionCode, self::PROVINCE_ID_CODES, true)) {
            return $this->resolveProvinceName($rawAnswer->answer_text);
        }

        if (in_array($questionCode, self::CITY_ID_CODES, true)) {
            return $this->resolveCityName($rawAnswer->answer_text);
        }

        // ── Cek dulu apakah question_code ini punya baris di
        // questionnaire_options, TERLEPAS dari question_type-nya. ──
        // Ditemukan kasus nyata: f5c (jabatan wirausaha) bertype
        // 'number' di skema, TAPI tetap punya 4 opsi berkode
        // (1=Staff, 2=Founder, 3=Freelancer, 4=Co-Founder) di
        // questionnaire_options. Asumsi awal "number selalu cast ke
        // float" SALAH untuk kasus ini -- jawabannya kode pilihan,
        // bukan angka bebas. Mengecek opsi DULU (bukan murni
        // berdasarkan question_type) membuat resolver ini benar untuk
        // kombinasi question_type+opsi apapun, tanpa perlu hardcode
        // pengecualian per question_code.
        $options = $this->getOptionsForQuestionnaire($questionnaireId);
        $hasOptions = $options->contains(fn ($opt) => $opt->question_code === $questionCode);

        if ($hasOptions) {
            return $this->resolveChoiceLabel($questionnaireId, $questionCode, $rawAnswer->answer_text);
        }

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
     * Timpa $resolved[parent_code] dengan jawaban teks bebas dari
     * pertanyaan companion-nya, KHUSUS untuk alumni yang benar-benar
     * memilih opsi "Lainnya, tuliskan" pada pertanyaan induk (bukan opsi
     * lain) DAN companion-nya benar-benar terisi. Kalau companion
     * kosong/tidak dijawab, $resolved[parent_code] dibiarkan seperti
     * hasil resolve semula (label opsi tetap, mis. "Lainnya, tuliskan")
     * -- fail-visible, bukan fail-silent, sama filosofinya dengan
     * resolveProvinceName() di atas: respondennya memang tidak menuliskan
     * apa-apa, itu bukan bug ETL yang perlu disamarkan.
     *
     * $answersForResponse adalah collection jawaban MENTAH satu response
     * yang SAMA (sudah dipunyai caller sebelum pivot $resolved dibangun --
     * lihat AlumniFactBuilderService), sehingga tidak perlu query OLTP
     * baru per-alumni; hanya getShowIfCompanionDefinitions() yang
     * melakukan I/O baru, dan itu di-cache sekali per proses ETL.
     *
     * Definisi companion PASANGAN INI TIDAK PERNAH mengubah nilai
     * $resolved untuk parent yang jawabannya BUKAN opsi Lainnya -- resolve
     * biasa (resolveChoiceLabel()) tetap satu-satunya sumber nilai untuk
     * kasus itu.
     */
    public function applyCompanionSubstitutions(int $questionnaireId, array $resolved, Collection $answersForResponse): array
    {
        foreach ($this->getCompanionDefinitions($questionnaireId) as $def) {
            $parentAnswer = $answersForResponse->firstWhere('question_code', $def->parent_code);

            if ($parentAnswer === null) {
                continue; // alumni tidak menjawab pertanyaan induk sama sekali
            }

            $rawParentValue = $parentAnswer->answer_text !== null ? (string) $parentAnswer->answer_text : null;

            if ($rawParentValue === null || !in_array($rawParentValue, $def->trigger_values, true)) {
                continue; // opsi lain yang dipilih -- nilai resolve semula sudah benar
            }

            $companionAnswer = $answersForResponse->firstWhere('question_code', $def->companion_code);
            $companionText = $companionAnswer->answer_text ?? null;

            if ($companionText === null || trim($companionText) === '') {
                continue; // companion kosong -- biarkan label "Lainnya, tuliskan" apa adanya
            }

            $resolved[$def->parent_code] = $this->truncateFreeText($companionText, self::COMPANION_TEXT_MAX_LENGTH);
        }

        return $resolved;
    }

    private function getCompanionDefinitions(int $questionnaireId): Collection
    {
        return $this->companionDefsCache[$questionnaireId] ??= $this->oltpRepo
            ->getShowIfCompanionDefinitions()
            ->filter(fn ($def) => $def->questionnaire_id === $questionnaireId)
            ->values();
    }

    /**
     * Versi applyCompanionSubstitutions() untuk anggota grup multi-select
     * (boolean) -- dipakai MultiSelectFactBuilderService, BUKAN untuk role
     * narrow yang bersarang di $resolved. fact_multi_select grain-nya per-
     * alumni (beda dari dim_indikator_evaluasi yang Type1/global), jadi teks
     * penggantinya tidak bisa lewat applyCompanionSubstitutions() yang
     * menimpa $resolved[parent_code] -- di sini caller (MultiSelectFactBuilderService)
     * sendiri yang menaruh hasilnya ke kolom fact_multi_select.jawaban_lainnya.
     *
     * Beda dengan applyCompanionSubstitutions(), TIDAK perlu cek "apakah
     * $questionCode ini benar-benar yang dipilih" -- caller HANYA memanggil
     * method ini untuk jawaban boolean yang SUDAH dikonfirmasi true (lihat
     * MultiSelectFactBuilderService::buildForAlumni()), jadi kalau
     * $questionCode terdaftar sebagai parent_code companion, otomatis berarti
     * alumni ini memilih opsi "Lainnya" tersebut.
     *
     * Return null kalau $questionCode bukan companion-parent (mis. checkbox
     * biasa, bukan "Lainnya"), atau companion-nya kosong/tidak dijawab --
     * caller lalu membiarkan fact_multi_select.jawaban_lainnya NULL.
     */
    public function getCompanionText(int $questionnaireId, string $questionCode, Collection $answersForResponse): ?string
    {
        $def = $this->getCompanionDefinitions($questionnaireId)
            ->firstWhere('parent_code', $questionCode);

        if ($def === null) {
            return null;
        }

        $companionAnswer = $answersForResponse->firstWhere('question_code', $def->companion_code);
        $companionText = $companionAnswer->answer_text ?? null;

        if ($companionText === null || trim($companionText) === '') {
            return null;
        }

        return $this->truncateFreeText($companionText, self::COMPANION_TEXT_MAX_LENGTH);
    }

    /**
     * Lookup nama provinsi dari ID mentah (f5a1). Mengembalikan ID
     * mentah sebagai fallback jika tidak match (data kotor), BUKAN
     * null -- supaya tetap ada sinyal sesuatu salah, alih-alih hilang
     * diam-diam. Caller (AlumniFactBuilderService) tetap bisa pakai
     * nilai ini untuk derive business key dim_perusahaan/dim_wirausaha
     * meski isinya kebetulan ID mentah karena data kotor.
     */
    private function resolveProvinceName(?string $rawId): ?string
    {
        if ($rawId === null || $rawId === '') {
            return $rawId;
        }

        if (!ctype_digit($rawId)) {
            return $this->canonicalRegionName($this->getProvinces(), $rawId);
        }

        $provinces = $this->getProvinces();
        $match = $provinces->firstWhere('id', (int) $rawId);

        return $match?->name ?? $rawId;
    }

    /** Lookup nama kota dari ID mentah (f5a2), pola sama dengan resolveProvinceName(). */
    private function resolveCityName(?string $rawId): ?string
    {
        if ($rawId === null || $rawId === '') {
            return $rawId;
        }

        // Nama kota lama SENGAJA diteruskan apa adanya, tidak dikanonikkan
        // seperti provinsi. Dua alasan, keduanya terbukti saat dicoba:
        //
        //   1. Ambigu. "Bandung" cocok ke "Kota Bandung" MAUPUN "Kab.
        //      Bandung"; menebak salah satunya memalsukan data alumni.
        //   2. Nama kota ikut membentuk kunci bisnis dim_perusahaan
        //      ("{perusahaan}|{kota}"). Mengubah ejaannya memunculkan baris
        //      dimensi baru sementara baris lama tetap berbendera aktif,
        //      sehingga satu perusahaan terhitung dua kali di Sebaran Instansi.
        //
        // Provinsi tidak punya dua masalah itu: penamaannya tunggal dan tidak
        // masuk kunci bisnis, jadi SCD2 cukup menutup versi lama.
        if (!ctype_digit($rawId)) {
            return $rawId;
        }

        $cities = $this->getCities();
        $match = $cities->firstWhere('id', (int) $rawId);

        return $match?->name ?? $rawId;
    }

    /**
     * Pemulihan untuk jawaban PROVINSI lama yang telanjur berupa nama.
     *
     * Sejak isian wilayah memakai dropdown, f5a1 selalu menyimpan
     * provinces.id. Tapi jawaban yang sudah masuk sebelum itu berisi nama
     * tanpa prefiks ("Jawa Barat"), sedangkan tabel referensi dan
     * dim_ump.nama_provinsi memakai bentuk berprefiks ("Prov. Jawa Barat").
     * Selisih satu prefiks itu membuat pencocokan UMP gagal total -- seluruh
     * baris fakta berakhir dengan ump_sk NULL dan measure
     * count_above_ump/count_below_ump ikut nol.
     *
     * Nama bebas dicocokkan ke bentuk kanonik tabel referensi dengan
     * mengabaikan prefiks dan beda huruf besar-kecil. Yang dikembalikan selalu
     * ejaan versi tabel referensi, sehingga join UMP konsisten dari jalur mana
     * pun datangnya. Nama yang benar-benar tidak dikenal diteruskan apa adanya,
     * mengikuti prinsip fallback method di atas: lebih baik terlihat janggal
     * daripada hilang diam-diam.
     *
     * Hanya untuk provinsi -- lihat catatan di resolveCityName().
     */
    private function canonicalRegionName(Collection $reference, string $rawName): string
    {
        $needle = $this->stripRegionPrefix($rawName);

        $match = $reference->first(
            fn ($row) => $this->stripRegionPrefix($row->name) === $needle,
        );

        return $match?->name ?? $rawName;
    }

    /** "Prov. Jawa Barat" -> "jawa barat". */
    private function stripRegionPrefix(string $name): string
    {
        return mb_strtolower(trim(preg_replace(
            '/^(prov\.|provinsi)\s+/iu',
            '',
            trim($name),
        )));
    }

    private function getProvinces(): Collection
    {
        return $this->provincesCache ??= $this->oltpRepo->getAllProvinces();
    }

    private function getCities(): Collection
    {
        return $this->citiesCache ??= $this->oltpRepo->getAllCities();
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