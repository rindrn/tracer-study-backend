<?php

namespace App\Repositories\ETL;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Semua query SELECT terhadap tracer_oltp.
 *
 * Rantai relasi WAJIB:
 *   response_answers.response_id -> responses.id
 *                                    responses.alumni_id        <- alumni
 *                                    responses.questionnaire_id <- versi kuesioner
 *
 * EFISIENSI ETL (per requirement user):
 *   1. HANYA question_code yang relevan ke OLAP ditarik dari response_answers
 *      -- lihat RELEVANT_QUESTION_CODES. Field identitas murni (nimhsmsmh,
 *      nmmhsmsmh, kdptimsmh, dst dari section 1) TIDAK PERNAH ditarik sama
 *      sekali, karena tidak ada satupun dim/fact OLAP yang membutuhkannya
 *      secara langsung (alumni di-resolve via alumni_id -> alumni_profiles,
 *      bukan via jawaban kuesioner).
 *   2. Kolom acuan response_answers HANYA: id, response_id, question_code,
 *      answer_text (semua nilai termasuk angka disimpan sebagai string di
 *      answer_text, dikonfirmasi dari data nyata -- answer_number/
 *      answer_date/answer_option_code tidak dipakai).
 */
class OltpExtractRepository
{
    /**
     * Whitelist question_code yang benar-benar dipetakan ke OLAP -- DULU
     * hardcode const RELEVANT_QUESTION_CODES, SEKARANG dibaca dinamis dari
     * tracer_oltp.question_semantic_mapping (lihat getRelevantQuestionCodes()
     * di bawah). Field identitas (NIM, nama, email, dst) sengaja TIDAK
     * pernah termasuk -- data alumni diambil dari alumni_profiles via
     * alumni_id, bukan dari jawaban kuesioner.
     *
     * Di-memoize di sini (bukan di-query ulang tiap kali dipanggil) supaya
     * ke-4 call site (getAnswersForResponses/getOptionsForQuestionnaire/
     * getQuestionMetaForQuestionnaire/getAllIndikatorEvaluasiCandidates)
     * tetap hanya menghasilkan PALING BANYAK satu query DISTINCT tambahan
     * per instance repository -- TIDAK PERNAH di dalam loop per-alumni,
     * sama semangatnya dengan requirement "computed ONCE" di kontrak.
     */
    private ?array $relevantQuestionCodesCache = null;

    private function oltp(): \Illuminate\Database\Connection
    {
        return DB::connection('oltp');
    }

    /**
     * Union dua sumber:
     *   1. question_code AKTIF di question_semantic_mapping -- sumber
     *      kebenaran utama sekarang, mencakup role narrow MAUPUN wide
     *      (kompetensi/metode/alasan semuanya sudah termapping, lihat
     *      003_semantic_mapping_seed.sql).
     *   2. kode_field yang SUDAH tercatat di public.dim_indikator_evaluasi
     *      (lintas koneksi ke 'olap' -- fisik satu Postgres yang sama,
     *      lihat config/database.php) -- jaring pengaman supaya kalau
     *      suatu saat mapping wide-grain dinonaktifkan tanpa sengaja, ETL
     *      tidak langsung berhenti menarik jawaban untuk kode yang dim-nya
     *      sudah established dari run-run sebelumnya.
     */
    public function getRelevantQuestionCodes(): array
    {
        if ($this->relevantQuestionCodesCache !== null) {
            return $this->relevantQuestionCodesCache;
        }

        $mapped = $this->oltp()->table('question_semantic_mapping')
            ->where('is_active', true)
            ->distinct()
            ->pluck('question_code');

        $impliedByIndikator = DB::connection('olap')->table('dim_indikator_evaluasi')->pluck('kode_field');

        return $this->relevantQuestionCodesCache = $mapped->merge($impliedByIndikator)->unique()->values()->all();
    }

    /**
     * Ambil semua responses SUBMITTED yang baru/berubah sejak snapshot
     * terakhir. Grain: 1 baris = 1 response (1 alumni, 1 questionnaire,
     * 1 submission) -- sama dengan grain fact_tracer_study.
     */
    public function getSubmittedResponsesSince(?\DateTimeInterface $since): Collection
    {
        $query = $this->oltp()->table('responses as r')
            ->select(['r.id as response_id', 'r.alumni_id', 'r.questionnaire_id', 'r.status', 'r.submitted_at', 'r.updated_at'])
            ->where('r.status', 'submitted');

        if ($since !== null) {
            $query->where('r.updated_at', '>=', $since);
        }

        return $query->orderBy('r.id')->get();
    }

    /**
     * Ambil HANYA jawaban yang relevan (RELEVANT_QUESTION_CODES) untuk
     * sekumpulan response_id. HANYA 4 kolom: id, response_id,
     * question_code, answer_text -- sesuai konfirmasi bahwa semua nilai
     * (termasuk numerik) disimpan sebagai string di answer_text.
     *
     * Filter whitelist DI QUERY (whereIn question_code), bukan di PHP
     * setelah fetch -- supaya volume data yang ditarik dari OLTP minimal,
     * bukan cuma minimal yang diproses.
     */
    public function getAnswersForResponses(array $responseIds): Collection
    {
        if (empty($responseIds)) {
            return collect();
        }

        return $this->oltp()->table('response_answers')
            ->select(['id', 'response_id', 'question_code', 'answer_text'])
            ->whereIn('response_id', $responseIds)
            ->whereIn('question_code', $this->getRelevantQuestionCodes())
            ->orderBy('response_id')
            ->get();
    }

    /**
     * Lookup label opsi jawaban untuk satu questionnaire_id tertentu,
     * dibatasi HANYA ke question_code yang relevan (whitelist).
     * Business key: (questionnaire_id, question_code, option_code).
     */
    public function getOptionsForQuestionnaire(int $questionnaireId): Collection
    {
        return $this->oltp()->table('questionnaire_options as qo')
            ->join('questionnaire_questions as qq', 'qq.id', '=', 'qo.question_id')
            ->where('qq.questionnaire_id', $questionnaireId)
            ->whereIn('qq.code', $this->getRelevantQuestionCodes())
            ->select(['qq.code as question_code', 'qo.option_code', 'qo.option_label'])
            ->get();
    }

    /**
     * Metadata pertanyaan (question_type + metadata JSON), dibatasi
     * HANYA ke question_code relevan.
     */
    public function getQuestionMetaForQuestionnaire(int $questionnaireId): Collection
    {
        return $this->oltp()->table('questionnaire_questions')
            ->where('questionnaire_id', $questionnaireId)
            ->whereIn('code', $this->getRelevantQuestionCodes())
            ->select(['code as question_code', 'question_type', 'metadata'])
            ->get();
    }

    /**
     * Sumber dim_indikator_evaluasi: pertanyaan question_type IN
     * (boolean, number) yang relevan (otomatis subset dari whitelist
     * dinamis karena f1601-f1613, f1761-f1774, f21-f27 semua sudah
     * termapping ke role wide-grain di question_semantic_mapping).
     */
    public function getAllIndikatorEvaluasiCandidates(): Collection
    {
        return $this->oltp()->table('questionnaire_questions')
            ->whereIn('question_type', ['boolean', 'number'])
            ->whereIn('code', $this->getRelevantQuestionCodes())
            ->select(['code as question_code', 'question_text', 'question_type', 'metadata'])
            ->get();
    }

    public function getAllPrograms(): Collection
    {
        return $this->oltp()->table('programs')
            ->select([
                'id', 'name', 'code', 'degree', 'jurusan', 'is_active', 'updated_at',
                // Sumber dim_prodi.akreditasi_prodi (Gambar 5.3). Ikut di sini,
                // bukan di query terpisah, supaya deteksi perubahan SCD Type 2
                // di ProdiDimService melihat seluruh atribut sekaligus.
                'accreditation',
                // DFR-17: FK ke org_units (Fase 2, boleh NULL -- lihat catatan
                // dual-mode di OrgUnitHierarchyResolverService). Dipakai
                // ProdiDimService untuk resolve level_1_name..level_5_name.
                'org_unit_id',
            ])
            ->get();
    }

    public function getAlumniByIds(array $alumniIds): Collection
    {
        if (empty($alumniIds)) {
            return collect();
        }

        return $this->oltp()->table('alumni_profiles')
            ->whereIn('id', $alumniIds)
            ->select(['id', 'nim', 'name', 'program_id', 'entry_year', 'graduation_year', 'updated_at'])
            ->get();
    }

    /**
     * Sumber dim_ump: tracer_oltp.ref_ump (di-sync dari BPS API oleh
     * fitur UMP management yang sudah ada).
     */
    public function getAllUmp(): Collection
    {
        return $this->oltp()->table('ref_ump')
            ->select(['id', 'tahun', 'nilai_ump', 'nama_provinsi', 'updated_at'])
            ->get();
    }

    /**
     * Sumber lookup nama provinsi: f5a1 menyimpan provinces.id (FK
     * numerik), BUKAN nama provinsi langsung -- dikonfirmasi dari
     * struktur question_type='short_text' yang isinya ID, bukan teks
     * bebas. Ditarik sekali di awal run dan di-cache di memory oleh
     * AnswerResolverService, karena jumlah provinsi/kota tetap (34
     * provinsi) dan tidak berubah antar-alumni.
     */
    public function getAllProvinces(): Collection
    {
        return $this->oltp()->table('provinces')
            ->select(['id', 'code', 'name'])
            ->get();
    }

    /**
     * Sumber lookup nama kota: f5a2 menyimpan cities.id (FK numerik),
     * sama alasannya dengan getAllProvinces().
     */
    public function getAllCities(): Collection
    {
        return $this->oltp()->table('cities')
            ->select(['id', 'province_code', 'code', 'name'])
            ->get();
    }
}