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

        // 1. Ambil Data Alumni (Filter jika role adalah prodi)
        $query = DB::connection('oltp')->table('alumni_profiles')
            ->leftJoin('responses', 'alumni_profiles.id', '=', 'responses.alumni_id')
            ->select('alumni_profiles.*', 'responses.id as response_id');

        if ($user->isProdi()) {
            $query->where('alumni_profiles.program_id', $user->program_id);
        }

        $alumniProfiles = $query->get();
        $responseIds = $alumniProfiles->pluck('response_id')->filter()->toArray();

        // 2. Ambil semua jawaban alumni
        $answers = DB::connection('oltp')->table('response_answers')
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
        
        $ministryQuestions = [];
        $prodiQuestions = [];

        foreach ($allQuestionCodes as $code) {
            // Asumsi: Pertanyaan kementrian selalu diawali huruf F.
            // Pertanyaan selain itu dianggap Custom Prodi.
            if (preg_match('/^f\w+$/i', $code)) {
                $ministryQuestions[] = $code;
            } else {
                $prodiQuestions[] = $code;
            }
        }

        // Urutkan abjad untuk kerapian Header Kolom Excel
        sort($ministryQuestions);
        sort($prodiQuestions);

        // 4. Generate & Download Excel
        $export = new TracerStudyMultiSheetExport(
            $alumniData, 
            $ministryQuestions, 
            $prodiQuestions
        );

        return Excel::download($export, 'Laporan_Tracer_Study_'.date('YmdHis').'.xlsx');
    }
}
