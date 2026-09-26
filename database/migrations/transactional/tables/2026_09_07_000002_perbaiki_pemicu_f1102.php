<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Memperbaiki pemicu f1102 — "Sebutkan jenis perusahaan/instansi lainnya".
 *
 * MASALAH
 * -------
 * f1102 muncul bila f1101 bernilai 5. Pada lembar kementerian angka 5 memang
 * "Lainnya, tuliskan", dan QuestionnaireSeeder mengikutinya. Data produksi
 * tidak: di sana opsi f1101 dinomori berurutan 1..7, sehingga
 *
 *     5 = BUMN/BUMD
 *     6 = Institusi/Organisasi Multilateral
 *     7 = Lainnya, tuliskan
 *
 * Akibatnya pada pemasangan yang datanya berasal dari produksi, f1102 tidak
 * pernah muncul ketika alumni memilih "Lainnya" — terbukti dari tabel
 * jawaban, yang tidak memuat satu pun jawaban f1102 — dan justru muncul
 * ketika alumni memilih BUMN/BUMD. Sejak f1102 ditandai wajib, cacat itu
 * berubah dari sekadar mengganggu menjadi menghalangi: memilih BUMN/BUMD
 * menuntut alumni menyebutkan "jenis perusahaan lainnya".
 *
 * PERBAIKAN
 * ---------
 * Pemicunya tidak dipatok angka, melainkan dicari dari label opsi f1101 pada
 * pemasangan yang bersangkutan. Dengan begitu satu migrasi ini benar baik di
 * basis data hasil seeder (Lainnya = 5) maupun hasil restore dump produksi
 * (Lainnya = 7), dan tetap benar bila kelak penomorannya berubah lagi.
 *
 * Penomoran f1101 SENGAJA tidak diseragamkan ke lembar kementerian. Jawaban
 * yang sudah tersimpan menunjuk kode produksi — 410 jawaban bernilai 6 dan 55
 * bernilai 7 — sehingga menomori ulang opsinya akan menggeser arti jawaban
 * yang sudah terkumpul. Penyelarasan itu urusan lapisan ekspor, bukan urusan
 * tabel opsi.
 */
return new class extends Migration
{
    protected $connection = 'oltp';

    /** Label opsi "Lainnya" pada f1101, dicocokkan tanpa peduli huruf besar. */
    private const LABEL_LAINNYA = 'lainnya, tuliskan';

    public function up(): void
    {
        foreach ($this->pasanganF1101danF1102() as [$f1101Id, $f1102]) {
            $kode = DB::connection('oltp')->table('questionnaire_options')
                ->where('question_id', $f1101Id)
                ->get(['option_code', 'option_label'])
                ->first(fn ($o) => mb_strtolower(trim($o->option_label)) === self::LABEL_LAINNYA)
                ?->option_code;

            if ($kode === null) {
                continue;
            }

            $this->setPemicu($f1102, ['f1101' => [is_numeric($kode) ? (int) $kode : $kode]]);
        }
    }

    /**
     * Mengembalikan pemicunya ke angka 5 sebagaimana tertulis di seeder.
     *
     * Pada pemasangan hasil seeder itu memang nilai yang benar; pada
     * pemasangan hasil dump produksi ia mengembalikan cacatnya — dan memang
     * itulah arti membatalkan migrasi ini.
     */
    public function down(): void
    {
        foreach ($this->pasanganF1101danF1102() as [, $f1102]) {
            $this->setPemicu($f1102, ['f1101' => [5]]);
        }
    }

    /** Pasangan (id pertanyaan f1101, baris f1102) untuk tiap kuesioner. */
    private function pasanganF1101danF1102(): array
    {
        $conn = DB::connection('oltp');

        $f1101 = $conn->table('questionnaire_questions')->where('code', 'f1101')
            ->pluck('id', 'questionnaire_id');

        $pasangan = [];
        foreach ($conn->table('questionnaire_questions')->where('code', 'f1102')->get(['id', 'questionnaire_id', 'metadata']) as $f1102) {
            if (isset($f1101[$f1102->questionnaire_id])) {
                $pasangan[] = [$f1101[$f1102->questionnaire_id], $f1102];
            }
        }

        return $pasangan;
    }

    /** Menulis ulang show_if tanpa menyentuh kunci metadata lainnya. */
    private function setPemicu(object $f1102, array $showIf): void
    {
        $meta = json_decode($f1102->metadata ?? '{}', true) ?: [];
        $meta['show_if'] = $showIf;

        DB::connection('oltp')->table('questionnaire_questions')
            ->where('id', $f1102->id)
            ->update(['metadata' => json_encode($meta), 'updated_at' => now()]);
    }
};
