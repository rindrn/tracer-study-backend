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
     * Whitelist question_code yang benar-benar dipetakan ke OLAP.
     * Field identitas (NIM, nama, email, dst) sengaja TIDAK termasuk --
     * data alumni diambil dari alumni_profiles via alumni_id, bukan dari
     * jawaban kuesioner.
     *
     * Per kategori (lihat AlumniFactBuilderService untuk detail pemakaian):
     *   - f8       : status alumni (dim_status_alumni)
     *   - f14      : kesesuaian BIDANG studi dengan pekerjaan (dim_kesesuaian_bidang)
     *               opsi: Sangat Erat, Erat, Cukup Erat, Kurang Erat, Tidak Sama Sekali
     *   - f15      : kesesuaian LEVEL/tingkat pendidikan dengan pekerjaan (dim_kesesuaian_level)
     *               opsi: Setingkat Lebih Tinggi, Tingkat yang Sama, Setingkat Lebih
     *               Rendah, Tidak Perlu Pendidikan Tinggi -- INDEPENDEN dari f14,
     *               BUKAN turunan/FK darinya (koreksi atas asumsi awal yang salah)
     *   - f18a     : sumber biaya studi lanjut (dim_studi_lanjut.sumber_biaya, lookup option)
     *   - f18b     : perguruan tinggi studi lanjut (dim_studi_lanjut.perguruan_tinggi)
     *   - f18c     : program studi studi lanjut (dim_studi_lanjut.program_studi)
     *   - f302     : bulan sebelum lulus mulai cari kerja (fact_tracer_study.bulan_sebelum_lulus)
     *   - f502     : masa tunggu kerja (fact_tracer_study.masa_tunggu_bekerja)
     *   - f505     : take home pay (fact_tracer_study.take_home_pay)
     *   - f5a1     : provinsi tempat kerja
     *   - f5a2     : kota tempat kerja
     *   - f5b      : nama perusahaan (dim_perusahaan)
     *   - f5c      : jabatan wirausaha (dim_wirausaha)
     *   - f5d      : tingkat instansi (dim_perusahaan & dim_wirausaha)
     *   - f1101    : jenis perusahaan (dim_perusahaan)
     *   - f1761-f1774 : kompetensi A/B (fact_range_evaluasi, via dim_indikator_evaluasi)
     *   - f21-f27     : metode pembelajaran (fact_range_evaluasi)
     *   - f1601-f1613 : alasan kerja tidak sesuai (fact_multi_select)
     *
     * Daftar ini bisa diperluas seiring KPI baru ditambahkan -- jadikan
     * satu sumber kebenaran tunggal supaya tidak ada whitelist ganda
     * yang bisa saling tidak sinkron.
     */
    public const RELEVANT_QUESTION_CODES = [
        'f8', 'f14', 'f15', 'f18a', 'f18b', 'f18c', 'f302', 'f502', 'f505', 'f5a1', 'f5a2', 'f5b', 'f5c', 'f5d', 'f1101', 'f1201',
        'f1761', 'f1762', 'f1763', 'f1764', 'f1765', 'f1766', 'f1767', 'f1768',
        'f1769', 'f1770', 'f1771', 'f1772', 'f1773', 'f1774',
        'f21', 'f22', 'f23', 'f24', 'f25', 'f26', 'f27',
        'f1601', 'f1602', 'f1603', 'f1604', 'f1605', 'f1606', 'f1607', 'f1608',
        'f1609', 'f1610', 'f1611', 'f1612', 'f1613',
    ];

    private function oltp(): \Illuminate\Database\Connection
    {
        return DB::connection('oltp');
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
            ->whereIn('question_code', self::RELEVANT_QUESTION_CODES)
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
            ->whereIn('qq.code', self::RELEVANT_QUESTION_CODES)
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
            ->whereIn('code', self::RELEVANT_QUESTION_CODES)
            ->select(['code as question_code', 'question_type', 'metadata'])
            ->get();
    }

    /**
     * Sumber dim_indikator_evaluasi: pertanyaan question_type IN
     * (boolean, number) yang relevan (otomatis subset dari whitelist
     * karena f1601-f1613, f1761-f1774, f21-f27 semua sudah ada di
     * RELEVANT_QUESTION_CODES).
     */
    public function getAllIndikatorEvaluasiCandidates(): Collection
    {
        return $this->oltp()->table('questionnaire_questions')
            ->whereIn('question_type', ['boolean', 'number'])
            ->whereIn('code', self::RELEVANT_QUESTION_CODES)
            ->select(['code as question_code', 'question_text', 'question_type', 'metadata'])
            ->get();
    }

    public function getAllPrograms(): Collection
    {
        return $this->oltp()->table('programs')
            ->select(['id', 'name', 'code', 'degree', 'jurusan', 'is_active', 'updated_at'])
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