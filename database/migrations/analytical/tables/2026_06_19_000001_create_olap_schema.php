<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration untuk seluruh schema OLAP SmartTracer.
 *
 * Mencakup semua keputusan desain yang disepakati selama sesi ETL:
 *   - Business key derived sebagai STRING (varchar) untuk dim_perusahaan,
 *     dim_wirausaha, dim_status_alumni, dim_kesesuaian_level,
 *     dim_kesesuaian_bidang -- bukan integer/hash (crc32) seperti
 *     schema awal.
 *   - dim_kesesuaian_bidang: kolom business key bernama id_kesesuaian_bidang
 *     (BUKAN id_kesesuaian_level seperti schema asal -- renamed karena
 *     f14 dan f15 INDEPENDEN, tidak ada FK antar keduanya).
 *   - Ukuran varchar diperbesar untuk kolom free-text rawan overflow
 *     (company_name, jabatan, perguruan_tinggi, program_studi, dst).
 *   - label_pertanyaan di dim_indikator_evaluasi diperbesar ke 255.
 *   - skor di fact_range_evaluasi: CHECK constraint 1-5.
 *
 * Koneksi: 'olap' (database terpisah dari OLTP).
 * Semua tabel masuk ke schema 'public' (default PostgreSQL).
 *
 * Urutan CREATE: dim tanpa FK dulu, baru dim dengan FK, baru fact.
 * Urutan DOWN: fact dulu (banyak FK ke dim), baru dim.
 */
return new class extends Migration
{
    protected $connection = 'olap';

    public function up(): void
    {
        // ══════════════════════════════════════════════════════════════
        // DIMENSI TANPA FK KE DIMENSI LAIN (aman dibuat duluan)
        // ══════════════════════════════════════════════════════════════

        // dim_waktu — selalu INSERT baru setiap run ETL, tidak punya
        // business key unik (snapshot baru = baris baru).
        Schema::connection('olap')->create('dim_waktu', function (Blueprint $table) {
            $table->increments('id_waktu');
            $table->string('minggu_snapshot', 10)->nullable();  // nomor minggu ISO (W01-W53)
            $table->string('bulan_snapshot', 15)->nullable();   // nama bulan (January-December)
            $table->string('tahun_snapshot', 5)->nullable();    // tahun 4 digit
            $table->date('tanggal_refresh')->nullable();        // tanggal ETL dijalankan
        });

        // dim_prodi — SCD Type 2 (valid_from/valid_to/flag).
        // Business key: id_prodi (integer, FK ke OLTP programs.id).
        Schema::connection('olap')->create('dim_prodi', function (Blueprint $table) {
            $table->increments('prodi_sk');                     // surrogate key (auto-increment)
            $table->integer('id_prodi')->index();               // natural key dari OLTP programs.id
            $table->string('kode_prodi', 10)->nullable();
            $table->string('nama_prodi', 100)->nullable();
            $table->string('jurusan', 100)->nullable();
            $table->string('jenjang', 5)->nullable();           // D3, D4, S1, dst
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->boolean('flag_prodi')->default(true);       // true = versi aktif saat ini
        });

        // dim_alumni — SCD Type 1 (overwrite). Business key: nim (UNIQUE).
        // label_sumber_biaya_dipolban diisi dari jawaban f1201 per-response
        // (bukan dari alumni_profiles), diupsert dari AlumniFactBuilderService.
        Schema::connection('olap')->create('dim_alumni', function (Blueprint $table) {
            $table->increments('id_alumni');
            $table->string('nim', 20)->unique();                // business key -- UNIQUE di OLTP
            $table->string('nama', 100)->nullable();
            $table->integer('jenis_kelamin')->nullable();       // 1=Laki, 2=Perempuan (sesuai OLTP)
            $table->string('angkatan', 5)->nullable();          // tahun masuk
            $table->string('tahun_lulus', 5)->nullable();       // dipakai lookup UMP untuk flag_above_ump
            $table->string('label_sumber_biaya_dipolban', 100)->nullable(); // label f1201 (single_choice)
        });

        // dim_status_alumni — Type 1 + append.
        // Business key: id_status_alumni = "{questionnaire_id}:f8:{option_code}" (string),
        // DIPISAH PER QUESTIONNAIRE supaya perubahan label di kuesioner baru
        // tidak menimpa label snapshot lama dari kuesioner sebelumnya.
        Schema::connection('olap')->create('dim_status_alumni', function (Blueprint $table) {
            $table->increments('status_alumni_sk');
            $table->string('id_status_alumni', 50)->unique();   // "{qid}:f8:{option_code}"
            $table->string('label', 100)->nullable();           // label dari questionnaire_options
        });

        // dim_kesesuaian_level — Type 1 + append, dynamic sync dari f15.
        // Business key: id_kesesuaian_level = "{questionnaire_id}:f15:{option_code}" (string).
        // f15 = "Tingkat pendidikan apa yang paling tepat/sesuai untuk pekerjaan Anda saat ini?"
        // Opsi: Setingkat Lebih Tinggi, Tingkat yang Sama, Setingkat Lebih Rendah,
        //        Tidak Perlu Pendidikan Tinggi.
        // SENTINEL: id_kesesuaian_level='0:tidak_ada_data', label='Tidak Ada Data'
        // (untuk alumni yang tidak bekerja / tidak mengisi f15).
        Schema::connection('olap')->create('dim_kesesuaian_level', function (Blueprint $table) {
            $table->increments('kesesuaian_level_sk');
            $table->string('id_kesesuaian_level', 50)->unique(); // "{qid}:f15:{option_code}"
            $table->string('label', 100)->nullable();
        });

        // dim_kesesuaian_bidang — Type 1 + append, dynamic sync dari f14.
        // Business key: id_kesesuaian_bidang = "{questionnaire_id}:f14:{option_code}" (string).
        // f14 = "Seberapa erat hubungan bidang studi dengan pekerjaan Anda?"
        // Opsi: Sangat Erat, Erat, Cukup Erat, Kurang Erat, Tidak Sama Sekali.
        // TIDAK ADA FK ke dim_kesesuaian_level -- f14 dan f15 adalah dua
        // pertanyaan INDEPENDEN. Kolom business key bernama id_kesesuaian_bidang
        // (bukan id_kesesuaian_level seperti schema asal -- dikoreksi).
        // SENTINEL: id_kesesuaian_bidang='0:tidak_ada_data', label='Tidak Ada Data'.
        Schema::connection('olap')->create('dim_kesesuaian_bidang', function (Blueprint $table) {
            $table->increments('kesesuaian_bidang_sk');
            $table->string('id_kesesuaian_bidang', 50)->unique(); // "{qid}:f14:{option_code}"
            $table->string('label', 100)->nullable();
        });

        // dim_studi_lanjut — Type 1.
        // Business key: kombinasi (perguruan_tinggi, program_studi).
        // PK = id_studi_lanjut (auto-increment, BUKAN surrogate terpisah).
        // SENTINEL: id_studi_lanjut=0, perguruan_tinggi='Tidak Ada Data',
        //           program_studi='Tidak Ada Data' (untuk alumni non-studi-lanjut).
        // id_studi_lanjut dimulai dari 1 di sequence, tapi sentinel dengan
        // id=0 di-insert manual (insertGetId dengan value eksplisit=0).
        Schema::connection('olap')->create('dim_studi_lanjut', function (Blueprint $table) {
            $table->increments('id_studi_lanjut');
            $table->string('perguruan_tinggi', 200)->nullable();    // f18b (free-text, rawan panjang)
            $table->string('program_studi', 150)->nullable();       // f18c (free-text)
            $table->string('sumber_biaya', 100)->nullable();        // f18a (label single_choice)

            $table->unique(['perguruan_tinggi', 'program_studi']);  // composite business key
        });

        // dim_ump — Type 1. Business key: id_ump (integer, dari ref_ump.id).
        // Dipakai untuk flag_above_ump di fact_tracer_study (threshold 1.2x UMP).
        // Tahun acuan = tahun_lulus alumni (bukan tahun snapshot ETL).
        Schema::connection('olap')->create('dim_ump', function (Blueprint $table) {
            $table->bigIncrements('ump_sk');
            $table->bigInteger('id_ump')->unique();              // FK ke OLTP ref_ump.id
            $table->string('tahun', 4)->index();
            $table->string('nama_provinsi', 100);               // match dengan nama hasil resolve f5a1
            $table->decimal('nilai_ump', 15, 2);

            $table->index(['tahun', 'nama_provinsi']);           // dipakai di findUmpByTahunProvinsi()
        });

        // dim_indikator_evaluasi — Type 1. Business key: kode_field (varchar).
        // Dua kategori:
        //   - 'multi_select': field boolean (f1601-f1613, AlasanKerjaTdkSesuai)
        //   - 'range_evaluasi': field number dengan scale (f1761-f1774 Kompetensi,
        //     f21-f27 MetodePembelajaran)
        Schema::connection('olap')->create('dim_indikator_evaluasi', function (Blueprint $table) {
            $table->increments('id_indikator_evaluasi');
            $table->string('kode_field', 50)->unique();          // f1601, f1761, f21, dst
            $table->string('label_pertanyaan', 255)->nullable(); // diperbesar dari 100 (rawan overflow)
            $table->string('kategori_pertanyaan', 50)->nullable(); // 'multi_select' | 'range_evaluasi'
            $table->string('jenis_skala', 50)->nullable();       // 'boolean' | 'likert_5' | dst
        });

        // dim_perusahaan — SCD Type 2.
        // Business key: id_perusahaan = "{nama_perusahaan}|{kota}" lowercase-trimmed (varchar),
        // derived dari f5b (nama perusahaan) + f5a2 (nama kota, sudah di-resolve dari cities.id).
        // NULL jika alumni tidak bekerja (perusahaan_sk di fact bisa NULL).
        Schema::connection('olap')->create('dim_perusahaan', function (Blueprint $table) {
            $table->increments('perusahaan_sk');
            $table->string('id_perusahaan', 255)->index();       // "{nama}|{kota}" lowercase
            $table->string('company_name', 200)->nullable();     // diperbesar dari 150 (f5b free-text)
            $table->string('label_jenis_perusahaan', 100)->nullable(); // label f1101 (single_choice)
            $table->string('label_tingkat_instansi', 100)->nullable(); // label f5d (single_choice)
            $table->string('nama_kota', 100)->nullable();        // nama kota (resolved dari cities.id)
            $table->string('nama_provinsi', 100)->nullable();    // nama provinsi (resolved dari provinces.id)
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->boolean('flag_perusahaan')->default(true);   // true = versi aktif
        });

        // dim_wirausaha — SCD Type 2.
        // Business key: id_wirausaha = "{jabatan}|{kota}" lowercase-trimmed (varchar),
        // derived dari f5c (jabatan, label single_choice: Staff/Founder/Freelancer/Co-Founder)
        // + f5a2 (nama kota, resolved dari cities.id).
        // NULL jika alumni bukan wiraswasta (wirausaha_sk di fact bisa NULL).
        Schema::connection('olap')->create('dim_wirausaha', function (Blueprint $table) {
            $table->increments('wirausaha_sk');
            $table->string('id_wirausaha', 255)->index();        // "{jabatan}|{kota}" lowercase
            $table->string('jabatan', 150)->nullable();          // diperbesar dari 20 (label f5c)
            $table->string('label_tingkat_instansi', 100)->nullable(); // diperbesar dari 15 (label f5d)
            $table->string('nama_provinsi', 100)->nullable();
            $table->string('nama_kota', 100)->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->boolean('flag_wirausaha')->default(true);    // true = versi aktif
        });

        // ══════════════════════════════════════════════════════════════
        // FACT TABLES — dibuat setelah semua dim ada
        // ══════════════════════════════════════════════════════════════

        // fact_tracer_study — grain: 1 baris per alumni per snapshot.
        // Semua dim-FK yang nullable (perusahaan_sk, wirausaha_sk, dst)
        // diisi NULL kalau alumni tidak relevan untuk dim tersebut.
        // kesesuaian_bidang_sk dan kesesuaian_level_sk TIDAK NULL karena
        // ada sentinel "Tidak Ada Data" untuk alumni yang tidak bekerja.
        // id_studi_lanjut TIDAK NULL karena ada sentinel id=0.
        Schema::connection('olap')->create('fact_tracer_study', function (Blueprint $table) {
            $table->increments('id_fact');

            // FK ke dim
            $table->integer('id_alumni')->index();
            $table->integer('id_waktu')->index();
            $table->integer('status_alumni_sk');                 // NOT NULL (selalu ada jawaban f8 untuk fact terinsert)
            $table->integer('prodi_sk');                         // NOT NULL
            $table->integer('kesesuaian_bidang_sk')->nullable(); // sentinel jika tidak menjawab f14
            $table->integer('kesesuaian_level_sk')->nullable();  // sentinel jika tidak menjawab f15
            $table->integer('id_studi_lanjut')->nullable();      // sentinel (0) jika tidak studi lanjut
            $table->integer('perusahaan_sk')->nullable();        // NULL jika bukan karyawan/bekerja
            $table->integer('wirausaha_sk')->nullable();         // NULL jika bukan wiraswasta
            $table->unsignedBigInteger('ump_sk')->nullable();    // NULL jika provinsi/tahun tidak match

            // Measure / field angka
            $table->integer('masa_tunggu_bekerja')->nullable();  // f502, bulan
            $table->integer('bulan_sebelum_lulus')->nullable();  // f302, bulan sebelum lulus mulai cari kerja
            $table->integer('bulan_sesudah_lulus')->nullable();  // belum ada question_code (pending)
            $table->integer('masa_tunggu_wirausaha')->nullable();// belum ada question_code (pending)
            $table->integer('take_home_pay')->nullable();        // f505, rupiah
            $table->integer('flag_above_ump')->nullable();       // 1 jika take_home_pay >= 1.2x UMP, 0 jika tidak

            // FK constraints
            $table->foreign('id_alumni')->references('id_alumni')->on('dim_alumni');
            $table->foreign('id_waktu')->references('id_waktu')->on('dim_waktu');
            $table->foreign('status_alumni_sk')->references('status_alumni_sk')->on('dim_status_alumni');
            $table->foreign('prodi_sk')->references('prodi_sk')->on('dim_prodi');
            $table->foreign('kesesuaian_bidang_sk')->references('kesesuaian_bidang_sk')->on('dim_kesesuaian_bidang');
            $table->foreign('kesesuaian_level_sk')->references('kesesuaian_level_sk')->on('dim_kesesuaian_level');
            $table->foreign('id_studi_lanjut')->references('id_studi_lanjut')->on('dim_studi_lanjut');
            $table->foreign('perusahaan_sk')->references('perusahaan_sk')->on('dim_perusahaan');
            $table->foreign('wirausaha_sk')->references('wirausaha_sk')->on('dim_wirausaha');
            $table->foreign('ump_sk')->references('ump_sk')->on('dim_ump');

            // Index untuk query KPI yang sering
            $table->index(['id_alumni', 'id_waktu']);
            $table->index(['prodi_sk', 'id_waktu']);
        });

        // fact_multi_select — grain: 1 baris per (alumni, indikator, snapshot).
        // Hanya untuk field boolean yang jawabannya TRUE (tercentang).
        // Tidak ada measure -- tabel ini murni occurrence/bridge.
        // Mencatat: AlasanKerjaTdkSesuai (f1601-f1613).
        Schema::connection('olap')->create('fact_multi_select', function (Blueprint $table) {
            $table->increments('id_multi_select');
            $table->integer('id_alumni');
            $table->integer('prodi_sk');
            $table->integer('id_waktu');
            $table->integer('id_indikator_evaluasi');

            $table->foreign('id_alumni')->references('id_alumni')->on('dim_alumni');
            $table->foreign('prodi_sk')->references('prodi_sk')->on('dim_prodi');
            $table->foreign('id_waktu')->references('id_waktu')->on('dim_waktu');
            $table->foreign('id_indikator_evaluasi')->references('id_indikator_evaluasi')->on('dim_indikator_evaluasi');

            $table->index(['id_alumni', 'id_waktu']);
            $table->index(['id_indikator_evaluasi', 'id_waktu']);
        });

        // fact_range_evaluasi — grain: 1 baris per (alumni, indikator, snapshot).
        // Dengan measure skor 1-5.
        // Tiap alumni yang mengisi semua indikator menghasilkan:
        //   - 7 baris Kompetensi A (f1761-f1767)
        //   - 7 baris Kompetensi B (f1768-f1774)
        //   - 7 baris Metode Pembelajaran (f21-f27)
        //   = 21 baris per alumni per snapshot (jika semua diisi).
        Schema::connection('olap')->create('fact_range_evaluasi', function (Blueprint $table) {
            $table->increments('id_range_evaluasi');
            $table->integer('prodi_sk');
            $table->integer('id_alumni');
            $table->integer('id_waktu');
            $table->integer('id_indikator_evaluasi');
            $table->integer('skor')->nullable();                 // skala 1-5

            $table->foreign('prodi_sk')->references('prodi_sk')->on('dim_prodi');
            $table->foreign('id_alumni')->references('id_alumni')->on('dim_alumni');
            $table->foreign('id_waktu')->references('id_waktu')->on('dim_waktu');
            $table->foreign('id_indikator_evaluasi')->references('id_indikator_evaluasi')->on('dim_indikator_evaluasi');

            $table->index(['id_alumni', 'id_waktu']);
            $table->index(['id_indikator_evaluasi', 'prodi_sk', 'id_waktu']);

            // CHECK constraint skor 1-5 via raw DB statement setelah create
            // (Blueprint::check() tidak tersedia di semua versi Laravel)
        });

        // Tambah CHECK constraint skor setelah tabel dibuat
        \Illuminate\Support\Facades\DB::connection('olap')->statement(
            'ALTER TABLE fact_range_evaluasi ADD CONSTRAINT fact_range_evaluasi_skor_check CHECK (skor >= 1 AND skor <= 5)'
        );
    }

    public function down(): void
    {
        // Drop FACT dulu (ada FK ke dim), baru dim
        // dim dengan FK ke dim lain: sesuaikan urutan
        Schema::connection('olap')->dropIfExists('fact_range_evaluasi');
        Schema::connection('olap')->dropIfExists('fact_multi_select');
        Schema::connection('olap')->dropIfExists('fact_tracer_study');
        Schema::connection('olap')->dropIfExists('dim_wirausaha');
        Schema::connection('olap')->dropIfExists('dim_perusahaan');
        Schema::connection('olap')->dropIfExists('dim_indikator_evaluasi');
        Schema::connection('olap')->dropIfExists('dim_ump');
        Schema::connection('olap')->dropIfExists('dim_studi_lanjut');
        Schema::connection('olap')->dropIfExists('dim_kesesuaian_bidang');
        Schema::connection('olap')->dropIfExists('dim_kesesuaian_level');
        Schema::connection('olap')->dropIfExists('dim_status_alumni');
        Schema::connection('olap')->dropIfExists('dim_alumni');
        Schema::connection('olap')->dropIfExists('dim_prodi');
        Schema::connection('olap')->dropIfExists('dim_waktu');
    }
};