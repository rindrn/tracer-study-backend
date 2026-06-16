<?php

namespace App\Services\ETL;

use App\Repositories\ETL\OlapLoadRepository;
use Illuminate\Support\Collection;

/**
 * Builder fact_range_evaluasi. Grain: 1 baris per (alumni, indikator
 * evaluasi number/range, snapshot), dengan skor 1-5 (CHECK constraint
 * di schema: skor >= 1 AND skor <= 5).
 *
 * Sumber: pertanyaan question_type='number' dengan metadata.scale_min/
 * scale_max (contoh: f1761-f1774 Kompetensi, f21-f27 MetodePembelajaran).
 */
class RangeEvaluasiFactBuilderService
{
    public function __construct(
        private readonly AnswerResolverService $resolver,
        private readonly OlapLoadRepository $olapRepo,
    ) {}

    /**
     * @return int jumlah baris fact_range_evaluasi yang di-insert untuk alumni ini
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

            if ($meta === null || $meta->question_type !== 'number') {
                continue;
            }

            if (!isset($meta->metadata['scale_min'], $meta->metadata['scale_max'])) {
                continue; // number tapi bukan skala evaluasi (misal f502 masa tunggu bulan)
            }

            $skor = $this->resolver->resolveValue($questionnaireId, $answer->question_code, $answer);

            if ($skor === null) {
                continue; // tidak diisi
            }

            $skor = (int) $skor;

            // Validasi defensif sesuai CHECK constraint di OLAP (skor 1-5).
            // Jika skor di luar rentang ini di OLTP (data kotor), JANGAN
            // insert -- biarkan gagal terdeteksi di sini daripada di-reject
            // database dengan error yang lebih sulit ditelusuri asalnya.
            if ($skor < 1 || $skor > 5) {
                continue;
            }

            $idIndikatorEvaluasi = $this->olapRepo->getIndikatorEvaluasiId($answer->question_code);

            if ($idIndikatorEvaluasi === null) {
                continue; // dim_indikator_evaluasi belum sync untuk field ini
            }

            $this->olapRepo->insertFactRangeEvaluasi([
                'prodi_sk'              => $prodiSk,
                'id_alumni'             => $alumniSk,
                'id_waktu'              => $idWaktu,
                'id_indikator_evaluasi' => $idIndikatorEvaluasi,
                'skor'                  => $skor,
            ]);

            $insertedCount++;
        }

        return $insertedCount;
    }
}