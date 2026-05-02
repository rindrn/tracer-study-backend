<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class QuestionnaireSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        // 1. Dapatkan ID Prodi TI untuk contoh kuesioner lokal
        $tiProgram = DB::connection('oltp')->table('programs')->where('code', 'TI')->first();

        // 2. Buat Kuesioner
        $qGlobalId = DB::connection('oltp')->table('questionnaires')->insertGetId([
            'code' => 'DIKTI_2026',
            'title' => 'Kuesioner Tracer Study Nasional 2026',
            'period_year' => 2026,
            'version' => 1,
            'status' => 'published',
            'program_id' => null, // Milik kementrian
            'published_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $qLokalId = DB::connection('oltp')->table('questionnaires')->insertGetId([
            'code' => 'TI_2026',
            'title' => 'Kuesioner Tambahan Lulusan Teknik Informatika',
            'period_year' => 2026,
            'version' => 1,
            'status' => 'published',
            'program_id' => $tiProgram ? $tiProgram->id : null, // Milik prodi TI
            'published_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 3. Buat Pertanyaan Kementrian (Global)
        $questionsGlobal = [
            [
                'questionnaire_id' => $qGlobalId,
                'code' => 'f8',
                'question_text' => 'Jelaskan status Anda saat ini?',
                'question_type' => 'single_choice',
                'is_required' => true,
                'order_no' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'questionnaire_id' => $qGlobalId,
                'code' => 'f502',
                'question_text' => 'Berapa rata-rata pendapatan Anda per bulan? (Dalam Rupiah)',
                'question_type' => 'number',
                'is_required' => false,
                'order_no' => 2,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];
        DB::connection('oltp')->table('questionnaire_questions')->insert($questionsGlobal);

        // Opsi untuk f8
        $f8Id = DB::connection('oltp')->table('questionnaire_questions')->where('code', 'f8')->first()->id;
        DB::connection('oltp')->table('questionnaire_options')->insert([
            ['question_id' => $f8Id, 'option_code' => '1', 'option_label' => 'Bekerja (full time/part time)', 'order_no' => 1],
            ['question_id' => $f8Id, 'option_code' => '3', 'option_label' => 'Wiraswasta', 'order_no' => 2],
            ['question_id' => $f8Id, 'option_code' => '4', 'option_label' => 'Melanjutkan Pendidikan', 'order_no' => 3],
            ['question_id' => $f8Id, 'option_code' => '5', 'option_label' => 'Tidak Bekerja / Sedang Mencari Kerja', 'order_no' => 4],
        ]);

        // 4. Buat Pertanyaan Lokal (Prodi TI)
        DB::connection('oltp')->table('questionnaire_questions')->insert([
            [
                'questionnaire_id' => $qLokalId,
                'code' => 'q_framework',
                'question_text' => 'Framework Web apa yang paling sering Anda gunakan di tempat kerja?',
                'question_type' => 'short_text',
                'is_required' => false,
                'order_no' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'questionnaire_id' => $qLokalId,
                'code' => 'q_sertifikasi',
                'question_text' => 'Apakah Anda memiliki sertifikasi IT profesional (Misal: AWS, CCNA)?',
                'question_type' => 'boolean',
                'is_required' => false,
                'order_no' => 2,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        ]);
    }
}
