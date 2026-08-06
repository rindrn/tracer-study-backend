<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menambah bagian "Kontak Penilai" ke kuesioner global.
 *
 * LATAR
 * -----
 * Tabel `stakeholder_contacts` sudah ada sejak 2026_06_09 lengkap dengan
 * endpoint admin dan ekspornya, tapi isinya sampai sekarang hanya data
 * seeder — tidak pernah ada pertanyaan yang mengumpulkannya dari alumni.
 * Migrasi ini menutup celah itu: enam pertanyaan (tiga pasang nama + email)
 * yang jawabannya diproyeksikan ke `stakeholder_contacts` oleh
 * TracerStudySubmitService::persistNormalizedRecords().
 *
 * SYARAT TAMPIL
 * -------------
 * `show_if` pada f8 = [1, 3, 4] — Bekerja, Wiraswasta, Melanjutkan
 * Pendidikan. Opsi 2 (belum memungkinkan bekerja) dan 5 (sedang mencari
 * kerja) sengaja tidak masuk: alumni pada kondisi itu belum punya penilai,
 * dan kolom `alumni_status` di tabel tujuan pun hanya mengenal tiga nilai
 * yang sepadan dengan ketiga opsi tersebut.
 *
 * WAJIB ISI
 * ---------
 * is_required SENGAJA false. SubmitTracerStudyRequest::buildDynamicRules()
 * membangun aturan hanya dari kolom is_required dan tidak membaca show_if,
 * sehingga pertanyaan bersyarat yang ditandai wajib akan menolak submisi
 * alumni yang bahkan tidak melihat pertanyaannya. Seluruh pertanyaan
 * bersyarat lain di kuesioner ini memakai konvensi yang sama.
 *
 * Migrasi ini aman dijalankan ulang.
 */
return new class extends Migration
{
    private const SECTION_TITLE = 'Kontak Penilai';

    private const SECTION_DESCRIPTION = 'Tracer study juga meminta penilaian dari orang-orang yang mengenal kinerja Anda. '
        . 'Tuliskan tiga nama beserta alamat surel yang bisa kami hubungi. '
        . 'Mereka hanya akan menerima satu kuesioner singkat mengenai kompetensi Anda.';

    /**
     * [kode, teks, label pemisah, keterangan, format]
     *
     * Teks pertanyaan memuat tiga konteks sekaligus (bekerja / wiraswasta /
     * lanjut studi) karena satu pertanyaan dipakai bersama oleh ketiganya —
     * pola yang sama dengan f502. Yang membedakan penyimpanannya hanyalah
     * kolom alumni_status, yang diisi dari jawaban f8 saat submit.
     */
    private const QUESTIONS = [
        [
            'stk1_nama',
            'Tuliskan nama atasan Anda (bekerja) / rekan bisnis (wiraswasta) / dosen pembimbing (lanjut studi)',
            'Penilai 1',
            'Orang yang menilai hasil kerja Anda secara langsung.',
            null,
        ],
        [
            'stk1_email',
            'Tuliskan alamat surel Penilai 1',
            null,
            'Sesuai nama yang Anda tulis di atas.',
            'email',
        ],
        [
            'stk2_nama',
            'Tuliskan nama senior terdekat Anda (bekerja) / rekan kerja (wiraswasta) / mahasiswa senior (lanjut studi)',
            'Penilai 2',
            'Orang yang sehari-hari bekerja atau belajar berdampingan dengan Anda.',
            null,
        ],
        [
            'stk2_email',
            'Tuliskan alamat surel Penilai 2',
            null,
            'Sesuai nama yang Anda tulis di atas.',
            'email',
        ],
        [
            'stk3_nama',
            'Tuliskan nama HRD Anda (bekerja) / rekan kerja lainnya (wiraswasta) / mahasiswa seangkatan (lanjut studi)',
            'Penilai 3',
            'Orang ketiga yang dapat memberi gambaran berbeda tentang Anda.',
            null,
        ],
        [
            'stk3_email',
            'Tuliskan alamat surel Penilai 3',
            null,
            'Sesuai nama yang Anda tulis di atas.',
            'email',
        ],
    ];

    public function up(): void
    {
        $conn = DB::connection('oltp');
        $now = now();

        // Hanya kuesioner global (program_id null) — pertanyaan ini berlaku
        // untuk seluruh prodi, jadi tidak diduplikasi ke kuesioner prodi.
        $questionnaireIds = $conn->table('questionnaires')
            ->whereNull('program_id')
            ->pluck('id');

        foreach ($questionnaireIds as $qnrId) {
            $sectionId = $conn->table('questionnaire_sections')
                ->where('questionnaire_id', $qnrId)
                ->where('title', self::SECTION_TITLE)
                ->value('id');

            if (!$sectionId) {
                $nextSectionOrder = (int) $conn->table('questionnaire_sections')
                    ->where('questionnaire_id', $qnrId)
                    ->max('order_no') + 1;

                $sectionId = $conn->table('questionnaire_sections')->insertGetId([
                    'questionnaire_id' => $qnrId,
                    'title'            => self::SECTION_TITLE,
                    'description'      => self::SECTION_DESCRIPTION,
                    'order_no'         => $nextSectionOrder,
                    'is_active'        => true,
                    'created_at'       => $now,
                    'updated_at'       => $now,
                ]);
            }

            $nextQuestionOrder = (int) $conn->table('questionnaire_questions')
                ->where('questionnaire_id', $qnrId)
                ->max('order_no') + 1;

            foreach (self::QUESTIONS as $i => [$code, $text, $divider, $hint, $format]) {
                $metadata = ['show_if' => ['f8' => [1, 3, 4]]];
                if ($divider) $metadata['divider_label'] = $divider;
                if ($hint)    $metadata['description']   = $hint;
                if ($format)  $metadata['format']        = $format;

                $conn->table('questionnaire_questions')->updateOrInsert(
                    ['questionnaire_id' => $qnrId, 'code' => $code],
                    [
                        'section_id'    => $sectionId,
                        'question_text' => $text,
                        'question_type' => 'short_text',
                        'is_required'   => false,
                        'order_no'      => $nextQuestionOrder + $i,
                        'metadata'      => json_encode($metadata),
                        'created_at'    => $now,
                        'updated_at'    => $now,
                    ],
                );
            }
        }
    }

    public function down(): void
    {
        $conn = DB::connection('oltp');

        $codes = array_column(self::QUESTIONS, 0);

        // Jawaban yang sudah masuk ikut dibuang — tanpa pertanyaannya, baris
        // response_answers menjadi yatim dan tidak bisa ditafsirkan lagi.
        // Kolomnya `question_code`, bukan foreign key ke tabel pertanyaan.
        $conn->table('response_answers')->whereIn('question_code', $codes)->delete();
        $conn->table('questionnaire_questions')->whereIn('code', $codes)->delete();
        $conn->table('questionnaire_sections')->where('title', self::SECTION_TITLE)->delete();
    }
};
