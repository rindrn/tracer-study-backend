<?php

namespace App\Http\Controllers\Api\Transactional;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;

class QuestionnaireFetchController extends Controller
{
    /**
     * Mengambil daftar kuesioner aktif (Pusat + Jurusan terkait).
     */
    public function getActiveForms(Request $request): JsonResponse
    {
        $kodeProdi = $request->query('kode_prodi');

        if (!$kodeProdi) {
            return response()->json([
                'success' => false,
                'message' => 'Parameter kode_prodi (misal: kdpstmsmh) wajib disertakan di URL.'
            ], 400);
        }

        // Cari ID Prodi (Berdasarkan parameter string frontend)
        $program = DB::connection('oltp')->table('programs')
            ->where('code', $kodeProdi)
            ->first();

        if (!$program) {
            return response()->json([
                'success' => false,
                'message' => 'Kode program studi tidak dikenali.'
            ], 404);
        }

        // Ambil kuesioner Pusat (program_id IS NULL) dan kuesioner Prodi terkait (program_id = ID Prodi)
        // Keduanya disyaratkan berstatus 'published'
        $questionnaires = DB::connection('oltp')->table('questionnaires')
            ->where('status', 'published')
            ->where(function($q) use ($program) {
                $q->whereNull('program_id')
                  ->orWhere('program_id', $program->id);
            })
            ->get();

        if ($questionnaires->isEmpty()) {
            return response()->json([
                'success' => true,
                'data' => [],
                'message' => 'Tidak ada kuesioner aktif.'
            ]);
        }

        $questionnaireIds = $questionnaires->pluck('id')->toArray();

        // Mengambil daftar pertanyaan yang masuk dalam form terkait
        $questions = DB::connection('oltp')->table('questionnaire_questions')
            ->whereIn('questionnaire_id', $questionnaireIds)
            ->orderBy('order_no')
            ->get();

        $questionIds = $questions->pluck('id')->toArray();

        // Mengambil Opsi Jawaban (jika jenis soal adalah multiple choice / single choice)
        $options = DB::connection('oltp')->table('questionnaire_options')
            ->whereIn('question_id', $questionIds)
            ->orderBy('order_no')
            ->get()
            ->groupBy('question_id');

        // Menyusun (mapping) Object JSON — normalisasi nama field agar sesuai kontrak FE
        $mappedQuestions = $questions->map(function ($q) use ($options) {
            $rawOptions = $options->get($q->id, collect());
            $metadata = $q->metadata ? json_decode($q->metadata) : null;

            return (object) [
                'id'               => $q->id,
                'questionnaire_id' => $q->questionnaire_id,
                'question_code'    => $q->code,           // FE expects "question_code"
                'question_text'    => $q->question_text,
                'question_type'    => $q->question_type,
                'is_required'      => $q->is_required,
                'order_no'         => $q->order_no,
                'metadata'         => $metadata,
                'options'          => $rawOptions->map(function ($o) {
                    return [
                        'id'    => $o->id,
                        'code'  => $o->option_code,       // e.g. "1", "2", "3"
                        'label' => $o->option_label,       // FE expects "label"
                        'value' => $o->option_code,        // FE uses value as answer key — use option_code
                    ];
                })->values(),
            ];
        })->groupBy('questionnaire_id');

        $result = $questionnaires->map(function ($qnr) use ($mappedQuestions) {
            $qnr->is_global = is_null($qnr->program_id);
            $qnr->questions = $mappedQuestions->get($qnr->id, collect())->values();
            return $qnr;
        });

        return response()->json([
            'success' => true,
            'data' => $result->values()
        ]);
    }
}
