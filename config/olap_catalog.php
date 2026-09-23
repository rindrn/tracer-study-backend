<?php

/**
 * config/olap_catalog.php
 *
 * Katalog OLAP Explorer — satu-satunya sumber kebenaran tentang measure dan
 * dimensi apa yang boleh disusun sendiri oleh pengguna di halaman Insight.
 *
 * Frontend TIDAK PERNAH mengarang nama member Cube.js. Ia membaca katalog ini
 * lewat GET /api/dashboard/explorer/catalog dan hanya boleh mengirim balik key
 * yang ada di sini. Apa pun di luar katalog ditolak 422 oleh OlapCatalog.
 *
 * Tiga keputusan yang dibakukan di berkas ini:
 *
 * 1. GRAIN. Tiap cube membawa daftar `dimensions`-nya sendiri. Pengguna memilih
 *    SATU cube lebih dulu, dan hanya dimensi milik cube itu yang ditawarkan.
 *    fact_range_evaluasi dan fact_multi_select bergrain satu baris per alumni
 *    PER INDIKATOR, sedangkan fact_tracer_study satu baris per alumni — kalau
 *    ketiganya bisa masuk satu query, join-nya fan-out dan satu alumni
 *    terhitung belasan kali. Pemisahan per cube di sini yang mencegahnya, bukan
 *    disiplin pengguna.
 *
 * 2. SATU SUMBU TAHUN. DimWaktu sengaja TIDAK ada di katalog. Ia tahun snapshot
 *    ETL, bukan tahun kejadian, dan pengguna pasti salah memilihnya kalau
 *    ditawarkan berdampingan dengan tahun lulus. Satu-satunya sumbu tahun yang
 *    bisa dipilih adalah DimAlumni.tahun_lulus.
 *
 * 3. KOLOM TEKNIS & PII DISEMBUNYIKAN. Surrogate key (*_sk), valid_from,
 *    valid_to, dan flag_* tidak masuk. DimAlumni.nim dan DimAlumni.nama juga
 *    tidak — mengelompokkan agregat berdasarkan identitas alumni bukan analisis,
 *    dan membuka PII ke siapa pun yang punya akses halaman Insight.
 *
 * 4. CUBE "TERKINI", BUKAN FACT MENTAH. Ketiga cube yang dirujuk di sini adalah
 *    varian ter-dedup (FactTracerStudyTerkini dkk), bukan FactTracerStudy yang
 *    dipakai dashboard lain. Tabel fact menyimpan baris baru setiap ETL
 *    berjalan, dan ETL-nya inkremental, sehingga menggabung seluruh snapshot
 *    menghitung sebagian alumni berkali-kali sementara menyaring ke snapshot
 *    terakhir justru membuang alumni yang tidak ikut run terakhir. Dedup
 *    dilakukan di Cube.js; lihat catatan lengkapnya di FactTracerStudyTerkini.js.
 *
 * Nama measure & dimension di sini HARUS persis sama dengan definisi di
 * tracer-study-analytics/tracer-analytics/model/cubes/*.js. Kalau cube schema
 * berubah, berkas ini ikut diperbarui.
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Batas query
    |--------------------------------------------------------------------------
    | Pengguna menyusun query sendiri, jadi batasnya dipasang di server.
    | Tiga dimensi adalah batas praktis pivot table (baris × kolom × satu
    | pemecah lagi); di atas itu tabelnya tidak terbaca dan query-nya mahal.
    */
    'limits' => [
        'max_dimensions' => 3,
        'max_measures'   => 5,
        'max_rows'       => 5000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Dimensi
    |--------------------------------------------------------------------------
    | Dikelompokkan per dimension cube. `group` dipakai FE sebagai judul grup
    | di dropdown. Key = nama member Cube.js yang sesungguhnya.
    */
    'dimensions' => [

        'DimProdi' => [
            'group' => 'Program Studi',
            'members' => [
                'DimProdi.nama_prodi'       => 'Nama Program Studi',
                'DimProdi.jenjang'          => 'Jenjang',
                'DimProdi.jurusan'          => 'Jurusan',
                'DimProdi.kode_prodi'       => 'Kode Program Studi',
                'DimProdi.akreditasi_prodi' => 'Akreditasi',
                'DimProdi.nama_pt'          => 'Perguruan Tinggi',
            ],
        ],

        'DimAlumni' => [
            'group' => 'Alumni',
            'members' => [
                'DimAlumni.tahun_lulus'                  => 'Tahun Lulus',
                'DimAlumni.label_sumber_biaya_dipolban'  => 'Sumber Biaya Kuliah',
            ],
        ],

        'DimStatusAlumni' => [
            'group' => 'Status Alumni',
            'members' => [
                'DimStatusAlumni.label' => 'Status Alumni',
            ],
        ],

        'DimPerusahaan' => [
            'group' => 'Tempat Bekerja',
            'members' => [
                'DimPerusahaan.nama_provinsi'          => 'Provinsi Tempat Kerja',
                'DimPerusahaan.nama_kota'              => 'Kota Tempat Kerja',
                'DimPerusahaan.label_jenis_perusahaan' => 'Jenis Instansi',
                'DimPerusahaan.label_tingkat_instansi' => 'Tingkat Instansi',
                'DimPerusahaan.company_name'           => 'Nama Instansi',
            ],
        ],

        'DimWirausaha' => [
            'group' => 'Wirausaha',
            'members' => [
                'DimWirausaha.nama_provinsi'          => 'Provinsi Usaha',
                'DimWirausaha.nama_kota'              => 'Kota Usaha',
                'DimWirausaha.jabatan'                => 'Jabatan di Usaha',
                'DimWirausaha.label_tingkat_instansi' => 'Tingkat Usaha',
            ],
        ],

        'DimKesesuaianBidang' => [
            'group' => 'Kesesuaian',
            'members' => [
                'DimKesesuaianBidang.label' => 'Kesesuaian Bidang',
            ],
        ],

        'DimKesesuaianLevel' => [
            'group' => 'Kesesuaian',
            'members' => [
                'DimKesesuaianLevel.label' => 'Kesesuaian Level',
            ],
        ],

        'DimStudiLanjut' => [
            'group' => 'Studi Lanjut',
            'members' => [
                'DimStudiLanjut.perguruan_tinggi' => 'PT Studi Lanjut',
                'DimStudiLanjut.program_studi'    => 'Prodi Studi Lanjut',
                'DimStudiLanjut.sumber_biaya'     => 'Sumber Biaya Studi Lanjut',
            ],
        ],

        'DimUmp' => [
            'group' => 'UMP',
            'members' => [
                'DimUmp.nama_provinsi' => 'Provinsi UMP',
                'DimUmp.tahun'         => 'Tahun UMP',
            ],
        ],

        // Dimensi yang tinggal di fact cube itu sendiri, bukan di dimension
        // cube: ukuran numerik yang dikelompokkan jadi rentang. Tanpa ini,
        // masa tunggu dan pendapatan hanya bisa dirata-ratakan, tidak bisa
        // dipakai MEMECAH angka lain — mis. rata-rata pendapatan per rentang
        // masa tunggu kerja. Definisi batasnya di FactTracerStudyTerkini.js.
        'FactTracerStudyTerkini' => [
            'group' => 'Rentang',
            'members' => [
                'FactTracerStudyTerkini.rentang_masa_tunggu_bekerja'   => 'Rentang Masa Tunggu Kerja',
                'FactTracerStudyTerkini.rentang_masa_tunggu_wirausaha' => 'Rentang Masa Tunggu Wirausaha',
                'FactTracerStudyTerkini.rentang_pendapatan'            => 'Rentang Pendapatan',
            ],
        ],

        'DimIndikatorEvaluasi' => [
            'group' => 'Indikator Evaluasi',
            'members' => [
                'DimIndikatorEvaluasi.label_pertanyaan'    => 'Pertanyaan',
                'DimIndikatorEvaluasi.kategori_pertanyaan' => 'Kategori Pertanyaan',
                'DimIndikatorEvaluasi.grup_gap'            => 'Grup Kompetensi',
                'DimIndikatorEvaluasi.jenis_skala'         => 'Jenis Skala',
                'DimIndikatorEvaluasi.kode_field'          => 'Kode Field',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cube
    |--------------------------------------------------------------------------
    | `dimensions` = daftar dimension cube yang PUNYA JOIN ke fact ini di
    | Cube.js. Ini bukan pilihan gaya: meminta dimensi yang tidak ter-join
    | membuat Cube.js menolak query atau (lebih buruk) diam-diam menghasilkan
    | cross join. Daftarnya disalin dari blok `joins` tiap fact cube.
    */
    'cubes' => [

        'FactTracerStudyTerkini' => [
            'label'       => 'Tracer Study (per alumni)',
            'description' => 'Masa tunggu, pendapatan, keterserapan, dan kesesuaian kerja. Satu baris per alumni.',
            'dimensions'  => [
                'DimProdi', 'DimAlumni', 'DimStatusAlumni', 'DimPerusahaan',
                'DimWirausaha', 'DimKesesuaianBidang', 'DimKesesuaianLevel',
                'DimStudiLanjut', 'DimUmp',

                // Rentang masa tunggu & pendapatan — tinggal di fact cube ini
                // sendiri, jadi tidak perlu join apa pun.
                'FactTracerStudyTerkini',
            ],
            'measures' => [
                'FactTracerStudyTerkini.count_alumni' => [
                    'label' => 'Jumlah Alumni', 'format' => 'integer',
                ],

                // ── Masa tunggu bekerja ──────────────────────────────
                'FactTracerStudyTerkini.avg_masa_tunggu_bekerja' => [
                    'label' => 'Rata-rata Masa Tunggu Kerja (bulan)', 'format' => 'decimal',
                ],
                'FactTracerStudyTerkini.median_masa_tunggu_bekerja' => [
                    'label' => 'Median Masa Tunggu Kerja (bulan)', 'format' => 'decimal',
                ],
                'FactTracerStudyTerkini.min_masa_tunggu_bekerja' => [
                    'label' => 'Masa Tunggu Kerja Tercepat (bulan)', 'format' => 'decimal',
                ],
                'FactTracerStudyTerkini.max_masa_tunggu_bekerja' => [
                    'label' => 'Masa Tunggu Kerja Terlama (bulan)', 'format' => 'decimal',
                ],
                'FactTracerStudyTerkini.avg_bulan_sebelum_lulus' => [
                    'label' => 'Rata-rata Bulan Mencari Sebelum Lulus', 'format' => 'decimal',
                ],
                'FactTracerStudyTerkini.avg_bulan_sesudah_lulus' => [
                    'label' => 'Rata-rata Bulan Mencari Sesudah Lulus', 'format' => 'decimal',
                ],
                'FactTracerStudyTerkini.count_masa_tunggu_cepat' => [
                    'label' => 'Alumni Masa Tunggu Cepat (<=6 bln)', 'format' => 'integer',
                ],
                'FactTracerStudyTerkini.count_tunggu_0_3_bulan' => [
                    'label' => 'Alumni Masa Tunggu 0-3 Bulan', 'format' => 'integer',
                ],
                'FactTracerStudyTerkini.count_tunggu_3_6_bulan' => [
                    'label' => 'Alumni Masa Tunggu 3-6 Bulan', 'format' => 'integer',
                ],
                'FactTracerStudyTerkini.count_tunggu_lebih_6_bulan' => [
                    'label' => 'Alumni Masa Tunggu >6 Bulan', 'format' => 'integer',
                ],

                // ── Wirausaha ────────────────────────────────────────
                'FactTracerStudyTerkini.avg_masa_tunggu_wirausaha' => [
                    'label' => 'Rata-rata Masa Tunggu Wirausaha (bulan)', 'format' => 'decimal',
                ],
                'FactTracerStudyTerkini.min_masa_tunggu_wirausaha' => [
                    'label' => 'Masa Tunggu Wirausaha Tercepat (bulan)', 'format' => 'decimal',
                ],
                'FactTracerStudyTerkini.max_masa_tunggu_wirausaha' => [
                    'label' => 'Masa Tunggu Wirausaha Terlama (bulan)', 'format' => 'decimal',
                ],

                // ── Pendapatan ───────────────────────────────────────
                'FactTracerStudyTerkini.avg_take_home_pay' => [
                    'label' => 'Rata-rata Pendapatan', 'format' => 'currency',
                ],
                'FactTracerStudyTerkini.min_take_home_pay' => [
                    'label' => 'Pendapatan Terendah', 'format' => 'currency',
                ],
                'FactTracerStudyTerkini.max_take_home_pay' => [
                    'label' => 'Pendapatan Tertinggi', 'format' => 'currency',
                ],
                'FactTracerStudyTerkini.count_above_ump' => [
                    'label' => 'Alumni Pendapatan di Atas UMP', 'format' => 'integer',
                ],
                'FactTracerStudyTerkini.count_below_ump' => [
                    'label' => 'Alumni Pendapatan di Bawah UMP', 'format' => 'integer',
                ],
                'FactTracerStudyTerkini.count_dengan_data_ump' => [
                    'label' => 'Alumni dengan Data UMP', 'format' => 'integer',
                ],

                // ── Keterserapan & kesesuaian ────────────────────────
                'FactTracerStudyTerkini.count_terserap' => [
                    'label' => 'Alumni Terserap', 'format' => 'integer',
                ],
                'FactTracerStudyTerkini.count_tidak_terserap' => [
                    'label' => 'Alumni Belum Terserap', 'format' => 'integer',
                ],
                'FactTracerStudyTerkini.count_sesuai_bidang' => [
                    'label' => 'Alumni Bekerja Sesuai Bidang', 'format' => 'integer',
                ],
                'FactTracerStudyTerkini.count_tidak_sesuai_bidang' => [
                    'label' => 'Alumni Bekerja Tidak Sesuai Bidang', 'format' => 'integer',
                ],
            ],
        ],

        'FactRangeEvaluasiTerkini' => [
            'label'       => 'Evaluasi Kompetensi (per indikator)',
            'description' => 'Skor 1-5 tiap indikator kompetensi. Satu baris per alumni per indikator.',
            'dimensions'  => ['DimProdi', 'DimAlumni', 'DimIndikatorEvaluasi'],
            'measures'    => [
                'FactRangeEvaluasiTerkini.avg_skor' => [
                    'label' => 'Rata-rata Skor', 'format' => 'decimal',
                ],
                'FactRangeEvaluasiTerkini.min_skor' => [
                    'label' => 'Skor Terendah', 'format' => 'decimal',
                ],
                'FactRangeEvaluasiTerkini.max_skor' => [
                    'label' => 'Skor Tertinggi', 'format' => 'decimal',
                ],
                'FactRangeEvaluasiTerkini.sum_skor' => [
                    'label' => 'Total Skor', 'format' => 'integer',
                ],
                'FactRangeEvaluasiTerkini.count' => [
                    'label' => 'Jumlah Jawaban', 'format' => 'integer',
                ],
            ],
        ],

        'FactMultiSelectTerkini' => [
            'label'       => 'Jawaban Pilihan Ganda (per pilihan)',
            'description' => 'Metode pembelajaran dan pertanyaan multi-pilihan. Satu baris per alumni per pilihan.',
            'dimensions'  => ['DimProdi', 'DimAlumni', 'DimIndikatorEvaluasi'],
            'measures'    => [
                'FactMultiSelectTerkini.count_pilihan' => [
                    'label' => 'Jumlah Pilihan Dipilih', 'format' => 'integer',
                ],
                'FactMultiSelectTerkini.count_alumni_unik' => [
                    'label' => 'Jumlah Alumni Unik', 'format' => 'integer',
                ],
            ],
        ],
    ],
];
