<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menandai tiga isian referensi sebagai lookup: f5a1 (provinsi), f5a2
 * (kab/kota), dan kdpstmsmh (kode prodi).
 *
 * MASALAH
 * -------
 * Ketiganya bertipe `short_text` — kotak ketik polos. Alumni harus mengunduh
 * berkas CSV referensi, mencari kodenya, lalu mengetik ulang. Yang tersimpan
 * akhirnya campur aduk: 172 jawaban f5a1/f5a2 yang ada sekarang seluruhnya
 * berisi NAMA hasil seeder ("Jawa Barat", "Denpasar"), bukan kode maupun id.
 *
 * Akibatnya rantai UMP putus. AnswerResolverService memang menerjemahkan
 * f5a1/f5a2 dari id ke nama, tapi karena isinya sudah berupa nama tanpa
 * prefiks, nama itu diteruskan apa adanya — sementara dim_ump.nama_provinsi
 * berisi "Prov. Jawa Barat" mengikuti provinces.name. Pencocokan di
 * findUmpByTahunProvinsi() tidak pernah kena, sehingga seluruh 1.014 baris
 * fact_tracer_study punya ump_sk NULL dan flag_above_ump NULL. Measure
 * count_above_ump/count_below_ump di Cube.js otomatis nol untuk semua data.
 *
 * PERBAIKAN
 * ---------
 * Enum question_type SENGAJA tidak diubah — menambah nilai enum di PostgreSQL
 * memaksa rewrite tabel dan memutus kompatibilitas borang lama. Penanda cukup
 * disimpan di kolom metadata yang sudah ada:
 *
 *   { "lookup": "province" }
 *   { "lookup": "city", "depends_on": "f5a1" }
 *   { "lookup": "program", "lookup_value": "code" }
 *
 * `lookup_value` menentukan kolom mana yang disimpan sebagai jawaban. Default
 * `id` untuk wilayah (dituntut AnswerResolverService), tapi Kode Prodi memakai
 * `code` karena nilai itulah yang dibaca MinistrySheetExport.
 *
 * Metadata lain pada pertanyaan yang sama (show_if dan kawan-kawan)
 * dipertahankan — migrasi ini menggabung, bukan menimpa.
 *
 * Jawaban lama yang telanjur berupa nama tidak diubah di sini.
 * AnswerResolverService diberi jalur pemulihan terpisah supaya nama tanpa
 * prefiks tetap bisa dicocokkan ke provinces/cities.
 */
return new class extends Migration
{
    /** code pertanyaan => metadata lookup yang ditambahkan. */
    private const LOOKUPS = [
        'f5a1'      => ['lookup' => 'province'],
        'f5a2'      => ['lookup' => 'city', 'depends_on' => 'f5a1'],
        'kdpstmsmh' => ['lookup' => 'program', 'lookup_value' => 'code'],
    ];

    public function up(): void
    {
        $this->apply(fn (array $meta, array $lookup) => array_merge($meta, $lookup));
    }

    public function down(): void
    {
        $this->apply(function (array $meta) {
            unset($meta['lookup'], $meta['lookup_value'], $meta['depends_on']);
            return $meta;
        });
    }

    /**
     * Berlaku untuk SEMUA kuesioner yang punya code tersebut, bukan hanya
     * kuesioner global bawaan seeder — kuesioner prodi memakai code yang sama.
     */
    private function apply(callable $mutate): void
    {
        $conn = DB::connection('oltp');

        foreach (self::LOOKUPS as $code => $lookup) {
            $questions = $conn->table('questionnaire_questions')
                ->where('code', $code)
                ->get(['id', 'metadata']);

            foreach ($questions as $q) {
                $meta = json_decode($q->metadata ?? '{}', true) ?: [];

                $conn->table('questionnaire_questions')
                    ->where('id', $q->id)
                    ->update([
                        'metadata'   => json_encode($mutate($meta, $lookup)),
                        'updated_at' => now(),
                    ]);
            }
        }
    }
};
