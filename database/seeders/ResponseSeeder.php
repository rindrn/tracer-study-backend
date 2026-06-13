<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Faker\Factory as Faker;
use Carbon\Carbon;

class ResponseSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create('id_ID');
        $now = Carbon::now();
        $conn = DB::connection('oltp');

        $alumniList = $conn->table('alumni_profiles')->get();
        $qGlobal = $conn->table('questionnaires')->whereNull('program_id')->first();

        if (!$qGlobal || $alumniList->isEmpty()) return;

        // Pre-load programs and prodi questionnaires
        $programs = $conn->table('programs')->get()->keyBy('id');
        $prodiQuestionnaires = $conn->table('questionnaires')
            ->whereNotNull('program_id')
            ->where('status', 'published')
            ->get()
            ->keyBy('program_id');

        // Prodi-specific answer generators (keyed by jurusan)
        $prodiAnswerMap = [
            'Teknik Sipil' => ['q_software_desain' => ['AutoCAD', 'SAP2000', 'Revit', 'SketchUp', 'ETABS']],
            'Teknik Mesin' => ['q_software_cad' => ['SolidWorks', 'AutoCAD', 'CATIA', 'Fusion 360', 'Inventor']],
            'Teknik Refrigerasi & Tata Udara' => ['q_sistem_pendingin' => ['AC Split', 'VRV/VRF System', 'Chiller', 'Cold Storage', 'AHU']],
            'Teknik Konversi Energi' => ['q_bidang_energi' => ['PLTU', 'PLTA', 'Solar Panel', 'Biomassa', 'PLTG']],
            'Teknik Elektro' => ['q_platform' => ['Arduino', 'PLC Siemens', 'Raspberry Pi', 'STM32', 'Mikrotik']],
            'Teknik Kimia' => ['q_instrumen_lab' => ['HPLC', 'GC-MS', 'Spektrofotometer UV-Vis', 'AAS', 'Titrasi Otomatis']],
            'Teknik Komputer & Informatika' => ['q_framework' => ['Laravel', 'React', 'Vue', 'Spring Boot', 'Express', 'Django', 'Flutter']],
            'Akuntansi' => ['q_software_akuntansi' => ['SAP', 'Accurate', 'Zahir', 'MYOB', 'Jurnal.id']],
            'Administrasi Niaga' => ['q_platform_digital' => ['Google Ads', 'Meta Business Suite', 'HubSpot', 'Canva', 'Shopify']],
            'Bahasa Inggris' => ['q_bidang_bahasa' => ['Penerjemah', 'Pengajar', 'Content Writer', 'Tour Guide', 'Customer Service']],
        ];

        $provinces = ['Jawa Barat', 'DKI Jakarta', 'Jawa Tengah', 'Jawa Timur', 'Banten', 'Sumatera Utara', 'Bali'];
        $cities    = ['Bandung', 'Jakarta Selatan', 'Bekasi', 'Semarang', 'Surabaya', 'Tangerang', 'Denpasar'];
        $companies = ['PT Telkom Indonesia', 'PT Pertamina', 'PT Astra International', 'Tokopedia', 'GoTo', 'PT PLN', 'Bank BCA', 'Shopee Indonesia', 'Traveloka', 'PT Krakatau Steel'];
        $universities = ['Universitas Indonesia', 'Institut Teknologi Bandung', 'Universitas Gadjah Mada', 'Universitas Padjadjaran', 'Universitas Brawijaya'];

        // Track alumni index per program to skip 4th (testing account)
        $alumniCountByProgram = [];

        foreach ($alumniList as $alumni) {
            $program = $programs[$alumni->program_id] ?? null;

            // Count alumni per program — skip the 4th (index 3) for testing
            $pid = $alumni->program_id;
            $alumniCountByProgram[$pid] = ($alumniCountByProgram[$pid] ?? 0) + 1;
            if ($alumniCountByProgram[$pid] > 3) {
                continue; // Leave this alumni unanswered for testing
            }

            // 1. Buat Header Response
            $responseId = $conn->table('responses')->insertGetId([
                'questionnaire_id' => $qGlobal->id,
                'alumni_id'        => $alumni->id,
                'status'           => 'submitted',
                'submitted_at'     => $now,
                'created_at'       => $now,
                'updated_at'       => $now,
            ]);

            $answers = [];

            // ── Section 0: Identitas ─────────────────────────
            $answers[] = $this->answer($responseId, 'nimhsmsmh', $alumni->nim, $now);
            $answers[] = $this->answer($responseId, 'kdptimsmh', $alumni->kode_pt ?? '001001', $now);
            $answers[] = $this->answer($responseId, 'tahun_lulus', (string)$alumni->graduation_year, $now);
            $answers[] = $this->answer($responseId, 'kdpstmsmh', $program->code ?? '', $now);
            $answers[] = $this->answer($responseId, 'nmmhsmsmh', $alumni->name, $now);
            $answers[] = $this->answer($responseId, 'telpomsmh', $alumni->phone ?? '', $now);
            $answers[] = $this->answer($responseId, 'emailmsmh', $alumni->email, $now);
            $answers[] = $this->answer($responseId, 'nik', $alumni->nik ?? '', $now);
            $answers[] = $this->answer($responseId, 'npwp', $alumni->npwp ?? '', $now);

            // ── Q1: f8 — Status saat ini ─────────────────────
            $statusKerja = $faker->randomElement([1, 1, 1, 3, 3, 4, 5, 2]); // weighted: more bekerja
            $answers[] = $this->answer($responseId, 'f8', (string)$statusKerja, $now);

            // ── Conditional: Bekerja(1) / Wiraswasta(3) ──────
            if (in_array($statusKerja, [1, 3])) {
                $salary = $faker->numberBetween(3000000, 20000000);
                $waitingMonths = $faker->numberBetween(0, 12);

                $answers[] = $this->answer($responseId, 'f502', (string)$waitingMonths, $now);
                $answers[] = $this->answer($responseId, 'f505', (string)$salary, $now);
                $answers[] = $this->answer($responseId, 'f5a1', $faker->randomElement($provinces), $now);
                $answers[] = $this->answer($responseId, 'f5a2', $faker->randomElement($cities), $now);
                $answers[] = $this->answer($responseId, 'f5d', (string)$faker->numberBetween(1, 3), $now);

                if ($statusKerja == 1) {
                    $f1101 = $faker->randomElement([1, 2, 3, 3, 3, 4, 6, 7]);
                    $answers[] = $this->answer($responseId, 'f1101', (string)$f1101, $now);
                    if ($f1101 == 5) {
                        $answers[] = $this->answer($responseId, 'f1102', 'Perusahaan Rintisan/Startup', $now);
                    }
                    $answers[] = $this->answer($responseId, 'f5b', $faker->randomElement($companies), $now);
                    $answers[] = $this->answer($responseId, 'f14', (string)$faker->numberBetween(1, 5), $now);
                    $answers[] = $this->answer($responseId, 'f15', (string)$faker->numberBetween(1, 4), $now);
                }

                if ($statusKerja == 3) {
                    $answers[] = $this->answer($responseId, 'f5c', (string)$faker->numberBetween(1, 3), $now);
                }

                // Employment record
                $conn->table('employment_records')->insert([
                    'alumni_id'         => $alumni->id,
                    'questionnaire_id'  => $qGlobal->id,
                    'employment_status' => $statusKerja == 1 ? 'employed' : 'entrepreneur',
                    'company_name'      => $statusKerja == 1 ? $faker->randomElement($companies) : 'Usaha Sendiri',
                    'job_title'         => $faker->jobTitle,
                    'salary_current'    => $salary,
                    'waiting_months'    => $waitingMonths,
                    'work_city'         => $faker->randomElement($cities),
                    'first_job_started_at' => $faker->dateTimeBetween('-2 years', 'now')->format('Y-m-d'),
                    'created_at'        => $now,
                    'updated_at'        => $now,
                ]);
            }

            // ── Conditional: Studi Lanjut (4) ────────────────
            if ($statusKerja == 4) {
                $answers[] = $this->answer($responseId, 'f18a', (string)$faker->numberBetween(1, 4), $now);
                $answers[] = $this->answer($responseId, 'f18b', $faker->randomElement($universities), $now);
                $answers[] = $this->answer($responseId, 'f18c', 'Magister Teknik/' . $faker->word, $now);
                $answers[] = $this->answer($responseId, 'f18d', $faker->dateTimeBetween('-1 year', 'now')->format('Y-m-d'), $now);

                $conn->table('education_records')->insert([
                    'alumni_id'        => $alumni->id,
                    'questionnaire_id' => $qGlobal->id,
                    'is_further_study' => true,
                    'institution_name' => $faker->randomElement($universities),
                    'degree'           => 'S2',
                    'major'            => 'Magister Teknik',
                    'start_year'       => 2025,
                    'created_at'       => $now,
                    'updated_at'       => $now,
                ]);
            }

            // ── Q10: f1201 — Sumber dana kuliah (wajib) ──────
            $f1201 = $faker->randomElement([1, 1, 1, 3, 4, 6]);
            $answers[] = $this->answer($responseId, 'f1201', (string)$f1201, $now);
            if ($f1201 == 7) {
                $answers[] = $this->answer($responseId, 'f1202', 'Beasiswa Daerah', $now);
            }

            // ── Q13: Kompetensi f1761-f1774 (wajib) ──────────
            foreach (['f1761','f1762','f1763','f1764','f1765','f1766','f1767','f1768','f1769','f1770','f1771','f1772','f1773','f1774'] as $fCode) {
                $answers[] = $this->answer($responseId, $fCode, (string)$faker->numberBetween(1, 5), $now);
            }

            // ── Q14: Metode Pembelajaran f21-f27 (wajib) ─────
            foreach (['f21','f22','f23','f24','f25','f26','f27'] as $fCode) {
                $answers[] = $this->answer($responseId, $fCode, (string)$faker->numberBetween(1, 5), $now);
            }

            // ── Q15: Pencarian Kerja f301 (wajib) ────────────
            $f301 = $faker->randomElement([1, 2, 3]);
            $answers[] = $this->answer($responseId, 'f301', (string)$f301, $now);
            if ($f301 == 1) {
                $answers[] = $this->answer($responseId, 'f302', (string)$faker->numberBetween(1, 6), $now);
            } elseif ($f301 == 2) {
                $answers[] = $this->answer($responseId, 'f303', (string)$faker->numberBetween(1, 12), $now);
            }

            // ── Q16: Cara mencari kerja f401-f415 ────────────
            foreach (['f401','f402','f403','f404','f405','f406','f407','f408','f409','f410','f411','f412','f413','f414','f415'] as $fCode) {
                $val = $faker->boolean(30) ? '1' : '0';
                $answers[] = $this->answer($responseId, $fCode, $val, $now);
            }

            // ── Q17-19: Statistik lamaran ────────────────────
            $f6 = $faker->numberBetween(1, 20);
            $f7 = $faker->numberBetween(1, max(1, $f6));
            $f7a = $faker->numberBetween(1, max(1, $f7));
            $answers[] = $this->answer($responseId, 'f6', (string)$f6, $now);
            $answers[] = $this->answer($responseId, 'f7', (string)$f7, $now);
            $answers[] = $this->answer($responseId, 'f7a', (string)$f7a, $now);

            // ── Q20: f1001 — Aktivitas cari kerja (wajib) ────
            $f1001 = $faker->randomElement([1, 2, 3, 4]);
            $answers[] = $this->answer($responseId, 'f1001', (string)$f1001, $now);
            if ($f1001 == 5) {
                $answers[] = $this->answer($responseId, 'f1002', 'Freelance/remote work', $now);
            }

            // ── Q21: f1601-f1613 — Alasan pekerjaan ──────────
            foreach (['f1601','f1602','f1603','f1604','f1605','f1606','f1607','f1608','f1609','f1610','f1611','f1612','f1613'] as $fCode) {
                $val = $faker->boolean(25) ? '1' : '0';
                $answers[] = $this->answer($responseId, $fCode, $val, $now);
            }

            // ── Pertanyaan Prodi (lokal — per program studi) ─
            if ($program && isset($prodiQuestionnaires[$program->id])) {
                $prodiQnr = $prodiQuestionnaires[$program->id];
                $jurusan = $program->jurusan;
                $answerDefs = $prodiAnswerMap[$jurusan] ?? [];

                // Create a separate response for the prodi questionnaire
                $prodiResponseId = $conn->table('responses')->insertGetId([
                    'questionnaire_id' => $prodiQnr->id,
                    'alumni_id'        => $alumni->id,
                    'status'           => 'submitted',
                    'submitted_at'     => $now,
                    'created_at'       => $now,
                    'updated_at'       => $now,
                ]);

                $prodiAnswers = [];

                // First question: text answer based on jurusan
                $firstKey = array_key_first($answerDefs);
                if ($firstKey && isset($answerDefs[$firstKey])) {
                    $prodiAnswers[] = $this->answer($prodiResponseId, $firstKey, $faker->randomElement($answerDefs[$firstKey]), $now);
                    // Also keep in global response for backward compat
                    $answers[] = $this->answer($responseId, $firstKey, $faker->randomElement($answerDefs[$firstKey]), $now);
                }

                // Second question: q_sertifikasi (boolean)
                $sertVal = $faker->randomElement(['1', '0']);
                $prodiAnswers[] = $this->answer($prodiResponseId, 'q_sertifikasi', $sertVal, $now);
                $answers[] = $this->answer($responseId, 'q_sertifikasi', $sertVal, $now);

                // Insert prodi answers
                if (count($prodiAnswers) > 0) {
                    $conn->table('response_answers')->insert($prodiAnswers);
                }
            }

            // Bulk insert global answers
            foreach (array_chunk($answers, 50) as $chunk) {
                $conn->table('response_answers')->insert($chunk);
            }
        }
    }

    /**
     * Helper untuk membuat array jawaban.
     */
    private function answer(int $responseId, string $code, string $value, Carbon $now): array
    {
        return [
            'response_id'   => $responseId,
            'question_code' => $code,
            'answer_text'   => $value,
            'created_at'    => $now,
            'updated_at'    => $now,
        ];
    }
}
