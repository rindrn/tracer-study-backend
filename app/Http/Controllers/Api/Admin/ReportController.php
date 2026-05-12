<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Exports\TracerStudyMultiSheetExport;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    public function exportAlumniResponses(Request $request)
    {
        $user = $request->user();
        $conn = DB::connection('oltp');
        $questionnaireId = $request->query('questionnaire_id');

        // 1. Ambil Data Alumni (Filter jika role adalah prodi)
        $query = $conn->table('alumni_profiles')
            ->leftJoin('responses', 'alumni_profiles.id', '=', 'responses.alumni_id')
            ->leftJoin('programs', 'alumni_profiles.program_id', '=', 'programs.id')
            ->select(
                'alumni_profiles.*',
                'responses.id as response_id',
                'programs.name as program_name',
                'programs.code as program_code',
                'programs.jurusan as jurusan_name'
            );

        // Filter by specific questionnaire if provided
        if ($questionnaireId) {
            $query->where('responses.questionnaire_id', $questionnaireId);
        }

        if ($user->isKaprodi()) {
            $query->where('alumni_profiles.program_id', $user->program_id);
        }

        $alumniProfiles = $query->get();
        $responseIds = $alumniProfiles->pluck('response_id')->filter()->toArray();

        // 2. Ambil semua jawaban alumni
        $answers = $conn->table('response_answers')
            ->whereIn('response_id', $responseIds)
            ->get();

        // Mengelompokkan jawaban (Key-Value array per response)
        $answersGrouped = $answers->groupBy('response_id')->map(function ($items) {
            return $items->pluck('answer_text', 'question_code')->toArray();
        });

        // Gabungkan/Suntikkan array jawaban ke dalam object profil alumni
        $alumniData = $alumniProfiles->map(function ($item) use ($answersGrouped) {
            $item->answers = $item->response_id ? ($answersGrouped->get($item->response_id) ?? []) : [];
            return $item;
        });

        // 3. Pisahkan Header Kolom (Kementrian vs Prodi) berdasarkan Kode
        $allQuestionCodes = $answers->pluck('question_code')->unique()->toArray();

        // Identity codes yang tidak perlu ditampilkan sebagai kolom pertanyaan
        $identityCodes = ['nimhsmsmh', 'kdptimsmh', 'tahun_lulus', 'kdpstmsmh', 'nmmhsmsmh', 'telpomsmh', 'emailmsmh', 'nik', 'npwp'];

        $ministryCodes = [];
        $prodiCodes = [];

        foreach ($allQuestionCodes as $code) {
            // Skip identity codes — already in fixed columns
            if (in_array($code, $identityCodes)) continue;

            // Pertanyaan kementrian selalu diawali huruf F.
            // Pertanyaan selain itu dianggap Custom Prodi.
            if (preg_match('/^f\w+$/i', $code)) {
                $ministryCodes[] = $code;
            } else {
                $prodiCodes[] = $code;
            }
        }

        sort($ministryCodes);
        sort($prodiCodes);

        // 4. Ambil teks pertanyaan dari database untuk label kolom Excel
        $questionLabels = $conn->table('questionnaire_questions')
            ->whereIn('code', array_merge($ministryCodes, $prodiCodes))
            ->pluck('question_text', 'code')
            ->toArray();

        // Build array of ['code' => ..., 'label' => '...'] untuk header
        $ministryQuestions = array_map(function ($code) use ($questionLabels) {
            $text = $questionLabels[$code] ?? $code;
            // Potong teks panjang agar header tidak terlalu lebar (max 80 karakter)
            if (mb_strlen($text) > 80) {
                $text = mb_substr($text, 0, 77) . '...';
            }
            return ['code' => $code, 'label' => "{$text} ({$code})"];
        }, $ministryCodes);

        // 5. Build per-prodi question lists from their own questionnaires
        // Query each prodi's questionnaire questions directly
        $prodiQuestionnaires = $conn->table('questionnaires')
            ->whereNotNull('program_id')
            ->where('status', 'published')
            ->get()
            ->keyBy('program_id');

        // Get programs to map program_id -> code
        $programMap = $conn->table('programs')->get()->keyBy('id');

        // Build a map: program_code => [question objects]
        $prodiQuestionsByProgram = [];
        foreach ($prodiQuestionnaires as $programId => $qnr) {
            $program = $programMap[$programId] ?? null;
            if (!$program) continue;

            $qCodes = $conn->table('questionnaire_questions')
                ->where('questionnaire_id', $qnr->id)
                ->orderBy('order_no')
                ->pluck('question_text', 'code')
                ->toArray();

            $prodiQuestionsByProgram[$program->code] = array_map(function ($code, $text) {
                if (mb_strlen($text) > 80) {
                    $text = mb_substr($text, 0, 77) . '...';
                }
                return ['code' => $code, 'label' => "{$text} ({$code})"];
            }, array_keys($qCodes), array_values($qCodes));
        }

        // Group alumni by program_code for per-prodi sheets
        $prodiQuestionsGrouped = [];
        $alumniByProdi = $alumniData->groupBy('program_code');

        foreach ($alumniByProdi as $prodiCode => $prodiAlumni) {
            if (!$prodiCode) continue;

            $prodiQuestionsGrouped[$prodiCode] = [
                'name'      => $prodiAlumni->first()->program_name ?? $prodiCode,
                'questions' => $prodiQuestionsByProgram[$prodiCode] ?? [],
                'alumni'    => $prodiAlumni,
            ];
        }

        // 6. Generate & Download Excel
        $export = new TracerStudyMultiSheetExport(
            $alumniData,
            $ministryQuestions,
            $prodiQuestionsGrouped
        );

        return Excel::download($export, 'Laporan_Tracer_Study_'.date('YmdHis').'.xlsx');
    }
}
