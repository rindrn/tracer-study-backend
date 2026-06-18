<?php

namespace App\Services\ETL;

use App\Repositories\ETL\OlapLoadRepository;
use App\Repositories\ETL\OltpExtractRepository;
use Illuminate\Support\Collection;

/**
 * Membangun fact_tracer_study (grain: 1 baris per alumni per snapshot),
 * dan di loop yang SAMA memicu fact_multi_select dan fact_range_evaluasi
 * untuk alumni yang sama -- supaya jawaban tidak perlu di-pivot dua kali.
 *
 * Mapping question_code -> field OLAP (terkonfirmasi dari data nyata,
 * lihat OltpExtractRepository::RELEVANT_QUESTION_CODES):
 *   f8    -> status alumni (dim_status_alumni)
 *   f14   -> kesesuaian bidang (dim_kesesuaian_bidang + dim_kesesuaian_level)
 *   f502  -> masa_tunggu_bekerja
 *   f505  -> take_home_pay
 *   f5a1  -> provinsi tempat kerja
 *   f5a2  -> kota tempat kerja
 *   f5b   -> nama perusahaan (dim_perusahaan)
 *   f5c   -> jabatan wirausaha (dim_wirausaha)
 *   f5d   -> tingkat instansi (dipakai BAIK oleh dim_perusahaan MAUPUN
 *            dim_wirausaha -- pertanyaan ini sama untuk kedua jalur kerja,
 *            dibedakan oleh apakah f5b atau f5c yang terisi)
 *   f1101 -> jenis perusahaan (dim_perusahaan)
 *   f18a  -> sumber biaya studi lanjut (dim_studi_lanjut, lookup option)
 *   f18b  -> perguruan tinggi studi lanjut (dim_studi_lanjut)
 *   f18c  -> program studi studi lanjut (dim_studi_lanjut)
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
        \DateTimeInterface $snapshotDate
    ): array {
        $answersByResponse = $allAnswers->groupBy('response_id');

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

            // ── Pivot EAV -> array asosiatif ──
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

            // ── label_sumber_biaya_dipolban dari f1201 ──
            // Field ini adalah JAWABAN kuesioner (response-level), bukan
            // data master alumni_profiles -- karena itu AlumniDimService
            // (yang hanya punya akses ke alumni_profiles murni) TIDAK
            // bisa mengisinya. Di-resolve di sini, lalu dim_alumni
            // di-upsert ULANG dengan field ini terisi (SCD Type 1,
            // overwrite -- konsisten dengan field lain di dim_alumni).
            if ($alumniRow !== null && isset($resolved['f1201'])) {
                $this->olapRepo->upsertAlumni([
                    'nim'                          => $alumniRow->nim,
                    'nama'                         => $alumniRow->name,
                    'jenis_kelamin'                => null,
                    'angkatan'                     => (string) $alumniRow->entry_year,
                    'tahun_lulus'                  => (string) $alumniRow->graduation_year,
                    'label_sumber_biaya_dipolban'  => $resolved['f1201'],
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

            // ── status_alumni_sk dari f8 (NOT NULL di fact_tracer_study) ──
            $f8Answer = $answersForThisAlumni->firstWhere('question_code', 'f8');
            $statusAlumniSk = null;
            if ($f8Answer !== null) {
                $rawCode = $this->resolver->getRawOptionCode($f8Answer);
                if ($rawCode !== null) {
                    $idStatusAlumni = $this->statusAlumniDim->resolveIdStatusAlumni(
                        $response->questionnaire_id,
                        $rawCode
                    );
                    $statusAlumniSk = $this->olapRepo->getStatusAlumniSk($idStatusAlumni);
                }
            }

            if ($statusAlumniSk === null) {
                $skipped++;
                continue;
            }

            // ── dim_perusahaan (alumni bekerja, f5b terisi) ──
            $perusahaanSk = $this->perusahaanDim->syncAndResolveSk([
                'nama_perusahaan'  => $resolved['f5b'] ?? null,
                'jenis_perusahaan' => $resolved['f1101'] ?? null,
                'kota'             => $resolved['f5a2'] ?? null,
                'provinsi'         => $resolved['f5a1'] ?? null,
                'tingkat_instansi' => $resolved['f5d'] ?? null,
            ], $snapshotDate);

            // ── dim_wirausaha (alumni wiraswasta, f5c terisi) ──
            $wirausahaSk = $this->wirausahaDim->syncAndResolveSk([
                'jabatan'          => $resolved['f5c'] ?? null,
                'kota'             => $resolved['f5a2'] ?? null,
                'provinsi'         => $resolved['f5a1'] ?? null,
                'tingkat_instansi' => $resolved['f5d'] ?? null,
            ], $snapshotDate);

            // ── dim_kesesuaian_bidang dari f14 (kesesuaian BIDANG studi) ──
            // Dynamic sync (pola SAMA dengan f8/dim_status_alumni).
            // f14 dan f15 adalah dua pertanyaan INDEPENDEN (terverifikasi
            // dari data: f14 opsinya Sangat Erat...Tidak Sama Sekali,
            // f15 opsinya Setingkat Lebih Tinggi...Tidak Perlu Pendidikan
            // Tinggi) -- TIDAK ADA relasi FK antara dim_kesesuaian_bidang
            // dan dim_kesesuaian_level, koreksi atas asumsi awal yang salah.
            $f14Answer = $answersForThisAlumni->firstWhere('question_code', 'f14');
            $kesesuaianBidangSk = $kesesuaianBidangSentinelSk;

            if ($f14Answer !== null) {
                $rawCodeF14 = $this->resolver->getRawOptionCode($f14Answer);

                if ($rawCodeF14 !== null) {
                    $idKesesuaianBidang = $this->kesesuaianBidangDim->resolveIdKesesuaianBidang(
                        $response->questionnaire_id,
                        $rawCodeF14
                    );
                    $kesesuaianBidangSk = $this->olapRepo->getKesesuaianBidangSk($idKesesuaianBidang)
                        ?? $kesesuaianBidangSentinelSk;
                }
            }

            // ── dim_kesesuaian_level dari f15 (kesesuaian LEVEL pendidikan) ──
            // Independen dari f14, sumbernya pertanyaan terpisah.
            $f15Answer = $answersForThisAlumni->firstWhere('question_code', 'f15');
            $kesesuaianLevelSk = $kesesuaianLevelSentinelSk;

            if ($f15Answer !== null) {
                $rawCodeF15 = $this->resolver->getRawOptionCode($f15Answer);

                if ($rawCodeF15 !== null) {
                    $idKesesuaianLevel = $this->kesesuaianLevelDim->resolveIdKesesuaianLevel(
                        $response->questionnaire_id,
                        $rawCodeF15
                    );
                    $kesesuaianLevelSk = $this->olapRepo->getKesesuaianLevelSk($idKesesuaianLevel)
                        ?? $kesesuaianLevelSentinelSk;
                }
            }

            // ── dim_studi_lanjut dari f18b (PT) + f18c (prodi) + f18a (sumber biaya) ──
            // Hanya relevan jika alumni melanjutkan studi (f8=4). Jika
            // tidak, isi sentinel (id=0, "Tidak Ada Data") -- sama
            // alasannya dengan kesesuaian_bidang/level di atas.
            if (isset($resolved['f18b'], $resolved['f18c'])) {
                $idStudiLanjut = $this->olapRepo->upsertStudiLanjut(
                    $resolved['f18b'],
                    $resolved['f18c'],
                    $resolved['f18a'] ?? null // sudah ter-resolve ke label (single_choice)
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
            $takeHomePay = isset($resolved['f505']) ? (int) $resolved['f505'] : null;

            if ($alumniRow !== null && $perusahaanSk !== null) {
                $tahunLulus = (string) $alumniRow->graduation_year;
                $provinsiKerja = $resolved['f5a1'] ?? null;

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
                'masa_tunggu_bekerja'   => isset($resolved['f502']) ? (int) $resolved['f502'] : null,
                'bulan_sebelum_lulus'   => isset($resolved['f302']) ? (int) $resolved['f302'] : null,
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
}