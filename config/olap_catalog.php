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
*/

return [

    'limits' => [
        // Dimensi ketiga digambar sebagai panel (small multiples) di FE.
        'max_dimensions' => 3,
        'max_measures'   => 4,
        'max_rows'       => 5000,
    ],

    'cubes' => [

        'FactTracerStudy' => [
            'label'       => 'Tracer Study Alumni',
            'description' => 'Status, masa tunggu, kesesuaian bidang, dan pendapatan alumni pada snapshot terbaru.',

            // Measure cacah yang dipakai untuk mencari snapshot terbaru dan
            // mengisi dropdown filter — tidak ditampilkan terpisah.
            'base_measure' => 'FactTracerStudy.count_alumni',

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
                    'DimProdi.nama_prodi'       => 'Program studi',
                    'DimProdi.akreditasi_prodi' => 'Akreditasi prodi',
                ],
                'Alumni' => [
                    'DimAlumni.tahun_lulus'                 => 'Tahun lulus',
                    'DimAlumni.label_sumber_biaya_dipolban' => 'Sumber biaya kuliah',
                ],
                'Status & Kesesuaian' => [
                    'DimStatusAlumni.label'     => 'Status alumni',
                    'DimKesesuaianBidang.label' => 'Kesesuaian bidang kerja',
                    'DimKesesuaianLevel.label'  => 'Kesesuaian tingkat pendidikan',
                ],
                'Tempat Kerja' => [
                    'DimPerusahaan.label_jenis_perusahaan' => 'Jenis instansi',
                    'DimPerusahaan.label_tingkat_instansi' => 'Tingkat instansi',
                    'DimPerusahaan.nama_provinsi'          => 'Provinsi tempat kerja',
                ],
                'Wirausaha' => [
                    'DimWirausaha.label_tingkat_instansi' => 'Tingkat usaha',
                    'DimWirausaha.nama_provinsi'          => 'Provinsi usaha',
                ],
            ],
        ],

    ],
];
