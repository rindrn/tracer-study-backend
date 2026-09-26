<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menandai wajib pertanyaan yang lembar kementerian wajibkan secara bersyarat.
 *
 * MASALAH
 * -------
 * Lembar instrumen kementerian menandai sejumlah pertanyaan "(Wajib diisi)"
 * hanya pada cabang jawaban tertentu — f502 bagi yang bekerja atau
 * berwiraswasta, f18a sampai f18d bagi yang melanjutkan pendidikan, f1202 bagi
 * yang memilih sumber dana lainnya, dan seterusnya.
 *
 * Seluruhnya tersimpan dengan is_required = false. Alasannya masuk akal pada
 * masanya: SubmitTracerStudyRequest menyusun aturan dari kolom is_required
 * tanpa menoleh ke show_if, sehingga menandainya wajib akan menolak alumni yang
 * cabangnya berbeda — pertanyaan itu memang tidak pernah muncul di layar
 * mereka, jadi tidak ada isian yang bisa mereka perbaiki.
 *
 * Ongkosnya: tidak ada satu pun yang ditegakkan. Alumni yang memilih bekerja
 * dapat mengirimkan pengisian dengan f502 kosong, dan berkas ekspornya berisiko
 * ditolak portal pelaporan.
 *
 * PERBAIKAN
 * ---------
 * SubkelasValidasi kini menilai syaratnya lebih dulu — lihat
 * validateConditionallyRequired() pada SubmitTracerStudyRequest — sehingga
 * is_required aman dinaikkan. Migrasi ini menyelaraskan basis data yang sudah
 * terlanjur ada dengan QuestionnaireSeeder yang sudah diperbarui; pemasangan
 * baru memperolehnya langsung dari seeder.
 *
 * f5d ditangani terpisah karena satu-satunya yang syarat tampil dan syarat
 * wajibnya berbeda: tampil bagi yang bekerja maupun berwiraswasta, wajib hanya
 * bagi yang berwiraswasta.
 *
 * Pengisian yang sudah terkirim tidak disentuh. Yang dibuka kembali akan
 * tunduk pada aturan baru saat dikirim ulang, dan itu memang yang diinginkan.
 */
return new class extends Migration
{
    /** Wajib mengikuti show_if masing-masing — tidak perlu syarat terpisah. */
    private const WAJIB_MENGIKUTI_SHOW_IF = [
        'f502',   // bekerja atau berwiraswasta
        'f5c',    // berwiraswasta
        'f14', 'f15', // bekerja — lembar menandainya dengan asteris
        'f18a', 'f18b', 'f18c', 'f18d', // melanjutkan pendidikan
        'f1102',  // jenis instansi "Lainnya"
        'f1202',  // sumber dana "Lainnya"
        'f302',   // mulai mencari sebelum lulus
        'f303',   // mulai mencari sesudah lulus
        'f416',   // cara mencari kerja "Lainnya"
        'f1002',  // aktivitas mencari kerja "Lainnya"
        'f1614',  // alasan tidak sesuai "Lainnya"
    ];

    public function up(): void
    {
        $conn = DB::connection('oltp');

        $conn->table('questionnaire_questions')
            ->whereIn('code', self::WAJIB_MENGIKUTI_SHOW_IF)
            ->update(['is_required' => true, 'updated_at' => now()]);

        $this->setelF5d($conn, wajib: true);
    }

    public function down(): void
    {
        $conn = DB::connection('oltp');

        $conn->table('questionnaire_questions')
            ->whereIn('code', self::WAJIB_MENGIKUTI_SHOW_IF)
            ->update(['is_required' => false, 'updated_at' => now()]);

        $this->setelF5d($conn, wajib: false);
    }

    /**
     * f5d wajib hanya bagi yang berwiraswasta, padahal tampil pula bagi yang
     * bekerja. Syaratnya karena itu ditulis sebagai `required_if` tersendiri
     * alih-alih menumpang `show_if`.
     */
    private function setelF5d(\Illuminate\Database\Connection $conn, bool $wajib): void
    {
        $rows = $conn->table('questionnaire_questions')
            ->where('code', 'f5d')
            ->get(['id', 'metadata']);

        foreach ($rows as $row) {
            $meta = json_decode($row->metadata ?? '{}', true) ?: [];

            if ($wajib) {
                $meta['required_if'] = ['f8' => [3]];
            } else {
                unset($meta['required_if']);
            }

            $conn->table('questionnaire_questions')
                ->where('id', $row->id)
                ->update([
                    'is_required' => $wajib,
                    'metadata'    => json_encode($meta),
                    'updated_at'  => now(),
                ]);
        }
    }
};
