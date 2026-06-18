<?php

namespace App\Services\ETL;

use App\DTOs\ETL\EtlRunSummaryDTO;
use App\Repositories\ETL\OlapLoadRepository;
use App\Repositories\ETL\OltpExtractRepository;
use Illuminate\Support\Facades\DB;

/**
 * Orchestrator ETL. Urutan eksekusi WAJIB:
 *
 *   1. dim_waktu              (snapshot baru -> id_waktu)
 *   2. dim_prodi               (SCD Type 2, independen dari batch responses)
 *   3. dim_indikator_evaluasi  (Type 1, independen dari batch -- bisa
 *                               jalan kapan saja sebelum fact_multi_select
 *                               / fact_range_evaluasi dibangun)
 *   4. dim_ump                 (Type 1, independen, sumber ref_ump)
 *   5. dim_alumni              (SCD Type 1, hanya alumni yang relevan di batch)
 *   6. dim_status_alumni       (Type 1+append, hanya questionnaire relevan di batch)
 *   6b. dim_kesesuaian_level   (Type 1+append, dynamic dari questionnaire_options
 *                               f14, pola identik dim_status_alumni)
 *   7. fact_tracer_study + fact_multi_select + fact_range_evaluasi
 *      (terakhir -- dim_perusahaan & dim_wirausaha disinkronkan INLINE
 *      di tahap ini, karena business key-nya baru diketahui setelah
 *      jawaban per-alumni di-pivot)
 *
 * Alasan dim selalu sebelum fact: fact menyimpan SK (surrogate key),
 * bukan business key. Tanpa dim ter-sync dulu, SK yang dibutuhkan
 * belum ada / belum mencerminkan perubahan terbaru.
 */
class EtlOrchestratorService
{
    public function __construct(
        private readonly OltpExtractRepository $oltpRepo,
        private readonly OlapLoadRepository $olapRepo,
        private readonly ProdiDimService $prodiDim,
        private readonly AlumniDimService $alumniDim,
        private readonly StatusAlumniDimService $statusAlumniDim,
        private readonly KesesuaianLevelDimService $kesesuaianLevelDim,
        private readonly KesesuaianBidangDimService $kesesuaianBidangDim,
        private readonly IndikatorEvaluasiDimService $indikatorEvaluasiDim,
        private readonly UmpDimService $umpDim,
        private readonly AlumniFactBuilderService $factBuilder,
    ) {}

    public function run(bool $force = false): EtlRunSummaryDTO
    {
        $summary = new EtlRunSummaryDTO();
        $snapshotDate = now();
        $lastSnapshot = $force ? null : $this->getLastSnapshotDate();

        return DB::connection('olap')->transaction(function () use ($summary, $snapshotDate, $lastSnapshot) {

            // ── Tahap 1: dim_waktu ──
            $idWaktu = $this->olapRepo->insertNewSnapshot($snapshotDate);
            $summary->idWaktu = $idWaktu;
            $summary->addStage('dim_waktu', 1, 1, 0);

            // ── Extract: responses + answers relevan untuk batch ini ──
            $responses = $this->oltpRepo->getSubmittedResponsesSince($lastSnapshot);

            if ($responses->isEmpty()) {
                $summary->addStage('responses (extract)', 0, 0, 0);
                return $summary;
            }

            $responseIds = $responses->pluck('response_id')->all();
            $allAnswers = $this->oltpRepo->getAnswersForResponses($responseIds);
            $summary->addStage('responses (extract)', $responses->count(), 0, 0);

            // ── Tahap 2: dim_prodi (SCD Type 2) ──
            $prodiResult = $this->prodiDim->sync($snapshotDate);
            $summary->addStage('dim_prodi (SCD2)', $prodiResult['processed'], $prodiResult['inserted'], $prodiResult['updated']);

            // ── Tahap 3: dim_indikator_evaluasi (Type 1) ──
            $indikatorResult = $this->indikatorEvaluasiDim->sync();
            $summary->addStage('dim_indikator_evaluasi (Type1)', $indikatorResult['processed'], $indikatorResult['inserted'], $indikatorResult['updated']);

            // ── Tahap 4: dim_ump (Type 1) ──
            $umpResult = $this->umpDim->sync();
            $summary->addStage('dim_ump (Type1)', $umpResult['processed'], $umpResult['inserted'], $umpResult['updated']);

            // ── Tahap 5: dim_alumni (SCD Type 1) ──
            $alumniIds = $responses->pluck('alumni_id')->unique()->all();
            $alumniResult = $this->alumniDim->sync($alumniIds);
            $summary->addStage('dim_alumni (SCD1)', $alumniResult['processed'], $alumniResult['inserted'], $alumniResult['updated']);

            // ── Tahap 6: dim_status_alumni (Type 1+append) ──
            $questionnaireIds = $responses->pluck('questionnaire_id')->unique()->all();
            $statusResult = $this->statusAlumniDim->sync($questionnaireIds);
            $summary->addStage('dim_status_alumni (Type1)', $statusResult['processed'], $statusResult['inserted'], $statusResult['updated']);

            // ── Tahap 6b: dim_kesesuaian_level (Type1+append, dynamic) ──
            // Sama pola dim_status_alumni -- dijalankan sebelum fact,
            // supaya semua opsi f14 yang relevan di batch ini sudah
            // ter-sync sebelum AlumniFactBuilderService butuh resolve SK.
            $kesesuaianResult = $this->kesesuaianLevelDim->sync($questionnaireIds);
            $summary->addStage('dim_kesesuaian_level (Type1)', $kesesuaianResult['processed'], $kesesuaianResult['inserted'], $kesesuaianResult['updated']);

            // ── Tahap 6c: dim_kesesuaian_bidang (Type1+append, dynamic) ──
            // Independen dari dim_kesesuaian_level (sumber f14, bukan f15).
            $kesesuaianBidangResult = $this->kesesuaianBidangDim->sync($questionnaireIds);
            $summary->addStage('dim_kesesuaian_bidang (Type1)', $kesesuaianBidangResult['processed'], $kesesuaianBidangResult['inserted'], $kesesuaianBidangResult['updated']);

            // ── Tahap 7: 3 fact table sekaligus, per alumni ──
            // dim_perusahaan & dim_wirausaha (SCD2) disinkronkan INLINE
            // di dalam factBuilder, karena business key-nya baru
            // diketahui setelah jawaban per-alumni di-pivot.
            $factResult = $this->factBuilder->buildAndInsertAllFacts($responses, $allAnswers, $idWaktu, $snapshotDate);

            $summary->addStage(
                'fact_tracer_study',
                $factResult['tracer_study']['processed'],
                $factResult['tracer_study']['inserted'],
                $factResult['tracer_study']['skipped']
            );
            $summary->addStage('fact_multi_select', $factResult['multi_select']['processed'], $factResult['multi_select']['inserted'], 0);
            $summary->addStage('fact_range_evaluasi', $factResult['range_evaluasi']['processed'], $factResult['range_evaluasi']['inserted'], 0);

            return $summary;
        });
    }

    /**
     * Untuk TESTING per-beberapa-menit, ini akan sering mengembalikan
     * waktu yang sangat dekat dengan sekarang -- batch yang diproses
     * kecil, itu memang yang diinginkan supaya verifikasi mudah.
     */
    private function getLastSnapshotDate(): ?\DateTimeInterface
    {
        $row = DB::connection('olap')->table('dim_waktu')->orderByDesc('id_waktu')->first();
        return $row !== null ? new \DateTimeImmutable($row->tanggal_refresh) : null;
    }
}