<?php

namespace App\Http\Controllers\Api\Transactional;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SubmitTracerStudyRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class TracerStudySubmitController extends Controller
{
    public function store(SubmitTracerStudyRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            DB::connection('oltp')->beginTransaction();

            // 1. Cari program_id berdasarkan kdpstmsmh (Kode Prodi)
            $program = DB::connection('oltp')->table('programs')->where('code', $validated['kdpstmsmh'])->first();
            
            if (!$program) {
                return response()->json([
                    'success' => false,
                    'message' => 'Program Studi dengan kode ' . $validated['kdpstmsmh'] . ' tidak ditemukan.'
                ], 400);
            }

            // 2. Cari Alumni Profile
            $alumni = DB::connection('oltp')->table('alumni_profiles')->where('nim', $validated['nim'])->first();

            $alumniData = [
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'program_id' => $program->id,
                'graduation_year' => $validated['tahun_lulus'],
                'kode_pt' => $validated['kode_pt'] ?? null,
                'nik' => $validated['nik'],
                'npwp' => $validated['npwp'] ?? null,
                'updated_at' => Carbon::now(),
            ];

            if ($alumni) {
                DB::connection('oltp')->table('alumni_profiles')
                    ->where('id', $alumni->id)
                    ->update($alumniData);
                $alumniId = $alumni->id;
            } else {
                $alumniData['nim'] = $validated['nim'];
                $alumniData['created_at'] = Carbon::now();
                $alumniId = DB::connection('oltp')->table('alumni_profiles')->insertGetId($alumniData);
            }

            // 3. Merekam Response ke kuesioner nasional (global)
            $questionnaire = DB::connection('oltp')->table('questionnaires')
                ->whereNull('program_id')
                ->where('status', 'published')
                ->first();

            if (!$questionnaire) {
                throw new \Exception("Sistem belum memiliki referensi Kuesioner aktif.");
            }

            // Jika response sudah ada, hapus dulu agar datanya bersih atau update (disini contoh replace/upsert)
            DB::connection('oltp')->table('responses')
                ->where('questionnaire_id', $questionnaire->id)
                ->where('alumni_id', $alumniId)
                ->delete();

            $responseId = DB::connection('oltp')->table('responses')->insertGetId([
                'questionnaire_id' => $questionnaire->id,
                'alumni_id' => $alumniId,
                'status' => 'submitted',
                'submitted_at' => Carbon::now(),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ]);

            // 4. Raw Dump to response_answers
            $answerRecords = [];
            // Expand grouped checkbox answers (q16_cara_cari_kerja, q21_alasan_tidak_sesuai)
            // into individual f401-f415 and f1601-f1613 booleans.
            $answerData = $request->all();
            foreach (['q16_cara_cari_kerja' => range(401, 415), 'q21_alasan_tidak_sesuai' => range(1601, 1613)] as $groupKey => $codes) {
                if (isset($answerData[$groupKey]) && is_array($answerData[$groupKey])) {
                    $selected = array_map('strval', $answerData[$groupKey]);
                    foreach ($codes as $code) {
                        $answerData['f' . $code] = in_array('f' . $code, $selected) ? '1' : '0';
                    }
                    unset($answerData[$groupKey]);
                }
            }
            foreach ($answerData as $key => $value) {
                // Kecualikan key yang sudah pasti milik tabel identitas
                $identityKeys = ['nim', 'name', 'email', 'phone', 'tahun_lulus', 'kdpstmsmh', 'kode_pt', 'nik', 'npwp'];
                if (!in_array($key, $identityKeys) && $value !== null) {
                    $answerRecords[] = [
                        'response_id' => $responseId,
                        'question_code' => $key,
                        'answer_text' => is_bool($value) ? ($value ? '1' : '0') : (string) $value,
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now()
                    ];
                }
            }
            if (count($answerRecords) > 0) {
                DB::connection('oltp')->table('response_answers')->insert($answerRecords);
            }

            // 5. Normalisasi Data untuk Employment / Education
            if (in_array((int)$validated['f8'], [1, 3])) { // Pekerja (1) / Wiraswasta (3)
                DB::connection('oltp')->table('employment_records')
                    ->where('alumni_id', $alumniId)
                    ->delete(); // Bersihkan rekaman lama

                DB::connection('oltp')->table('employment_records')->insert([
                    'alumni_id' => $alumniId,
                    'questionnaire_id' => $questionnaire->id,
                    'employment_status' => (int)$validated['f8'] === 1 ? 'employed' : 'entrepreneur',
                    'waiting_months' => $validated['f502'] ?? null,
                    'salary_current' => $validated['f505'] ?? null,
                    'work_city' => $validated['f5a2'] ?? null,
                    'company_name' => $validated['f5b'] ?? null,
                    'job_title' => $validated['f5c'] ?? null,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now()
                ]);
            } elseif ((int)$validated['f8'] === 4) { // Melanjutkan Pendidikan
                DB::connection('oltp')->table('education_records')
                    ->where('alumni_id', $alumniId)
                    ->delete();

                DB::connection('oltp')->table('education_records')->insert([
                    'alumni_id' => $alumniId,
                    'questionnaire_id' => $questionnaire->id,
                    'is_further_study' => true,
                    'institution_name' => $validated['f18b'] ?? null,
                    'major' => $validated['f18c'] ?? null,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now()
                ]);
            }

            DB::connection('oltp')->commit();

            return response()->json([
                'success' => true,
                'message' => 'Data Kuesioner Tracer Study berhasil disimpan.',
            ], 201);

        } catch (\Exception $e) {
            DB::connection('oltp')->rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan pada sistem saat menyimpan.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
