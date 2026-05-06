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
        $conn = DB::connection('oltp');

        // ═══════════════════════════════════════════════════════
        // 1. KUESIONER NASIONAL (KEMENTRIAN) — program_id = null
        // ═══════════════════════════════════════════════════════
        $qGlobalId = $conn->table('questionnaires')->insertGetId([
            'code'         => 'DIKTI_2026',
            'title'        => 'Kuesioner Tracer Study Nasional 2026',
            'description'  => 'Kuesioner wajib dari Kementerian Pendidikan untuk seluruh lulusan perguruan tinggi.',
            'period_year'  => 2026,
            'version'      => 1,
            'status'       => 'published',
            'program_id'   => null,
            'published_at' => $now,
            'created_at'   => $now,
            'updated_at'   => $now,
        ]);

        // ── Sections ─────────────────────────────────────────
        $sections = [
            ['title' => 'Status & Pekerjaan',                  'order_no' => 1],
            ['title' => 'Studi Lanjut',                        'order_no' => 2],
            ['title' => 'Sumber Dana Kuliah',                  'order_no' => 3],
            ['title' => 'Kesesuaian Pekerjaan & Pendidikan',   'order_no' => 4],
            ['title' => 'Kompetensi',                          'order_no' => 5],
            ['title' => 'Metode Pembelajaran',                 'order_no' => 6],
            ['title' => 'Pencarian Kerja',                     'order_no' => 7],
            ['title' => 'Statistik Lamaran',                   'order_no' => 8],
            ['title' => 'Aktivitas & Alasan',                  'order_no' => 9],
        ];

        $sectionIds = [];
        foreach ($sections as $s) {
            $sectionIds[$s['order_no']] = $conn->table('questionnaire_sections')->insertGetId([
                'questionnaire_id' => $qGlobalId,
                'title'            => $s['title'],
                'order_no'         => $s['order_no'],
                'is_active'        => true,
                'created_at'       => $now,
                'updated_at'       => $now,
            ]);
        }

        // ── Helper: insert question + options ────────────────
        $orderCounter = 0;
        $insertQuestion = function (int $sectionNo, string $code, string $text, string $type, bool $required, ?array $meta = null, ?array $options = null) use ($conn, $qGlobalId, $sectionIds, $now, &$orderCounter) {
            $orderCounter++;
            $qId = $conn->table('questionnaire_questions')->insertGetId([
                'questionnaire_id' => $qGlobalId,
                'section_id'       => $sectionIds[$sectionNo],
                'code'             => $code,
                'question_text'    => $text,
                'question_type'    => $type,
                'is_required'      => $required,
                'order_no'         => $orderCounter,
                'metadata'         => $meta ? json_encode($meta) : null,
                'created_at'       => $now,
                'updated_at'       => $now,
            ]);

            if ($options) {
                foreach ($options as $i => $opt) {
                    $conn->table('questionnaire_options')->insert([
                        'question_id'  => $qId,
                        'option_code'  => $opt[0],
                        'option_label' => $opt[1],
                        'order_no'     => $i + 1,
                        'is_active'    => true,
                        'created_at'   => $now,
                        'updated_at'   => $now,
                    ]);
                }
            }

            return $qId;
        };

        // ═══════════════════════════════════════════════════════
        // SECTION 1: STATUS & PEKERJAAN
        // ═══════════════════════════════════════════════════════

        // Q1 — f8
        $insertQuestion(1, 'f8', 'Jelaskan status Anda saat ini?', 'single_choice', true, null, [
            ['1', 'Bekerja (full time / part time)'],
            ['2', 'Belum memungkinkan bekerja'],
            ['3', 'Wiraswasta'],
            ['4', 'Melanjutkan Pendidikan'],
            ['5', 'Tidak kerja tetapi sedang mencari kerja'],
        ]);

        // Q2 — f502
        $insertQuestion(1, 'f502', 'Dalam berapa bulan Anda mendapatkan pekerjaan pertama? / Dalam berapa bulan setelah lulus Anda memulai wiraswasta?', 'number', false, ['show_if' => ['f8' => [1, 3]]]);

        // Q3 — f505
        $insertQuestion(1, 'f505', 'Berapa rata-rata pendapatan Anda per bulan? (take home pay)', 'number', false, ['show_if' => ['f8' => [1, 3]]]);

        // Q4 — f5a1
        $insertQuestion(1, 'f5a1', 'Dimana lokasi tempat Anda bekerja? (Provinsi)', 'short_text', false, ['show_if' => ['f8' => [1, 3]]]);

        // Q4 — f5a2
        $insertQuestion(1, 'f5a2', 'Dimana lokasi tempat Anda bekerja? (Kota/Kabupaten)', 'short_text', false, ['show_if' => ['f8' => [1, 3]]]);

        // Q5 — f1101
        $insertQuestion(1, 'f1101', 'Apa jenis perusahaan/instansi/institusi tempat Anda bekerja sekarang?', 'single_choice', false, ['show_if' => ['f8' => [1]]], [
            ['1', 'Instansi pemerintah'],
            ['2', 'Organisasi non-profit/Lembaga Swadaya Masyarakat'],
            ['3', 'Perusahaan swasta'],
            ['4', 'Wiraswasta/perusahaan sendiri'],
            ['6', 'BUMN/BUMD'],
            ['7', 'Institusi/Organisasi Multilateral'],
            ['5', 'Lainnya, tuliskan'],
        ]);

        // Q5b — f1102
        $insertQuestion(1, 'f1102', 'Sebutkan jenis perusahaan/instansi lainnya', 'short_text', false, ['show_if' => ['f1101' => [5]]]);

        // Q6 — f5b
        $insertQuestion(1, 'f5b', 'Apa nama perusahaan/kantor tempat Anda bekerja?', 'short_text', false, ['show_if' => ['f8' => [1]]]);

        // Q7 — f5c
        $insertQuestion(1, 'f5c', 'Bila berwiraswasta, apa posisi/jabatan Anda saat ini?', 'number', false, ['show_if' => ['f8' => [3]]]);

        // Q8 — f5d
        $insertQuestion(1, 'f5d', 'Apa tingkat tempat kerja Anda?', 'single_choice', false, ['show_if' => ['f8' => [1, 3]]], [
            ['1', 'Lokal/Wilayah/Wiraswasta tidak berbadan hukum'],
            ['2', 'Nasional/Wiraswasta berbadan hukum'],
            ['3', 'Multinasional/Internasional'],
        ]);

        // ═══════════════════════════════════════════════════════
        // SECTION 2: STUDI LANJUT
        // ═══════════════════════════════════════════════════════

        // Q9a — f18a
        $insertQuestion(2, 'f18a', 'Sumber biaya untuk studi lanjut?', 'single_choice', false, ['show_if' => ['f8' => [4]]], [
            ['1', 'Biaya Sendiri/Keluarga'],
            ['2', 'Beasiswa'],
            ['3', 'Asisten/Mengajar'],
            ['4', 'Lainnya'],
        ]);

        // Q9b — f18b
        $insertQuestion(2, 'f18b', 'Perguruan Tinggi tempat studi lanjut?', 'short_text', false, ['show_if' => ['f8' => [4]]]);

        // Q9c — f18c
        $insertQuestion(2, 'f18c', 'Program Studi studi lanjut?', 'short_text', false, ['show_if' => ['f8' => [4]]]);

        // Q9d — f18d
        $insertQuestion(2, 'f18d', 'Tanggal Masuk studi lanjut? (dd/mm/yyyy)', 'date', false, ['show_if' => ['f8' => [4]]]);

        // ═══════════════════════════════════════════════════════
        // SECTION 3: SUMBER DANA KULIAH
        // ═══════════════════════════════════════════════════════

        // Q10 — f1201
        $insertQuestion(3, 'f1201', 'Sebutkan sumber dana dalam pembiayaan kuliah Anda? (bukan ketika Studi Lanjut)', 'single_choice', true, null, [
            ['1', 'Biaya Sendiri/Keluarga'],
            ['2', 'Beasiswa ADIK'],
            ['3', 'Beasiswa BIDIKMISI'],
            ['4', 'Beasiswa PPA'],
            ['5', 'Beasiswa AFIRMASI'],
            ['6', 'Beasiswa Perusahaan/Swasta'],
            ['7', 'Lainnya, tuliskan'],
        ]);

        // Q10b — f1202
        $insertQuestion(3, 'f1202', 'Sebutkan sumber dana pembiayaan kuliah lainnya', 'short_text', false, ['show_if' => ['f1201' => [7]]]);

        // ═══════════════════════════════════════════════════════
        // SECTION 4: KESESUAIAN PEKERJAAN & PENDIDIKAN
        // ═══════════════════════════════════════════════════════

        // Q11 — f14
        $insertQuestion(4, 'f14', 'Seberapa erat hubungan bidang studi dengan pekerjaan Anda?', 'single_choice', false, ['show_if' => ['f8' => [1]]], [
            ['1', 'Sangat Erat'],
            ['2', 'Erat'],
            ['3', 'Cukup Erat'],
            ['4', 'Kurang Erat'],
            ['5', 'Tidak Sama Sekali'],
        ]);

        // Q12 — f15
        $insertQuestion(4, 'f15', 'Tingkat pendidikan apa yang paling tepat/sesuai untuk pekerjaan Anda saat ini?', 'single_choice', false, ['show_if' => ['f8' => [1]]], [
            ['1', 'Setingkat Lebih Tinggi'],
            ['2', 'Tingkat yang Sama'],
            ['3', 'Setingkat Lebih Rendah'],
            ['4', 'Tidak Perlu Pendidikan Tinggi'],
        ]);

        // ═══════════════════════════════════════════════════════
        // SECTION 5: KOMPETENSI (f1761–f1774)
        // ═══════════════════════════════════════════════════════

        $competencies = [
            ['f1761', 'f1762', 'Etika'],
            ['f1763', 'f1764', 'Keahlian berdasarkan bidang ilmu'],
            ['f1765', 'f1766', 'Bahasa Inggris'],
            ['f1767', 'f1768', 'Penggunaan Teknologi Informasi'],
            ['f1769', 'f1770', 'Komunikasi'],
            ['f1771', 'f1772', 'Kerja sama tim'],
            ['f1773', 'f1774', 'Pengembangan diri'],
        ];

        foreach ($competencies as $comp) {
            $insertQuestion(5, $comp[0],
                "Pada saat lulus, pada tingkat mana kompetensi {$comp[2]} Anda kuasai? (A)",
                'number', true, ['scale_min' => 1, 'scale_max' => 5, 'competency' => $comp[2], 'dimension' => 'saat_lulus']);

            $insertQuestion(5, $comp[1],
                "Pada saat ini, pada tingkat mana kompetensi {$comp[2]} diperlukan dalam pekerjaan? (B)",
                'number', true, ['scale_min' => 1, 'scale_max' => 5, 'competency' => $comp[2], 'dimension' => 'saat_ini']);
        }

        // ═══════════════════════════════════════════════════════
        // SECTION 6: METODE PEMBELAJARAN (f21–f27)
        // ═══════════════════════════════════════════════════════

        $methods = [
            ['f21', 'Perkuliahan'],
            ['f22', 'Demonstrasi'],
            ['f23', 'Partisipasi dalam proyek riset'],
            ['f24', 'Magang'],
            ['f25', 'Praktikum'],
            ['f26', 'Kerja Lapangan'],
            ['f27', 'Diskusi'],
        ];

        foreach ($methods as $m) {
            $insertQuestion(6, $m[0],
                "Menurut Anda seberapa besar penekanan pada metode pembelajaran \"{$m[1]}\" di program studi Anda?",
                'number', true, ['scale_min' => 1, 'scale_max' => 5, 'method' => $m[1]]);
        }

        // ═══════════════════════════════════════════════════════
        // SECTION 7: PENCARIAN KERJA
        // ═══════════════════════════════════════════════════════

        // Q15 — f301
        $insertQuestion(7, 'f301', 'Kapan Anda mulai mencari pekerjaan? Mohon pekerjaan sambilan tidak dimasukkan.', 'single_choice', true, null, [
            ['1', 'Kira-kira __ bulan sebelum lulus'],
            ['2', 'Kira-kira __ bulan sesudah lulus'],
            ['3', 'Saya tidak mencari kerja'],
        ]);

        // f302
        $insertQuestion(7, 'f302', 'Kira-kira berapa bulan sebelum lulus Anda mulai mencari pekerjaan?', 'number', false, ['show_if' => ['f301' => [1]]]);

        // f303
        $insertQuestion(7, 'f303', 'Kira-kira berapa bulan sesudah lulus Anda mulai mencari pekerjaan?', 'number', false, ['show_if' => ['f301' => [2]]]);

        // Q16 — f401-f416 (Cara mencari pekerjaan — multi-select booleans)
        $jobSearchMethods = [
            ['f401', 'Melalui iklan di koran/majalah, brosur'],
            ['f402', 'Melamar ke perusahaan tanpa mengetahui lowongan yang ada'],
            ['f403', 'Pergi ke bursa/pameran kerja'],
            ['f404', 'Mencari lewat internet/iklan online/milis'],
            ['f405', 'Dihubungi oleh perusahaan'],
            ['f406', 'Menghubungi Kemenakertrans'],
            ['f407', 'Menghubungi agen tenaga kerja komersial/swasta'],
            ['f408', 'Memperoleh informasi dari pusat/kantor pengembangan karir fakultas/universitas'],
            ['f409', 'Menghubungi kantor kemahasiswaan/hubungan alumni'],
            ['f410', 'Membangun jejaring (network) sejak masih kuliah'],
            ['f411', 'Melalui relasi (misalnya dosen, orang tua, saudara, teman, dll.)'],
            ['f412', 'Membangun bisnis sendiri'],
            ['f413', 'Melalui penempatan kerja atau magang'],
            ['f414', 'Bekerja di tempat yang sama dengan tempat kerja semasa kuliah'],
            ['f415', 'Lainnya'],
        ];

        foreach ($jobSearchMethods as $jsm) {
            $insertQuestion(7, $jsm[0],
                "Bagaimana Anda mencari pekerjaan tersebut? — {$jsm[1]}",
                'boolean', false);
        }

        // f416
        $insertQuestion(7, 'f416', 'Sebutkan cara lainnya dalam mencari pekerjaan', 'short_text', false, ['show_if' => ['f415' => [1]]]);

        // ═══════════════════════════════════════════════════════
        // SECTION 8: STATISTIK LAMARAN
        // ═══════════════════════════════════════════════════════

        // Q17 — f6
        $insertQuestion(8, 'f6', 'Berapa perusahaan/instansi yang sudah Anda lamar (lewat surat atau e-mail) sebelum Anda memperoleh pekerjaan pertama?', 'number', false);

        // Q18 — f7
        $insertQuestion(8, 'f7', 'Berapa banyak perusahaan/instansi yang merespons lamaran Anda?', 'number', false);

        // Q19 — f7a
        $insertQuestion(8, 'f7a', 'Berapa banyak perusahaan/instansi yang mengundang Anda untuk wawancara?', 'number', false);

        // ═══════════════════════════════════════════════════════
        // SECTION 9: AKTIVITAS & ALASAN
        // ═══════════════════════════════════════════════════════

        // Q20 — f1001
        $insertQuestion(9, 'f1001', 'Apakah Anda aktif mencari pekerjaan dalam 4 minggu terakhir? Pilih satu jawaban.', 'single_choice', true, null, [
            ['1', 'Tidak'],
            ['2', 'Tidak, tapi saya sedang menunggu hasil lamaran kerja'],
            ['3', 'Ya, saya akan mulai bekerja dalam 2 minggu ke depan'],
            ['4', 'Ya, tapi saya belum pasti akan bekerja dalam 2 minggu ke depan'],
            ['5', 'Lainnya'],
        ]);

        // f1002
        $insertQuestion(9, 'f1002', 'Sebutkan aktivitas lainnya dalam mencari pekerjaan', 'short_text', false, ['show_if' => ['f1001' => [5]]]);

        // Q21 — f1601-f1614 (Alasan pekerjaan tidak sesuai — multi-select)
        $mismatchReasons = [
            ['f1601', 'Pertanyaan tidak sesuai; pekerjaan saya sekarang sudah sesuai dengan pendidikan saya'],
            ['f1602', 'Saya belum mendapatkan pekerjaan yang lebih sesuai'],
            ['f1603', 'Di pekerjaan ini saya memperoleh prospek karir yang baik'],
            ['f1604', 'Saya lebih suka bekerja di area pekerjaan yang tidak ada hubungannya dengan pendidikan saya'],
            ['f1605', 'Saya dipromosikan ke posisi yang kurang berhubungan dengan pendidikan saya dibanding posisi sebelumnya'],
            ['f1606', 'Saya dapat memperoleh pendapatan yang lebih tinggi di pekerjaan ini'],
            ['f1607', 'Pekerjaan saya saat ini lebih aman/terjamin/secure'],
            ['f1608', 'Pekerjaan saya saat ini lebih menarik'],
            ['f1609', 'Pekerjaan saya saat ini lebih memungkinkan saya mengambil pekerjaan tambahan/jadwal yang fleksibel, dll.'],
            ['f1610', 'Pekerjaan saya saat ini lokasinya lebih dekat dari rumah saya'],
            ['f1611', 'Pekerjaan saya saat ini dapat lebih menjamin kebutuhan keluarga saya'],
            ['f1612', 'Pada awal meniti karir ini, saya harus menerima pekerjaan yang tidak berhubungan dengan pendidikan saya'],
            ['f1613', 'Lainnya'],
        ];

        foreach ($mismatchReasons as $mr) {
            $insertQuestion(9, $mr[0],
                "Jika menurut Anda pekerjaan saat ini tidak sesuai dengan pendidikan, mengapa Anda mengambilnya? — {$mr[1]}",
                'boolean', false);
        }

        // f1614
        $insertQuestion(9, 'f1614', 'Sebutkan alasan lainnya mengambil pekerjaan yang tidak sesuai pendidikan', 'short_text', false, ['show_if' => ['f1613' => [1]]]);

        // ═══════════════════════════════════════════════════════
        // 2. KUESIONER LOKAL — Contoh untuk Prodi Teknik Informatika D4
        // ═══════════════════════════════════════════════════════
        $tiProgram = $conn->table('programs')->where('code', 'TI')->first();

        if ($tiProgram) {
            $qLokalId = $conn->table('questionnaires')->insertGetId([
                'code'         => 'TI_2026',
                'title'        => 'Kuesioner Tambahan Lulusan Teknik Informatika',
                'description'  => 'Pertanyaan khusus dari Program Studi D4 Teknik Informatika POLBAN.',
                'period_year'  => 2026,
                'version'      => 1,
                'status'       => 'published',
                'program_id'   => $tiProgram->id,
                'published_at' => $now,
                'created_at'   => $now,
                'updated_at'   => $now,
            ]);

            $conn->table('questionnaire_questions')->insert([
                [
                    'questionnaire_id' => $qLokalId,
                    'code'             => 'q_framework',
                    'question_text'    => 'Framework Web apa yang paling sering Anda gunakan di tempat kerja?',
                    'question_type'    => 'short_text',
                    'is_required'      => false,
                    'order_no'         => 1,
                    'created_at'       => $now,
                    'updated_at'       => $now,
                ],
                [
                    'questionnaire_id' => $qLokalId,
                    'code'             => 'q_sertifikasi',
                    'question_text'    => 'Apakah Anda memiliki sertifikasi IT profesional (Misal: AWS, CCNA)?',
                    'question_type'    => 'boolean',
                    'is_required'      => false,
                    'order_no'         => 2,
                    'created_at'       => $now,
                    'updated_at'       => $now,
                ],
            ]);
        }
    }
}
