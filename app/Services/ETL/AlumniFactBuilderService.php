<?php

namespace App\Services\ETL;

use App\Repositories\ETL\OlapLoadRepository;
use App\Repositories\ETL\OltpExtractRepository;
use App\Repositories\ETL\SemanticMappingRepository;
use Illuminate\Support\Collection;

/**
 * Membangun fact_tracer_study (grain: 1 baris per alumni per snapshot),
 * dan di loop yang SAMA memicu fact_multi_select dan fact_range_evaluasi
 * untuk alumni yang sama -- supaya jawaban tidak perlu di-pivot dua kali.
 *
 * Mapping question_code -> field OLAP SEKARANG DINAMIS lewat
 * SemanticMappingRepository (tracer_oltp.question_semantic_mapping),
 * BUKAN literal $resolved['f502'] dkk lagi. Selain pivot lama
 * $resolved[question_code] (tetap dipertahankan -- masih dipakai untuk
 * f1201/upsertAlumni dan tetap jadi sumber nilai untuk $resolvedByRole,
 * supaya tidak resolve dua kali), method ini SEKARANG juga membangun
 * $resolvedByRole[semantic_role] = value untuk role grain='narrow' di
 * questionnaire response ini, tervalidasi terhadap kontrak
 * expected_kind/value_min/value_max milik role tsb (lihat
 * passesRoleValidation()). Role f8/f14/f15 (status_pekerjaan/
 * relevansi_bidang/kesesuaian_level) TETAP diresolve terpisah lewat jalur
 * raw-option-code + dim service seperti sebelumnya (mereka men-drive SK
 * dimensi, bukan langsung menulis kolom fact) -- HANYA literal
 * 'f8'/'f14'/'f15' yang diganti jadi question_code dinamis hasil
 * SemanticMappingRepository::questionCodeFor().
 *
 * SKIP RESPONSE (requirement user poin 2): jika response TIDAK punya
 * satupun field substantif terisi (hanya field identitas seperti NIM/
 * nama, yang sudah tidak ditarik sama sekali oleh OltpExtractRepository),
 * maka $answersForThisAlumni akan kosong setelah filter whitelist --
 * grain fact tidak terpenuhi (tidak ada apapun untuk diisi), sehingga
 * response ini otomatis dilewati (lihat early-continue di bawah).
 */
class AlumniFactBuilderService
{
    public function __construct(
        private readonly AnswerResolverService $resolver,
        private readonly StatusAlumniDimService $statusAlumniDim,
        private readonly KesesuaianLevelDimService $kesesuaianLevelDim,
        private readonly KesesuaianBidangDimService $kesesuaianBidangDim,
        private readonly PerusahaanDimService $perusahaanDim,
        private readonly WirausahaDimService $wirausahaDim,
        private readonly MultiSelectFactBuilderService $multiSelectBuilder,
        private readonly RangeEvaluasiFactBuilderService $rangeEvaluasiBuilder,
        private readonly OltpExtractRepository $oltpRepo,
        private readonly OlapLoadRepository $olapRepo,
        private readonly SemanticMappingRepository $semanticRepo,
        private readonly AnomalyLoggerService $anomalyLogger,
    ) {}

    /**
     * @return array{
     *   tracer_study: array{processed:int, inserted:int, skipped:int},
     *   multi_select: array{processed:int, inserted:int},
     *   range_evaluasi: array{processed:int, inserted:int}
     * }
     */
    public function buildAndInsertAllFacts(
        Collection $responses,
        Collection $allAnswers,
        int $idWaktu,
        \DateTimeInterface $snapshotDate,
        string $etlRunId
    ): array {
        $answersByResponse = $allAnswers->groupBy('response_id');

        // ── Mapping semantik: di-load SEKALI untuk seluruh batch, BUKAN
        // per-alumni -- lihat SemanticMappingRepository, method-methodnya
        // sudah memoize per-questionnaire secara internal. ──
        $questionnaireIds = $responses->pluck('questionnaire_id')->unique()->all();
        $mappingIndex = $this->semanticRepo->getActiveMappingsForQuestionnaires($questionnaireIds);
        $roleRegistry = $this->semanticRepo->getActiveRoleRegistry();

        // ── Sentinel "Tidak Ada Data" dipastikan ada SEKALI di awal run,
        // bukan per-alumni di dalam loop -- method ensure* sudah
        // idempotent, tapi memanggilnya sekali saja lebih efisien
        // daripada query SELECT pengecekan berulang untuk setiap alumni
        // yang sama-sama tidak punya jawaban f14/f18b/f18c. ──
        $kesesuaianLevelSentinelSk = $this->olapRepo->ensureKesesuaianLevelSentinel();
        $kesesuaianBidangSentinelSk = $this->olapRepo->ensureKesesuaianBidangSentinel();
        $studiLanjutSentinelId = $this->olapRepo->ensureStudiLanjutSentinel();

        $processed = 0;
        $inserted = 0;
        $skipped = 0;
        $multiSelectInserted = 0;
        $rangeEvaluasiInserted = 0;

        foreach ($responses as $response) {
            $processed++;

            $answersForThisAlumni = $answersByResponse->get($response->response_id, collect());

            // ── SKIP jika tidak ada field substantif sama sekali ──
            // (requirement user poin 2: grain fact tidak terpenuhi).
            // Karena getAnswersForResponses() di repo SUDAH memfilter
            // whitelist, $answersForThisAlumni kosong berarti response
            // ini hanya berisi field identitas yang sudah tidak ditarik.
            if ($answersForThisAlumni->isEmpty()) {
                $skipped++;
                continue;
            }

            // ── Pivot EAV -> array asosiatif per question_code (LAMA,
            // dipertahankan apa adanya -- masih sumber nilai f1201). ──
            $resolved = [];
            foreach ($answersForThisAlumni as $answer) {
                $resolved[$answer->question_code] = $this->resolver->resolveValue(
                    $response->questionnaire_id,
                    $answer->question_code,
                    $answer
                );
            }

            // ── Resolve alumni_sk & prodi_sk (dim harus sudah sync) ──
            $alumniRow = $this->oltpRepo->getAlumniByIds([$response->alumni_id])->first();
            $alumniSk = $alumniRow !== null ? $this->olapRepo->getAlumniSkByNim($alumniRow->nim) : null;

            // ── Pivot TAMBAHAN: EAV -> array asosiatif per semantic_role
            // (BARU). Hanya role grain='narrow' yang diproses di sini --
            // role wide (kompetensi_evaluasi/metode_pembelajaran/
            // alasan_kerja_tidak_sesuai) tetap sepenuhnya jadi urusan
            // MultiSelectFactBuilderService/RangeEvaluasiFactBuilderService
            // via dim_indikator_evaluasi, TIDAK disentuh di sini. Nilai
            // dipakai ULANG dari $resolved di atas -- TIDAK di-resolve dua
            // kali. Kegagalan validasi (tipe/rentang tidak cocok
            // expected_kind milik role) TIDAK menulis nilai (dibiarkan
            // absen dari $resolvedByRole, sehingga target fact-nya null)
            // dan dicatat ke etl_anomaly_log lewat AnomalyLoggerService --
            // TIDAK PERNAH melempar exception / menghentikan run. ──
            $resolvedByRole = [];
            foreach ($answersForThisAlumni as $answer) {
                $mappingRow = $mappingIndex['by_code']->get($response->questionnaire_id . ':' . $answer->question_code);

                if ($mappingRow === null || $mappingRow->grain !== 'narrow') {
                    continue; // tidak termapping (lagi), atau wide-grain -- bukan urusan pivot ini
                }

                $roleKey = $mappingRow->semantic_role;
                $roleRow = $roleRegistry->get($roleKey);
                $value = $resolved[$answer->question_code] ?? null;

                if ($roleRow !== null && $value !== null && $value !== '' && !$this->passesRoleValidation($value, $roleRow)) {
                    $this->anomalyLogger->log([
                        'etl_run_id'       => $etlRunId,
                        'alumni_nim'       => $alumniRow->nim ?? null,
                        'questionnaire_id' => $response->questionnaire_id,
                        'question_code'    => $answer->question_code,
                        'semantic_role'    => $roleKey,
                        'raw_answer'       => is_scalar($value) ? (string) $value : json_encode($value),
                        'expected_kind'    => $roleRow->expected_kind,
                        'reason'           => is_numeric($value) ? 'out_of_range' : 'type_mismatch',
                        'detail'           => sprintf(
                            'Nilai "%s" tidak valid untuk role "%s" (expected_kind=%s%s).',
                            $value,
                            $roleKey,
                            $roleRow->expected_kind,
                            $roleRow->value_min !== null ? ", range={$roleRow->value_min}-{$roleRow->value_max}" : ''
                        ),
                    ]);

                    continue; // JANGAN masuk ke resolvedByRole -- target fact tetap null
                }

                $resolvedByRole[$roleKey] = $value;
            }

            // ── label_sumber_biaya_dipolban dari role sumber_biaya_studi
            // (dulu literal f1201) ──
            // Field ini adalah JAWABAN kuesioner (response-level), bukan
            // data master alumni_profiles -- karena itu AlumniDimService
            // (yang hanya punya akses ke alumni_profiles murni) TIDAK
            // bisa mengisinya. Di-resolve di sini, lalu dim_alumni
            // di-upsert ULANG dengan field ini terisi (SCD Type 1,
            // overwrite -- konsisten dengan field lain di dim_alumni).
            if ($alumniRow !== null && isset($resolvedByRole['sumber_biaya_studi'])) {
                $this->olapRepo->upsertAlumni([
                    'nim'                          => $alumniRow->nim,
                    'nama'                         => $alumniRow->name,
                    'jenis_kelamin'                => null,
                    'angkatan'                     => (string) $alumniRow->entry_year,
                    'tahun_lulus'                  => (string) $alumniRow->graduation_year,
                    'label_sumber_biaya_dipolban'  => $resolvedByRole['sumber_biaya_studi'],
                ]);
            }

            $prodiSk = null;
            if ($alumniRow !== null) {
                $activeProdi = $this->olapRepo->getActiveProdiVersion($alumniRow->program_id);
                $prodiSk = $activeProdi?->prodi_sk;
            }

            if ($alumniSk === null || $prodiSk === null) {
                // id_alumni dan prodi_sk NOT NULL di ketiga fact -- tanpa
                // ini tidak ada fact yang valid untuk di-insert.
                $skipped++;
                continue;
            }

            // ── status_alumni_sk dari role status_pekerjaan (dulu literal
            // f8, NOT NULL di fact_tracer_study) ──
            $statusQuestionCode = $this->semanticRepo->questionCodeFor($response->questionnaire_id, 'status_pekerjaan');
            $f8Answer = $statusQuestionCode !== null
                ? $answersForThisAlumni->firstWhere('question_code', $statusQuestionCode)
                : null;
            $statusAlumniSk = null;
            if ($f8Answer !== null) {
                $rawCode = $this->resolver->getRawOptionCode($f8Answer);
                if ($rawCode !== null) {
                    $idStatusAlumni = $this->statusAlumniDim->resolveIdStatusAlumni(
                        $response->questionnaire_id,
                        $statusQuestionCode,
                        $rawCode
                    );
                    $statusAlumniSk = $this->olapRepo->getStatusAlumniSk($idStatusAlumni);
                }
            }

            if ($statusAlumniSk === null) {
                $skipped++;
                continue;
            }

            // ── dim_perusahaan (alumni bekerja, nama_perusahaan terisi) ──
            $perusahaanSk = $this->perusahaanDim->syncAndResolveSk([
                'nama_perusahaan'  => $resolvedByRole['nama_perusahaan'] ?? null,
                'jenis_perusahaan' => $resolvedByRole['jenis_perusahaan'] ?? null,
                'kota'             => $resolvedByRole['kota_kerja'] ?? null,
                'provinsi'         => $resolvedByRole['provinsi_kerja'] ?? null,
                'tingkat_instansi' => $resolvedByRole['tingkat_instansi'] ?? null,
            ], $snapshotDate);

            // ── dim_wirausaha (alumni wiraswasta, jabatan_wirausaha terisi) ──
            // Business key = $alumniSk (id_alumni dari dim_alumni).
            // Lihat WirausahaDimService untuk penjelasan lengkap perubahan
            // dari business key lama "jabatan|kota" ke id_alumni.
            $wirausahaSk = $this->wirausahaDim->syncAndResolveSk([
                'jabatan'          => $resolvedByRole['jabatan_wirausaha'] ?? null,
                'kota'             => $resolvedByRole['kota_kerja'] ?? null,
                'provinsi'         => $resolvedByRole['provinsi_kerja'] ?? null,
                'tingkat_instansi' => $resolvedByRole['tingkat_instansi'] ?? null,
            ], $alumniSk, $snapshotDate);

            // ── dim_kesesuaian_bidang dari role relevansi_bidang (dulu
            // literal f14, kesesuaian BIDANG studi) ──
            // Dynamic sync (pola SAMA dengan status_pekerjaan/dim_status_alumni).
            // relevansi_bidang dan kesesuaian_level adalah dua role
            // INDEPENDEN -- TIDAK ADA relasi FK antara dim_kesesuaian_bidang
            // dan dim_kesesuaian_level.
            $bidangQuestionCode = $this->semanticRepo->questionCodeFor($response->questionnaire_id, 'relevansi_bidang');
            $f14Answer = $bidangQuestionCode !== null
                ? $answersForThisAlumni->firstWhere('question_code', $bidangQuestionCode)
                : null;
            $kesesuaianBidangSk = $kesesuaianBidangSentinelSk;

            if ($f14Answer !== null) {
                $rawCodeF14 = $this->resolver->getRawOptionCode($f14Answer);

                if ($rawCodeF14 !== null) {
                    $idKesesuaianBidang = $this->kesesuaianBidangDim->resolveIdKesesuaianBidang(
                        $response->questionnaire_id,
                        $bidangQuestionCode,
                        $rawCodeF14
                    );
                    $kesesuaianBidangSk = $this->olapRepo->getKesesuaianBidangSk($idKesesuaianBidang)
                        ?? $kesesuaianBidangSentinelSk;
                }
            }

            // ── dim_kesesuaian_level dari role kesesuaian_level (dulu
            // literal f15) ──
            // Independen dari relevansi_bidang, sumbernya pertanyaan terpisah.
            $levelQuestionCode = $this->semanticRepo->questionCodeFor($response->questionnaire_id, 'kesesuaian_level');
            $f15Answer = $levelQuestionCode !== null
                ? $answersForThisAlumni->firstWhere('question_code', $levelQuestionCode)
                : null;
            $kesesuaianLevelSk = $kesesuaianLevelSentinelSk;

            if ($f15Answer !== null) {
                $rawCodeF15 = $this->resolver->getRawOptionCode($f15Answer);

                if ($rawCodeF15 !== null) {
                    $idKesesuaianLevel = $this->kesesuaianLevelDim->resolveIdKesesuaianLevel(
                        $response->questionnaire_id,
                        $levelQuestionCode,
                        $rawCodeF15
                    );
                    $kesesuaianLevelSk = $this->olapRepo->getKesesuaianLevelSk($idKesesuaianLevel)
                        ?? $kesesuaianLevelSentinelSk;
                }
            }

            // ── dim_studi_lanjut dari pt_lanjut + prodi_lanjut + sumber_biaya_lanjut
            // (dulu literal f18b + f18c + f18a) ──
            // Hanya relevan jika alumni melanjutkan studi (f8=4). Jika
            // tidak, isi sentinel (id=0, "Tidak Ada Data") -- sama
            // alasannya dengan kesesuaian_bidang/level di atas.
            if (isset($resolvedByRole['pt_lanjut'], $resolvedByRole['prodi_lanjut'])) {
                $idStudiLanjut = $this->olapRepo->upsertStudiLanjut(
                    $resolvedByRole['pt_lanjut'],
                    $resolvedByRole['prodi_lanjut'],
                    $resolvedByRole['sumber_biaya_lanjut'] ?? null // sudah ter-resolve ke label (single_choice)
                );
            } else {
                $idStudiLanjut = $studiLanjutSentinelId;
            }

            // ── ump_sk + flag_above_ump ──
            // Acuan UMP: tahun LULUS alumni (bukan tahun snapshot ETL
            // berjalan), dikonfirmasi user. Threshold flag_above_ump
            // adalah 1.2x UMP (bukan 1.0x seperti versi sebelumnya) --
            // sesuai definisi "di atas UMP secara layak", bukan sekadar
            // "memenuhi minimum UMP".
            $umpSk = null;
            $flagAboveUmp = null;
            $rawThp = isset($resolvedByRole['pendapatan']) ? (int) $resolvedByRole['pendapatan'] : null;
            $takeHomePay = ($rawThp !== null && $rawThp >= 100000) ? $rawThp : null;

            if ($alumniRow !== null && $perusahaanSk !== null) {
                $tahunLulus = (string) $alumniRow->graduation_year;
                $provinsiKerja = $resolvedByRole['provinsi_kerja'] ?? null;

                if ($provinsiKerja !== null) {
                    $umpRow = $this->olapRepo->findUmpByTahunProvinsi($tahunLulus, $provinsiKerja);

                    if ($umpRow !== null) {
                        $umpSk = $umpRow->ump_sk;

                        if ($takeHomePay !== null) {
                            $ambangBatas = (float) $umpRow->nilai_ump * 1.2;
                            $flagAboveUmp = $takeHomePay >= $ambangBatas ? 1 : 0;
                        }
                    }
                }
            }

            // ── INSERT fact_tracer_study (selalu baris baru per snapshot) ──
            $this->olapRepo->insertFactTracerStudy([
                'id_alumni'             => $alumniSk,
                'id_waktu'              => $idWaktu,
                'kesesuaian_bidang_sk'  => $kesesuaianBidangSk,
                'kesesuaian_level_sk'   => $kesesuaianLevelSk,
                'status_alumni_sk'      => $statusAlumniSk,
                'perusahaan_sk'         => $perusahaanSk,
                'id_studi_lanjut'       => $idStudiLanjut,
                'prodi_sk'              => $prodiSk,
                'wirausaha_sk'          => $wirausahaSk,
                'masa_tunggu_bekerja'   => isset($resolvedByRole['masa_tunggu_bekerja']) ? (int) $resolvedByRole['masa_tunggu_bekerja'] : null,
                'bulan_sebelum_lulus'   => isset($resolvedByRole['bulan_sebelum_lulus']) ? (int) $resolvedByRole['bulan_sebelum_lulus'] : null,
                'bulan_sesudah_lulus'   => null,
                'masa_tunggu_wirausaha' => null,
                'take_home_pay'         => $takeHomePay,
                'ump_sk'                => $umpSk,
                'flag_above_ump'        => $flagAboveUmp,
            ]);
            $inserted++;

            // ── fact_multi_select & fact_range_evaluasi untuk alumni yang SAMA ──
            $multiSelectInserted += $this->multiSelectBuilder->buildForAlumni(
                $response->questionnaire_id,
                $alumniSk,
                $prodiSk,
                $idWaktu,
                $answersForThisAlumni
            );

            $rangeEvaluasiInserted += $this->rangeEvaluasiBuilder->buildForAlumni(
                $response->questionnaire_id,
                $alumniSk,
                $prodiSk,
                $idWaktu,
                $answersForThisAlumni
            );
        }

        return [
            'tracer_study'   => ['processed' => $processed, 'inserted' => $inserted, 'skipped' => $skipped],
            'multi_select'   => ['processed' => $processed, 'inserted' => $multiSelectInserted],
            'range_evaluasi' => ['processed' => $processed, 'inserted' => $rangeEvaluasiInserted],
        ];
    }

    /**
     * Validasi ringan nilai hasil pivot terhadap kontrak semantic_role-nya:
     *   - integer/decimal : harus numeric DAN (kalau value_min/max diisi)
     *                       masuk rentang.
     *   - categorical/text/boolean/date : presence check saja (kalau
     *                       sampai di sini nilainya sudah pasti tidak
     *                       null/kosong, caller sudah menyaring itu) --
     *                       tidak ada cara struktural memvalidasi "bentuk"
     *                       teks bebas lebih jauh dari sekadar keberadaannya.
     */
    private function passesRoleValidation(mixed $value, object $roleRow): bool
    {
        return match ($roleRow->expected_kind) {
            'integer', 'decimal' => is_numeric($value)
                && ($roleRow->value_min === null || (float) $value >= (float) $roleRow->value_min)
                && ($roleRow->value_max === null || (float) $value <= (float) $roleRow->value_max),
            default => true,
        };
    }
}
