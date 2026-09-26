<?php

namespace App\Services\ETL;

use App\Repositories\ETL\OlapLoadRepository;
use App\Repositories\ETL\OltpExtractRepository;
use Illuminate\Support\Collection;

/**
 * Builder fact_multi_select. Grain: 1 baris per (alumni, indikator
 * evaluasi boolean, snapshot) -- HANYA untuk jawaban yang TRUE/dicentang.
 *
 * Tidak ada kolom measure di tabel ini (cek schema: id_multi_select,
 * id_alumni, prodi_sk, id_waktu, id_indikator_evaluasi -- tidak ada
 * kolom nilai). Ini murni bridge/occurrence table: keberadaan baris
 * ITU SENDIRI berarti "alumni ini memilih alasan ini".
 */
class MultiSelectFactBuilderService
{
    public function __construct(
        private readonly AnswerResolverService $resolver,
        private readonly OlapLoadRepository $olapRepo,
    ) {}

    /**
     * @param Collection $answersForOneAlumni semua baris response_answers
     *        milik SATU response_id (hasil groupBy dari caller)
     * @return int jumlah baris fact_multi_select yang di-insert untuk alumni ini
     */
    public function buildForAlumni(
        int $questionnaireId,
        int $alumniSk,
        int $prodiSk,
        int $idWaktu,
        Collection $answersForOneAlumni
    ): int {
        $insertedCount = 0;

        foreach ($answersForOneAlumni as $answer) {
            $meta = $this->resolver->getQuestionMeta($questionnaireId, $answer->question_code);

            if ($meta === null || $meta->question_type !== 'boolean') {
                continue; // bukan pertanyaan boolean, bukan kandidat multi_select
            }

            if (!isset($meta->metadata['group_code'])) {
                continue; // boolean tapi bukan bagian dari grup multi-select
            }

            $isChecked = $this->resolver->resolveValue($questionnaireId, $answer->question_code, $answer);

            if ($isChecked !== true) {
                continue; // HANYA insert baris untuk yang TRUE/dicentang
            }

            $idIndikatorEvaluasi = $this->olapRepo->getIndikatorEvaluasiId($answer->question_code);

            if ($idIndikatorEvaluasi === null) {
                // dim_indikator_evaluasi belum sync untuk field ini --
                // sinyal bahwa IndikatorEvaluasiDimService harus jalan
                // SEBELUM fact ini dibangun (lihat urutan di orchestrator).
                continue;
            }

            // Kalau pertanyaan ini adalah anggota grup multi-select yang
            // labelnya "Lainnya" (terdeteksi otomatis, lihat
            // OltpExtractRepository::getShowIfCompanionDefinitions() jalur
            // boolean), ambil teks bebas dari pertanyaan lanjutannya --
            // fact_multi_select grain-nya per-alumni jadi aman ditimpa di
            // sini, TIDAK BOLEH di dim_indikator_evaluasi (itu Type1/global,
            // dipakai bareng semua alumni yang pernah mencentang opsi sama).
            $jawabanLainnya = $this->resolver->getCompanionText(
                $questionnaireId,
                $answer->question_code,
                $answersForOneAlumni
            );

            $this->olapRepo->insertFactMultiSelect([
                'id_alumni'             => $alumniSk,
                'prodi_sk'              => $prodiSk,
                'id_waktu'              => $idWaktu,
                'id_indikator_evaluasi' => $idIndikatorEvaluasi,
                'jawaban_lainnya'       => $jawabanLainnya,
            ]);

            $insertedCount++;
        }

        return $insertedCount;
    }
}