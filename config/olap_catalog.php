<?php

/*
|--------------------------------------------------------------------------
| Katalog OLAP Explorer (halaman Insight)
|--------------------------------------------------------------------------
|
| Satu-satunya daftar measure dan dimensi Cube.js yang boleh disusun sendiri
| oleh pengguna di halaman Insight. Apa pun yang tidak tercantum di sini
| ditolak peladen (422), jadi katalog ini sekaligus pagar keamanannya:
| kolom identitas (nama, NIM, nama perusahaan) dan kunci teknis sengaja
| tidak dimasukkan.
|
| Label dan deskripsi ditulis untuk pengguna non-IT — yang mereka lihat
| adalah "Jumlah alumni terserap", bukan FactTracerStudy.count_terserap.
|
| format: integer  → cacah, boleh dijumlahkan jadi subtotal
|         decimal  → rata-rata/median, subtotalnya dikosongkan
|         currency → rupiah, subtotalnya dikosongkan
|
| Yang ditulis di sini hanya ukuran "siap pakai" dan dimensi. Ukuran dari
| kolom angka ("Rata-rata gaji", "Nilai tengah masa tunggu", ...) dan
| dimensi rentang ("Gaji per Rp 2 jt") TIDAK ditulis di sini: keduanya
| dibangkitkan di model Cube.js dan dibaca otomatis dari /meta (lihat
| ExplorerService::cubes()). Menambah kolom angka cukup di model Cube.
|
| count_measure    → cacah dasar: dipakai "sembunyikan kelompok dengan
|                    responden < n" dan penanda jumlah di drill-down.
| drill_dimensions → kolom daftar alumni saat sebuah angka diklik
|                    (drill-through). Harus bisa di-join dari cube ini.
| hidden           → true: cube tidak ditawarkan di pilihan "Data" halaman
|                    Insight (dan contoh pertanyaannya ikut hilang), tetapi
|                    definisinya tetap berlaku, sehingga item dashboard yang
|                    sudah tersimpan untuk cube ini masih bisa dijalankan.
| lainnya_texts    → dimensi berisian "Lainnya, tuliskan" → kolom teks
|                    ketikannya. Di dimensi semua isian bebas berkumpul di
|                    satu kelompok "Lainnya"; teksnya baru tampil saat
|                    kelompok itu di-drill-through.
| hierarchies      → jenjang dimensi untuk roll-up / drill-down, ditulis
|                    dari level RINGKAS ke RINCI. Tiap level wajib ada di
|                    dimension_groups cube yang sama, dan tiap nilai level
|                    bawah punya tepat satu induk di level atasnya. Dimensi
|                    satu level (status alumni, kesesuaian, tahun lulus, ...)
|                    sengaja tidak ditulis: cukup di-slice/dice, dan roll-up
|                    di atasnya berarti melepas dimensi sampai total.
|
| Yang SENGAJA tidak punya hierarki:
|   - DimWaktu  : waktu ETL dijalankan, bukan waktu alumni mengisi; cacahnya
|                 semi-additive (tidak boleh dijumlah lintas snapshot).
|   - DimUmp    : tabel acuan flag_above_ump; provinsi & tahunnya sudah ada
|                 di DimPerusahaan dan DimAlumni.
|   - nama_pt   : satu pemasangan = satu PT, nilainya selalu sama.
|   - grup_gap  : memasangkan butir Kompetensi A dan B, bukan induk kategori.
|
| "Program studi" memakai DimProdi.prodi_jenjang, bukan nama_prodi: nama
| prodi tidak unik antar-jenjang (D3 & D4 Akuntansi), lihat DimProdi.js.
|
| Hierarki Program Studi (jurusan → jenjang → prodi) satu-satunya yang
| tidak tegas di level tengah: jenjang dipakai banyak jurusan, jadi "D3"
| punya banyak induk. Aman karena drill-down menyimpan jurusan sebagai
| saringan, sehingga jenjang selalu dibaca di dalam satu jurusan (D3 & D4
| Akuntansi); roll-up pun dihitung ulang dari fakta, bukan dijumlah dari
| angka jenjang. Prodi_jenjang sendiri tetap punya tepat satu pasangan
| jurusan + jenjang.
|
*/

return [

    'limits' => [
        // Batas dimensi sebenarnya PER CUBE, dihitung dari jumlah tabel
        // dimensi di dimension_groups (ExplorerService::maxDimensionsOf) dan
        // bisa ditimpa dengan kunci 'max_dimensions' di config cube. Angka
        // ini hanya cadangan bila cube tidak punya dimensi sama sekali, dan
        // untuk klien lama yang belum membaca batas per cube.
        'max_dimensions' => 3,
        'max_measures'   => 4,
        'max_formulas'   => 3,
        'max_rows'       => 5000,
    ],

    'cubes' => [

        'FactTracerStudy' => [
            'label'       => 'Tracer Study Alumni',
            'description' => 'Status, masa tunggu, kesesuaian bidang, dan pendapatan alumni pada snapshot terbaru.',

            // Measure cacah yang dipakai untuk mencari snapshot terbaru dan
            // mengisi dropdown filter — tidak ditampilkan terpisah.
            'base_measure'  => 'FactTracerStudy.count_alumni',
            'count_measure' => 'FactTracerStudy.count_alumni',
            'drill_dimensions' => [
                'DimAlumni.id_alumni', 'DimAlumni.nama', 'DimAlumni.nim', 'DimProdi.nama_prodi',
                'DimProdi.jenjang', 'DimAlumni.tahun_lulus', 'DimStatusAlumni.label',
            ],
            'lainnya_texts' => [
                'DimPerusahaan.label_jenis_perusahaan' => 'DimPerusahaan.jenis_perusahaan_lainnya',
                'DimAlumni.label_sumber_biaya_dipolban' => 'DimAlumni.sumber_biaya_lainnya',
            ],

            'measures' => [
                'FactTracerStudy.count_alumni' => [
                    'label' => 'Jumlah alumni', 'format' => 'integer',
                    'description' => 'Banyaknya alumni yang mengisi tracer study.',
                ],
                'FactTracerStudy.count_terserap' => [
                    'label' => 'Jumlah alumni terserap', 'format' => 'integer',
                    'description' => 'Alumni yang bekerja, berwirausaha, atau studi lanjut (IKU 2).',
                ],
                'FactTracerStudy.count_tidak_terserap' => [
                    'label' => 'Jumlah alumni belum terserap', 'format' => 'integer',
                    'description' => 'Alumni yang belum bekerja, berwirausaha, atau studi lanjut.',
                ],
                'FactTracerStudy.count_masa_tunggu_cepat' => [
                    'label' => 'Jumlah alumni dapat kerja cepat', 'format' => 'integer',
                    'description' => 'Alumni yang mendapat pekerjaan dalam masa tunggu singkat.',
                ],
                'FactTracerStudy.count_sesuai_bidang' => [
                    'label' => 'Jumlah alumni kerja sesuai bidang', 'format' => 'integer',
                    'description' => 'Alumni yang pekerjaannya sesuai dengan bidang studinya.',
                ],
                'FactTracerStudy.count_tidak_sesuai_bidang' => [
                    'label' => 'Jumlah alumni kerja tidak sesuai bidang', 'format' => 'integer',
                    'description' => 'Alumni yang pekerjaannya kurang atau tidak sesuai bidang studinya.',
                ],
                'FactTracerStudy.count_above_ump' => [
                    'label' => 'Jumlah alumni bergaji di atas UMP', 'format' => 'integer',
                    'description' => 'Alumni dengan pendapatan di atas upah minimum provinsi tempat kerjanya.',
                ],
                'FactTracerStudy.count_below_ump' => [
                    'label' => 'Jumlah alumni bergaji di bawah UMP', 'format' => 'integer',
                    'description' => 'Alumni dengan pendapatan di bawah upah minimum provinsi tempat kerjanya.',
                ],
                'FactTracerStudy.avg_masa_tunggu_bekerja' => [
                    'label' => 'Rata-rata masa tunggu kerja (bulan)', 'format' => 'decimal',
                    'description' => 'Rata-rata lama alumni menunggu sampai mendapat pekerjaan pertama.',
                ],
                'FactTracerStudy.median_masa_tunggu_bekerja' => [
                    'label' => 'Median masa tunggu kerja (bulan)', 'format' => 'decimal',
                    'description' => 'Nilai tengah masa tunggu kerja — tidak terpengaruh nilai ekstrem.',
                ],
                'FactTracerStudy.avg_masa_tunggu_wirausaha' => [
                    'label' => 'Rata-rata masa tunggu wirausaha (bulan)', 'format' => 'decimal',
                    'description' => 'Rata-rata lama alumni sampai memulai usahanya sendiri.',
                ],
                'FactTracerStudy.avg_take_home_pay' => [
                    'label' => 'Rata-rata gaji', 'format' => 'currency',
                    'description' => 'Rata-rata pendapatan bersih (take home pay) per bulan.',
                ],
                'FactTracerStudy.min_take_home_pay' => [
                    'label' => 'Gaji terendah', 'format' => 'currency',
                    'description' => 'Pendapatan bersih per bulan paling kecil.',
                ],
                'FactTracerStudy.max_take_home_pay' => [
                    'label' => 'Gaji tertinggi', 'format' => 'currency',
                    'description' => 'Pendapatan bersih per bulan paling besar.',
                ],
            ],

            'dimension_groups' => [
                'Program Studi' => [
                    'DimProdi.jurusan'          => 'Jurusan',
                    'DimProdi.jenjang'          => 'Jenjang',
                    'DimProdi.prodi_jenjang'    => 'Program studi',
                    'DimProdi.akreditasi_prodi' => 'Akreditasi prodi',
                ],
                'Alumni' => [
                    'DimAlumni.tahun_lulus'                 => 'Tahun lulus',
                    'DimAlumni.label_sumber_biaya_dipolban' => 'Sumber biaya kuliah',
                ],
                // Satu grup = satu tabel dimensi star schema, termasuk tiga
                // dimensi kecil di bawah ini yang dulu disatukan.
                'Status Alumni' => [
                    'DimStatusAlumni.label' => 'Status alumni',
                ],
                'Kesesuaian Bidang' => [
                    'DimKesesuaianBidang.label' => 'Kesesuaian bidang kerja',
                ],
                'Kesesuaian Tingkat' => [
                    'DimKesesuaianLevel.label' => 'Kesesuaian tingkat pendidikan',
                ],
                'Perusahaan' => [
                    'DimPerusahaan.label_jenis_perusahaan' => 'Jenis instansi',
                    'DimPerusahaan.label_tingkat_instansi' => 'Tingkat instansi',
                    'DimPerusahaan.nama_provinsi'          => 'Provinsi tempat kerja',
                    'DimPerusahaan.nama_kota'              => 'Kota tempat kerja',
                ],
                'Wirausaha' => [
                    'DimWirausaha.label_tingkat_instansi' => 'Tingkat usaha',
                    'DimWirausaha.nama_provinsi'          => 'Provinsi usaha',
                    'DimWirausaha.nama_kota'              => 'Kota usaha',
                    'DimWirausaha.jabatan'                => 'Jabatan di usaha',
                ],
                'Studi Lanjut' => [
                    'DimStudiLanjut.status_studi'     => 'Status studi lanjut',
                    'DimStudiLanjut.sumber_biaya'     => 'Sumber biaya studi lanjut',
                    'DimStudiLanjut.perguruan_tinggi' => 'Perguruan tinggi studi lanjut',
                    'DimStudiLanjut.program_studi'    => 'Program studi lanjut',
                ],
            ],

            'hierarchies' => [
                'Program Studi' => ['DimProdi.jurusan', 'DimProdi.jenjang', 'DimProdi.prodi_jenjang'],
                'Lokasi kerja'  => ['DimPerusahaan.nama_provinsi', 'DimPerusahaan.nama_kota'],
                'Lokasi usaha'  => ['DimWirausaha.nama_provinsi', 'DimWirausaha.nama_kota'],
            ],
        ],

        'FactRangeEvaluasi' => [
            'label'       => 'Kompetensi & Metode Pembelajaran',
            'hidden'      => true,
            'description' => 'Skor 1–5 dari alumni: kompetensi yang dikuasai saat lulus, yang dibutuhkan di pekerjaan, dan metode pembelajaran.',

            'base_measure'  => 'FactRangeEvaluasi.count',
            'count_measure' => 'FactRangeEvaluasi.count',
            'drill_dimensions' => [
                'DimAlumni.id_alumni', 'DimAlumni.nama', 'DimAlumni.nim', 'DimProdi.nama_prodi',
                'DimProdi.jenjang', 'DimAlumni.tahun_lulus',
            ],

            'measures' => [
                // Ukuran utama fact ini. Skor antar-kategori tidak sebanding
                // (dikuasai vs dibutuhkan vs metode), jadi keterangannya
                // mengarahkan pengguna memecahnya per pertanyaan/kategori.
                'FactRangeEvaluasi.avg_skor' => [
                    'label' => 'Rata-rata skor', 'format' => 'decimal',
                    'description' => 'Rata-rata skor 1–5 dari alumni. Pecah per Pertanyaan atau Kategori penilaian agar bermakna.',
                ],
                'FactRangeEvaluasi.count' => [
                    'label' => 'Jumlah jawaban', 'format' => 'integer',
                    'description' => 'Banyaknya penilaian yang diberikan alumni.',
                ],
            ],

            'dimension_groups' => [
                'Pertanyaan' => [
                    'DimIndikatorEvaluasi.kategori_label'   => 'Kategori penilaian',
                    'DimIndikatorEvaluasi.grup_gap'         => 'Aspek kompetensi',
                    'DimIndikatorEvaluasi.label_pertanyaan' => 'Pertanyaan',
                ],
                'Program Studi' => [
                    'DimProdi.jurusan'    => 'Jurusan',
                    'DimProdi.jenjang'    => 'Jenjang',
                    'DimProdi.prodi_jenjang' => 'Program studi',
                ],
                'Alumni' => [
                    'DimAlumni.tahun_lulus' => 'Tahun lulus',
                ],
            ],

            'hierarchies' => [
                'Pertanyaan'    => ['DimIndikatorEvaluasi.kategori_label', 'DimIndikatorEvaluasi.label_pertanyaan'],
                'Program Studi' => ['DimProdi.jurusan', 'DimProdi.jenjang', 'DimProdi.prodi_jenjang'],
            ],
        ],

        'FactMultiSelect' => [
            'label'       => 'Alasan Kerja Tidak Sesuai Bidang',
            'hidden'      => true,
            'description' => 'Alasan yang dipilih alumni ketika pekerjaannya tidak sesuai bidang studi (boleh lebih dari satu).',

            'base_measure'  => 'FactMultiSelect.count_alumni_unik',
            'count_measure' => 'FactMultiSelect.count_alumni_unik',
            'drill_dimensions' => [
                'DimAlumni.id_alumni', 'DimAlumni.nama', 'DimAlumni.nim', 'DimProdi.nama_prodi',
                'DimProdi.jenjang', 'DimAlumni.tahun_lulus',
            ],

            'measures' => [
                'FactMultiSelect.count_alumni_unik' => [
                    'label' => 'Jumlah alumni', 'format' => 'integer',
                    'description' => 'Banyaknya alumni yang memilih alasan tersebut.',
                ],
                'FactMultiSelect.count_pilihan' => [
                    'label' => 'Jumlah pilihan', 'format' => 'integer',
                    'description' => 'Banyaknya centang; satu alumni bisa memilih beberapa alasan.',
                ],
            ],

            'dimension_groups' => [
                'Alasan' => [
                    'DimIndikatorEvaluasi.label_pertanyaan' => 'Alasan',
                ],
                'Program Studi' => [
                    'DimProdi.jurusan'    => 'Jurusan',
                    'DimProdi.jenjang'    => 'Jenjang',
                    'DimProdi.prodi_jenjang' => 'Program studi',
                ],
                'Alumni' => [
                    'DimAlumni.tahun_lulus' => 'Tahun lulus',
                ],
            ],

            'hierarchies' => [
                'Program Studi' => ['DimProdi.jurusan', 'DimProdi.jenjang', 'DimProdi.prodi_jenjang'],
            ],
        ],

    ],
];
