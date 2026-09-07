<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Menghapus opsi f8 nomor 6 dan 7 yang tidak dikenal instrumen kementerian.
 *
 * LATAR
 * -----
 * Lembar instrumen kementerian hanya mengenal lima status pada f8. Data
 * produksi memakai dua tambahan:
 *
 *     6 = Melanjutkan pendidikan sambil bekerja
 *     7 = Melanjutkan pendidikan sambil wiraswasta
 *
 * Keduanya masuk lewat commit 34550c9, yang mencatat bahwa status 6 dan 7
 * sebelumnya justru dibuang diam-diam karena ditolak CHECK constraint.
 * Jadi keduanya kenyataan lapangan, bukan galat pengisian.
 *
 * Kode 6 dan 7 tidak dikenal portal pelaporan, dan keberadaannya membuat
 * aturan wajib bersyarat sulit dirumuskan: alumni yang memilihnya bekerja
 * sekaligus melanjutkan studi, sehingga tidak ada satu cabang pertanyaan pun
 * yang tepat baginya. Keduanya karena itu dilebur ke status pokoknya.
 *
 * YANG DIJAGA
 * -----------
 * Melebur status begitu saja akan menghapus satu-satunya jejak bahwa alumni
 * yang bersangkutan sedang melanjutkan studi — tidak satu pun dari mereka
 * punya baris di education_records. Karena itu barisnya dibuatkan lebih dulu,
 * berisi HANYA fakta yang memang diketahui (is_further_study), sementara nama
 * perguruan tinggi dan program studinya dibiarkan kosong karena tidak pernah
 * ditanyakan.
 *
 * Keadaan sebelum peleburan disalin ke tabel arsip supaya migrasi ini dapat
 * dibatalkan dengan tepat. Tanpa arsip itu, alumni bekas status 6 tidak dapat
 * dibedakan dari alumni yang memang sudah bekerja sambil kuliah sebelum
 * migrasi ini berjalan — dan jumlahnya tidak sedikit.
 *
 * SESUDAHNYA
 * ----------
 * Lapisan analitik perlu dimuat ulang (php artisan etl:run --full), karena
 * dim_status_alumni masih memuat kedua label lama.
 *
 * Dump data-only di database/dump/ masih memuat kedua status tersebut. Restore
 * dump lama ke basis data yang sudah dimigrasi akan ditolak CHECK constraint;
 * jalankan rebuild-data-dumps.sh setelah migrasi ini agar dumpnya menyusul.
 */
return new class extends Migration
{
    protected $connection = 'oltp';

    private const ARSIP = 'f8_arsip_status_gabungan';

    /** kode f8 lama => [status lama, status pengganti, kode f8 pengganti] */
    private const PELEBURAN = [
        6 => ['Melanjutkan pendidikan sambil bekerja',    'Bekerja (full time / part time)', '1'],
        7 => ['Melanjutkan pendidikan sambil wiraswasta', 'Wiraswasta',                      '3'],
    ];

    /** Lima status yang dikenal instrumen kementerian. */
    private const STATUS_KEMENTERIAN = [
        'Bekerja (full time / part time)',
        'Belum memungkinkan bekerja',
        'Wiraswasta',
        'Melanjutkan Pendidikan',
        'Tidak kerja tetapi sedang mencari kerja',
    ];

    public function up(): void
    {
        $conn = DB::connection('oltp');

        $this->buatTabelArsip();

        foreach (self::PELEBURAN as $kodeLama => [$statusLama, $statusBaru, $kodeBaru]) {
            $alumniIds = $conn->table('employment_records')
                ->where('employment_status', $statusLama)
                ->pluck('alumni_id')
                ->unique();

            if ($alumniIds->isEmpty()) {
                continue;
            }

            // Siapa yang belum punya jejak studi lanjut — hanya mereka yang
            // dibuatkan, dan hanya mereka yang boleh dihapus saat dibatalkan.
            $sudahPunya = $conn->table('education_records')
                ->whereIn('alumni_id', $alumniIds)
                ->where('is_further_study', true)
                ->pluck('alumni_id')
                ->unique()
                ->flip();

            foreach ($alumniIds as $alumniId) {
                $dibuatkan = !$sudahPunya->has($alumniId);

                $conn->table(self::ARSIP)->insert([
                    'alumni_id'      => $alumniId,
                    'kode_f8_lama'   => $kodeLama,
                    'status_lama'    => $statusLama,
                    'dibuatkan_edu'  => $dibuatkan,
                    'created_at'     => now(),
                ]);

                if ($dibuatkan) {
                    $conn->table('education_records')->insert([
                        'alumni_id'        => $alumniId,
                        'questionnaire_id' => null,
                        'is_further_study' => true,
                        'created_at'       => now(),
                        'updated_at'       => now(),
                    ]);
                }
            }

            // Constraint masih memuat tujuh status pada tahap ini, jadi
            // peleburan aman dilakukan sebelum constraint dipersempit.
            $conn->table('employment_records')
                ->where('employment_status', $statusLama)
                ->update(['employment_status' => $statusBaru, 'updated_at' => now()]);

            $conn->table('response_answers')
                ->where('question_code', 'f8')
                ->where('answer_text', (string) $kodeLama)
                ->update(['answer_text' => $kodeBaru, 'updated_at' => now()]);
        }

        $this->hapusOpsiF8(array_keys(self::PELEBURAN));
        $this->pasangConstraint(self::STATUS_KEMENTERIAN);
    }

    public function down(): void
    {
        $conn = DB::connection('oltp');

        if (!Schema::connection('oltp')->hasTable(self::ARSIP)) {
            return;
        }

        // Constraint dilonggarkan lebih dulu; tanpa itu pemulihan statusnya
        // ditolak sebelum sempat tertulis.
        $this->pasangConstraint(array_merge(
            self::STATUS_KEMENTERIAN,
            array_map(fn (array $p) => $p[0], self::PELEBURAN),
        ));

        foreach (self::PELEBURAN as $kodeLama => [$statusLama, , $kodeBaru]) {
            $baris = $conn->table(self::ARSIP)->where('kode_f8_lama', $kodeLama)->get();
            if ($baris->isEmpty()) {
                continue;
            }

            $alumniIds = $baris->pluck('alumni_id')->all();

            $conn->table('employment_records')
                ->whereIn('alumni_id', $alumniIds)
                ->update(['employment_status' => $statusLama, 'updated_at' => now()]);

            $conn->table('response_answers')
                ->where('question_code', 'f8')
                ->where('answer_text', $kodeBaru)
                ->whereIn('response_id', function ($q) use ($alumniIds) {
                    $q->select('id')->from('responses')->whereIn('alumni_id', $alumniIds);
                })
                ->update(['answer_text' => (string) $kodeLama, 'updated_at' => now()]);

            // Hanya baris yang memang dibuat migrasi ini yang dicabut.
            $conn->table('education_records')
                ->whereIn('alumni_id', $baris->where('dibuatkan_edu', true)->pluck('alumni_id')->all())
                ->whereNull('questionnaire_id')
                ->whereNull('institution_name')
                ->where('is_further_study', true)
                ->delete();
        }

        $this->kembalikanOpsiF8();

        Schema::connection('oltp')->drop(self::ARSIP);
    }

    private function buatTabelArsip(): void
    {
        if (Schema::connection('oltp')->hasTable(self::ARSIP)) {
            return;
        }

        Schema::connection('oltp')->create(self::ARSIP, function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('alumni_id');
            $t->smallInteger('kode_f8_lama');
            $t->string('status_lama');
            $t->boolean('dibuatkan_edu')->default(false);
            $t->timestamp('created_at')->nullable();
            $t->index('alumni_id');
        });
    }

    private function hapusOpsiF8(array $kodeKode): void
    {
        $conn = DB::connection('oltp');

        $idF8 = $conn->table('questionnaire_questions')->where('code', 'f8')->pluck('id');

        $conn->table('questionnaire_options')
            ->whereIn('question_id', $idF8)
            ->whereIn('option_code', array_map('strval', $kodeKode))
            ->delete();
    }

    private function kembalikanOpsiF8(): void
    {
        $conn = DB::connection('oltp');

        foreach ($conn->table('questionnaire_questions')->where('code', 'f8')->pluck('id') as $qid) {
            foreach (self::PELEBURAN as $kode => [$label]) {
                $conn->table('questionnaire_options')->updateOrInsert(
                    ['question_id' => $qid, 'option_code' => (string) $kode],
                    [
                        'option_label' => $label,
                        'option_value' => null,
                        'order_no'     => $kode,
                        'is_active'    => true,
                        'is_hidden'    => false,
                        'created_at'   => now(),
                        'updated_at'   => now(),
                    ],
                );
            }
        }
    }

    /** Menulis ulang CHECK constraint employment_status. */
    private function pasangConstraint(array $status): void
    {
        $conn   = DB::connection('oltp');
        $daftar = implode(', ', array_map(fn ($s) => "'" . str_replace("'", "''", $s) . "'", $status));

        $conn->statement('ALTER TABLE tracer_oltp.employment_records DROP CONSTRAINT IF EXISTS employment_records_employment_status_check');
        $conn->statement("ALTER TABLE tracer_oltp.employment_records ADD CONSTRAINT employment_records_employment_status_check CHECK (employment_status::text = ANY (ARRAY[$daftar]))");
    }
};
