<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Menyelaraskan kode opsi f1101 (jenis instansi) dengan lembar kementerian.
 *
 * MASALAH
 * -------
 * Tabel opsi hasil dump produksi menomori f1101 berurutan 1..7:
 *
 *     5 = BUMN/BUMD   6 = Institusi/Organisasi Multilateral   7 = Lainnya
 *
 * Jawaban alumninya tidak. Seluruh jawaban asli memakai kode kementerian,
 * yang juga dipakai QuestionnaireSeeder:
 *
 *     5 = Lainnya     6 = BUMN/BUMD   7 = Institusi/Organisasi Multilateral
 *
 * Buktinya ada di isi jawaban, bukan di tabel opsi:
 *   - kode 6 (410 jawaban) berisi Telkom, PLN, Mandiri, BSI, BRI, Pertamina,
 *     Biofarma -- seluruhnya BUMN;
 *   - kode 5 tidak pernah muncul sama sekali, karena isian "Lainnya" dari
 *     portal kementerian tersimpan sebagai teks ketikannya, bukan kode.
 *
 * Migrasi 2026_09_07_000002 menyimpulkan sebaliknya (410 jawaban bernilai 6
 * dianggap Multilateral) dan sengaja membiarkan penomoran berurutan. Akibatnya
 * dashboard mencatat ~410 pegawai BUMN sebagai "Institusi/Organisasi
 * Multilateral", pemilih Multilateral sebagai "Lainnya", dan ekspor ke portal
 * kementerian mengirim kode yang artinya bergeser.
 *
 * PERBAIKAN
 * ---------
 * 1. Kode opsi f1101 ditulis ulang menurut LABEL-nya, sehingga migrasi ini
 *    benar di basis data hasil seeder (sudah sesuai, tidak berubah apa-apa)
 *    maupun hasil restore produksi.
 * 2. Jawaban yang masuk lewat aplikasi selama penomoran berurutan berlaku
 *    ikut diterjemahkan:
 *      - '5' selalu berarti BUMN/BUMD (data impor tidak pernah memakai 5)
 *        -> '6';
 *      - '7' yang disertai isian f1102 berarti "Lainnya" -> '5'.
 *    Jawaban '6' hasil aplikasi (Multilateral menurut penomoran lama) tidak
 *    dapat dibedakan dari '6' hasil impor (BUMN), jadi dibiarkan.
 * 3. Pemicu f1102 diarahkan ke kode "Lainnya" yang baru.
 *
 * Setiap jawaban yang diterjemahkan dicatat di tabel arsip supaya migrasi ini
 * dapat dibatalkan dengan tepat.
 *
 * SESUDAHNYA
 * ----------
 * Jalankan php artisan etl:run, karena dim_perusahaan masih memuat label lama.
 */
return new class extends Migration
{
    protected $connection = 'oltp';

    private const ARSIP = 'f1101_arsip_kode_berurutan';

    /** label opsi (huruf kecil) => [kode berurutan lama, kode kementerian] */
    private const KODE = [
        'bumn/bumd'                         => ['5', '6'],
        'institusi/organisasi multilateral' => ['6', '7'],
        'lainnya, tuliskan'                 => ['7', '5'],
    ];

    public function up(): void
    {
        if (! Schema::connection('oltp')->hasTable(self::ARSIP)) {
            Schema::connection('oltp')->create(self::ARSIP, function (Blueprint $table) {
                $table->id();
                // 'opsi' -> ref_id = questionnaire_questions.id yang kodenya ditulis ulang
                // 'jawaban' -> ref_id = response_answers.id yang diterjemahkan
                $table->string('jenis', 10);
                $table->unsignedBigInteger('ref_id')->index();
                $table->string('kode_lama', 10)->nullable();
                $table->string('kode_baru', 10)->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }

        foreach ($this->pertanyaanF1101() as $questionnaireId => $questionId) {
            if (! $this->masihBerurutan($questionId)) {
                continue;
            }

            $this->terjemahkanJawaban($questionnaireId);
            $this->tulisUlangKode($questionId, toKementerian: true);

            DB::connection('oltp')->table(self::ARSIP)->insert([
                'jenis' => 'opsi', 'ref_id' => $questionId, 'created_at' => now(),
            ]);
        }

        $this->setPemicuF1102();
    }

    /**
     * Hanya membalik yang tercatat di arsip: pemasangan hasil seeder, yang
     * sejak awal memakai kode kementerian, tidak tersentuh up() sehingga
     * tidak boleh dibalik ke penomoran berurutan.
     */
    public function down(): void
    {
        $conn = DB::connection('oltp');

        if (! Schema::connection('oltp')->hasTable(self::ARSIP)) {
            return;
        }

        foreach ($conn->table(self::ARSIP)->get() as $arsip) {
            if ($arsip->jenis === 'opsi') {
                $this->tulisUlangKode($arsip->ref_id, toKementerian: false);
            } else {
                $conn->table('response_answers')
                    ->where('id', $arsip->ref_id)
                    ->update(['answer_text' => $arsip->kode_lama, 'updated_at' => now()]);
            }
        }

        Schema::connection('oltp')->drop(self::ARSIP);

        $this->setPemicuF1102();
    }

    /** @return array<int, int> questionnaire_id => id pertanyaan f1101 */
    private function pertanyaanF1101(): array
    {
        return DB::connection('oltp')->table('questionnaire_questions')
            ->where('code', 'f1101')
            ->pluck('id', 'questionnaire_id')
            ->all();
    }

    /** Benar bila opsi BUMN/BUMD pada pertanyaan ini masih berkode 5. */
    private function masihBerurutan(int $questionId): bool
    {
        return DB::connection('oltp')->table('questionnaire_options')
            ->where('question_id', $questionId)
            ->get(['option_code', 'option_label'])
            ->contains(fn ($o) => mb_strtolower(trim($o->option_label)) === 'bumn/bumd' && $o->option_code === '5');
    }

    private function terjemahkanJawaban(int $questionnaireId): void
    {
        $conn = DB::connection('oltp');

        $jawaban = $conn->table('response_answers as a')
            ->join('responses as r', 'r.id', '=', 'a.response_id')
            ->where('r.questionnaire_id', $questionnaireId)
            ->where('a.question_code', 'f1101')
            ->whereIn('a.answer_text', ['5', '7'])
            ->get(['a.id', 'a.response_id', 'a.answer_text']);

        $adaF1102 = $conn->table('response_answers')
            ->whereIn('response_id', $jawaban->pluck('response_id'))
            ->where('question_code', 'f1102')
            ->whereNotNull('answer_text')
            ->where('answer_text', '!=', '')
            ->pluck('response_id')
            ->flip();

        foreach ($jawaban as $a) {
            $baru = match (true) {
                $a->answer_text === '5'                                   => '6',
                $a->answer_text === '7' && $adaF1102->has($a->response_id) => '5',
                default                                                   => null,
            };

            if ($baru === null) {
                continue;
            }

            $conn->table(self::ARSIP)->insert([
                'jenis'      => 'jawaban',
                'ref_id'     => $a->id,
                'kode_lama'  => $a->answer_text,
                'kode_baru'  => $baru,
                'created_at' => now(),
            ]);

            $conn->table('response_answers')
                ->where('id', $a->id)
                ->update(['answer_text' => $baru, 'updated_at' => now()]);
        }
    }

    /**
     * Kode ditukar lewat nilai sementara karena ada unique index
     * (question_id, option_code) -- 5/6/7 saling bertukar tempat.
     */
    private function tulisUlangKode(int $questionId, bool $toKementerian): void
    {
        $conn = DB::connection('oltp');

        $opsi = $conn->table('questionnaire_options')
            ->where('question_id', $questionId)
            ->get(['id', 'option_label']);

        $target = [];
        foreach ($opsi as $o) {
            $kode = self::KODE[mb_strtolower(trim($o->option_label))] ?? null;
            if ($kode !== null) {
                $target[$o->id] = $toKementerian ? $kode[1] : $kode[0];
            }
        }

        foreach (array_keys($target) as $id) {
            $conn->table('questionnaire_options')->where('id', $id)
                ->update(['option_code' => "tmp-{$id}"]);
        }
        foreach ($target as $id => $kode) {
            $conn->table('questionnaire_options')->where('id', $id)
                ->update(['option_code' => $kode, 'updated_at' => now()]);
        }
    }

    /** f1102 tampil bila f1101 = kode opsi "Lainnya, tuliskan" saat ini. */
    private function setPemicuF1102(): void
    {
        $conn = DB::connection('oltp');

        foreach ($this->pertanyaanF1101() as $questionnaireId => $questionId) {
            $kode = $conn->table('questionnaire_options')
                ->where('question_id', $questionId)
                ->get(['option_code', 'option_label'])
                ->first(fn ($o) => mb_strtolower(trim($o->option_label)) === 'lainnya, tuliskan')
                ?->option_code;

            if ($kode === null) {
                continue;
            }

            $f1102 = $conn->table('questionnaire_questions')
                ->where('questionnaire_id', $questionnaireId)
                ->where('code', 'f1102')
                ->first(['id', 'metadata']);

            if ($f1102 === null) {
                continue;
            }

            $meta = json_decode($f1102->metadata ?? '{}', true) ?: [];
            $meta['show_if'] = ['f1101' => [(int) $kode]];

            $conn->table('questionnaire_questions')
                ->where('id', $f1102->id)
                ->update(['metadata' => json_encode($meta), 'updated_at' => now()]);
        }
    }
};
