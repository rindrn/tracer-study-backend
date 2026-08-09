<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Memperbaiki f5c — "Bila berwiraswasta, apa posisi/jabatan Anda saat ini?"
 *
 * MASALAH
 * -------
 * Pertanyaan ini bertipe `number` padahal menanyakan jabatan. Niatnya memang
 * menyimpan KODE pilihan (data yang sudah masuk berisi "1", "2", "3" pada 37
 * jawaban), tapi barisan opsinya tidak pernah dibuat. Akibatnya:
 *
 *   1. Frontend merender kotak angka polos, alumni harus menebak isinya.
 *   2. SubmitTracerStudyRequest memberi aturan `numeric`, sehingga jawaban
 *      teks apa pun ditolak.
 *   3. AnswerResolverService hanya menerjemahkan kode -> label untuk tipe
 *      single_choice/multiple_choice. Karena f5c bertipe number, kodenya
 *      lewat apa adanya dan public.dim_wirausaha.jabatan terisi "1"/"2"/"3"
 *      -- bandingkan kolom label_tingkat_instansi di sebelahnya yang tampil
 *      sebagai teks terbaca.
 *
 * PERBAIKAN
 * ---------
 * Ubah tipe menjadi single_choice dan tambahkan 4 opsi, mengikuti pola f8
 * (status alumni): option_code berupa angka, option_label berupa teks.
 *
 * Kode angka SENGAJA dipertahankan supaya 37 jawaban yang sudah tersimpan
 * tetap sah dan tidak perlu migrasi data. show_if pada pertanyaan lain yang
 * merujuk f5c juga tidak terpengaruh.
 *
 * Setelah migrasi ini dijalankan, ETL perlu dijalankan ulang (php artisan
 * etl:run) supaya dim_wirausaha.jabatan terisi label, bukan kode.
 */
return new class extends Migration
{
    /** Urutan menentukan arti kode — jangan diubah tanpa memigrasi data lama. */
    private const POSITIONS = [
        1 => ['Founder', 'Pendiri utama usaha'],
        2 => ['Co-Founder', 'Salah satu pendiri bersama rekan lain'],
        3 => ['Staff', 'Pekerja atau karyawan di usaha tersebut'],
        4 => ['Freelance / Kerja Lepas', 'Pekerja paruh waktu atau lepas'],
    ];

    public function up(): void
    {
        $conn = DB::connection('oltp');

        $questions = $conn->table('questionnaire_questions')
            ->where('code', 'f5c')
            ->get(['id', 'metadata']);

        foreach ($questions as $q) {
            $conn->table('questionnaire_questions')
                ->where('id', $q->id)
                ->update([
                    'question_type' => 'single_choice',
                    // Keterangan panjang tiap opsi disimpan di metadata supaya
                    // frontend bisa menampilkannya sebagai teks kecil di bawah
                    // label, tanpa membuat dropdown-nya jadi berat.
                    'metadata'      => json_encode(array_merge(
                        json_decode($q->metadata ?? '{}', true) ?: [],
                        [
                            'option_hints' => array_map(
                                fn (array $o) => $o[1],
                                array_combine(
                                    array_map('strval', array_keys(self::POSITIONS)),
                                    array_values(self::POSITIONS),
                                ),
                            ),
                        ],
                    )),
                    'updated_at'    => now(),
                ]);

            foreach (self::POSITIONS as $code => [$label]) {
                // updateOrInsert supaya migrasi aman dijalankan ulang.
                $conn->table('questionnaire_options')->updateOrInsert(
                    ['question_id' => $q->id, 'option_code' => (string) $code],
                    [
                        'option_label' => $label,
                        'option_value' => null,
                        'order_no'     => $code,
                        'is_active'    => true,
                        'is_hidden'    => false,
                        'created_at'   => now(),
                        'updated_at'   => now(),
                    ],
                );
            }
        }
    }

    public function down(): void
    {
        $conn = DB::connection('oltp');

        $ids = $conn->table('questionnaire_questions')->where('code', 'f5c')->pluck('id');

        $conn->table('questionnaire_options')
            ->whereIn('question_id', $ids)
            ->whereIn('option_code', array_map('strval', array_keys(self::POSITIONS)))
            ->delete();

        foreach ($ids as $id) {
            $meta = json_decode(
                $conn->table('questionnaire_questions')->where('id', $id)->value('metadata') ?? '{}',
                true,
            ) ?: [];
            unset($meta['option_hints']);

            $conn->table('questionnaire_questions')->where('id', $id)->update([
                'question_type' => 'number',
                'metadata'      => json_encode($meta),
                'updated_at'    => now(),
            ]);
        }
    }
};
