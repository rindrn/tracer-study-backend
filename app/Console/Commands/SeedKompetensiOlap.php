<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * ETL: isi public.dim_indikator_evaluasi + public.fact_range_evaluasi
 * dari tracer_oltp.response_answers (kuesioner kompetensi f1761–f1774
 * dan metode pembelajaran f21–f27).
 *
 * Jalankan: php artisan etl:seed-evaluasi
 */
class SeedKompetensiOlap extends Command
{
    protected $signature   = 'etl:seed-evaluasi';
    protected $description = 'Seed OLAP fact_range_evaluasi dari OLTP response_answers (Kompetensi & Metode Pembelajaran)';

    // ── Kode pertanyaan yang masuk fact_range_evaluasi ──────────────────────
    private const RANGE_CODES = [
        'f1761','f1762','f1763','f1764','f1765','f1766','f1767',
        'f1768','f1769','f1770','f1771','f1772','f1773','f1774',
        'f21','f22','f23','f24','f25','f26','f27',
    ];

    // ── Metadata indikator evaluasi (kode_field → [label, kategori, jenis_skala]) ──
    private const INDICATORS = [
        // Kompetensi — saat lulus (A) vs dibutuhkan industri (B)
        'f1761' => ['Etika',                          'Kompetensi_A', 'range'],
        'f1762' => ['Etika',                          'Kompetensi_B', 'range'],
        'f1763' => ['Keahlian berdasarkan bidang ilmu', 'Kompetensi_A', 'range'],
        'f1764' => ['Keahlian berdasarkan bidang ilmu', 'Kompetensi_B', 'range'],
        'f1765' => ['Bahasa Inggris',                 'Kompetensi_A', 'range'],
        'f1766' => ['Bahasa Inggris',                 'Kompetensi_B', 'range'],
        'f1767' => ['Penggunaan Teknologi Informasi', 'Kompetensi_A', 'range'],
        'f1768' => ['Penggunaan Teknologi Informasi', 'Kompetensi_B', 'range'],
        'f1769' => ['Komunikasi',                     'Kompetensi_A', 'range'],
        'f1770' => ['Komunikasi',                     'Kompetensi_B', 'range'],
        'f1771' => ['Kerja sama tim',                 'Kompetensi_A', 'range'],
        'f1772' => ['Kerja sama tim',                 'Kompetensi_B', 'range'],
        'f1773' => ['Pengembangan diri',              'Kompetensi_A', 'range'],
        'f1774' => ['Pengembangan diri',              'Kompetensi_B', 'range'],
        // Metode Pembelajaran
        'f21'   => ['Perkuliahan',                    'MetodePembelajaran', 'range'],
        'f22'   => ['Demonstrasi',                    'MetodePembelajaran', 'range'],
        'f23'   => ['Partisipasi dalam proyek riset', 'MetodePembelajaran', 'range'],
        'f24'   => ['Magang',                         'MetodePembelajaran', 'range'],
        'f25'   => ['Praktikum',                      'MetodePembelajaran', 'range'],
        'f26'   => ['Kerja Lapangan',                 'MetodePembelajaran', 'range'],
        'f27'   => ['Diskusi',                        'MetodePembelajaran', 'range'],
    ];

    public function handle(): int
    {
        $this->line('');
        $this->info('=== ETL: Seed Evaluasi OLAP ===');
        $this->line('');

        try {
            $this->step1_createDimIndikator();
            $this->step2_upsertIndicators();
            $this->step3_createFactRange();
            $this->step4_populateFactRange();
            $this->line('');
            $this->info('✓ Selesai.');
        } catch (\Throwable $e) {
            $this->error('ETL gagal: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  Step 1: buat public.dim_indikator_evaluasi jika belum ada, pastikan ada UNIQUE
    // ──────────────────────────────────────────────────────────────────────────
    private function step1_createDimIndikator(): void
    {
        $this->line('  [1/4] Membuat public.dim_indikator_evaluasi (jika belum ada)...');

        DB::connection('olap')->statement(<<<SQL
            CREATE TABLE IF NOT EXISTS public.dim_indikator_evaluasi (
                id_indikator_evaluasi SERIAL PRIMARY KEY,
                kode_field            VARCHAR(20) NOT NULL,
                label_pertanyaan      TEXT        NOT NULL,
                kategori_pertanyaan   VARCHAR(50) NOT NULL,
                jenis_skala           VARCHAR(20) NOT NULL DEFAULT 'range'
            )
        SQL);

        // Pastikan UNIQUE constraint ada (mungkin tabel sudah ada tapi tanpa constraint)
        DB::connection('olap')->statement(<<<SQL
            DO $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM pg_constraint
                    WHERE conname = 'dim_indikator_evaluasi_kode_uq'
                ) THEN
                    ALTER TABLE public.dim_indikator_evaluasi
                    ADD CONSTRAINT dim_indikator_evaluasi_kode_uq UNIQUE (kode_field);
                END IF;
            END $$
        SQL);
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  Step 2: upsert baris indikator
    // ──────────────────────────────────────────────────────────────────────────
    private function step2_upsertIndicators(): void
    {
        $this->line('  [2/4] Upsert ' . count(self::INDICATORS) . ' indikator evaluasi...');

        foreach (self::INDICATORS as $kode => [$label, $kategori, $skala]) {
            DB::connection('olap')->statement(<<<SQL
                INSERT INTO public.dim_indikator_evaluasi
                    (kode_field, label_pertanyaan, kategori_pertanyaan, jenis_skala)
                VALUES (?, ?, ?, ?)
                ON CONFLICT (kode_field)
                DO UPDATE SET
                    label_pertanyaan    = EXCLUDED.label_pertanyaan,
                    kategori_pertanyaan = EXCLUDED.kategori_pertanyaan,
                    jenis_skala         = EXCLUDED.jenis_skala
            SQL, [$kode, $label, $kategori, $skala]);
        }

        $cnt = DB::connection('olap')
            ->selectOne('SELECT COUNT(*) AS c FROM public.dim_indikator_evaluasi');
        $this->line("     → {$cnt->c} row di dim_indikator_evaluasi");
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  Step 3: buat public.fact_range_evaluasi jika belum ada
    // ──────────────────────────────────────────────────────────────────────────
    private function step3_createFactRange(): void
    {
        $this->line('  [3/4] Membuat public.fact_range_evaluasi (jika belum ada)...');

        DB::connection('olap')->statement(<<<SQL
            CREATE TABLE IF NOT EXISTS public.fact_range_evaluasi (
                id_range_evaluasi     SERIAL  PRIMARY KEY,
                id_alumni             BIGINT  NOT NULL,
                prodi_sk              INT     NOT NULL,
                id_waktu              INT     NOT NULL,
                id_indikator_evaluasi INT     NOT NULL REFERENCES public.dim_indikator_evaluasi(id_indikator_evaluasi),
                skor                  NUMERIC(3,1) NOT NULL
            )
        SQL);

        // Unique constraint agar ON CONFLICT DO NOTHING bekerja
        DB::connection('olap')->statement(<<<SQL
            DO $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM pg_constraint
                    WHERE conname = 'fact_range_eval_uq'
                ) THEN
                    ALTER TABLE public.fact_range_evaluasi
                    ADD CONSTRAINT fact_range_eval_uq
                    UNIQUE (id_alumni, id_waktu, id_indikator_evaluasi);
                END IF;
            END $$
        SQL);
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  Step 4: isi fact_range_evaluasi dari response_answers
    // ──────────────────────────────────────────────────────────────────────────
    private function step4_populateFactRange(): void
    {
        $this->line('  [4/4] Mengisi fact_range_evaluasi dari response_answers...');

        // Ambil id_waktu terbaru dari dim_waktu
        $waktu = DB::connection('olap')
            ->selectOne('SELECT id_waktu FROM public.dim_waktu ORDER BY id_waktu DESC LIMIT 1');

        if (! $waktu) {
            $this->warn('     ! public.dim_waktu kosong — lewati pengisian fact.');
            $this->warn('       Jalankan ETL utama (fact_tracer_study) terlebih dahulu,');
            $this->warn('       lalu ulangi perintah ini.');
            return;
        }

        $idWaktu = $waktu->id_waktu;
        $inList  = implode(',', array_map(fn($c) => "'{$c}'", self::RANGE_CODES));

        // INSERT dari OLTP → OLAP (cross-schema query dalam satu DB)
        // Asumsi: dim_prodi.id_prodi = tracer_oltp.programs.id
        //         dim_alumni sudah ada (di-isi ETL utama)
        $affected = DB::connection('olap')->affectingStatement(<<<SQL
            INSERT INTO public.fact_range_evaluasi
                (id_alumni, prodi_sk, id_waktu, id_indikator_evaluasi, skor)
            SELECT
                r.alumni_id                      AS id_alumni,
                dp.prodi_sk,
                {$idWaktu}                       AS id_waktu,
                die.id_indikator_evaluasi,
                CAST(COALESCE(ra.answer_number, NULLIF(ra.answer_text, '')::NUMERIC) AS NUMERIC(3,1)) AS skor
            FROM tracer_oltp.response_answers ra
            JOIN tracer_oltp.responses  r   ON r.id  = ra.response_id
            JOIN tracer_oltp.alumni_profiles ap ON ap.id = r.alumni_id
            JOIN tracer_oltp.programs   p   ON p.id  = ap.program_id
            JOIN public.dim_prodi       dp  ON dp.id_prodi = p.id AND dp.flag_prodi = true
            JOIN public.dim_indikator_evaluasi die
                                        ON die.kode_field = ra.question_code
            WHERE ra.question_code IN ({$inList})
              AND (ra.answer_number IS NOT NULL OR ra.answer_text ~ '^[0-9]+(\.[0-9]+)?$')
            ON CONFLICT (id_alumni, id_waktu, id_indikator_evaluasi)
            DO NOTHING
        SQL);

        $total = DB::connection('olap')
            ->selectOne('SELECT COUNT(*) AS c FROM public.fact_range_evaluasi');

        $this->line("     → {$affected} row baru, total {$total->c} row di fact_range_evaluasi");
    }
}
