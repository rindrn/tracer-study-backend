<?php

/*
|--------------------------------------------------------------------------
| Identitas institusi pemakai SmartTracer
|--------------------------------------------------------------------------
|
| SmartTracer dipasang satu kali untuk satu perguruan tinggi. Nama dan kode
| PT tidak pernah ditulis langsung di kode maupun di seeder — sumbernya
| hanya berkas ini, yang isinya diambil dari `.env`.
|
| Cerminan sisi backend dari `fe-tracer-study/src/config/institution.ts`.
| Nama variabelnya sengaja dibuat sepadan (INSTITUTION_NAME di sini,
| VITE_INSTITUTION_NAME di sana) supaya satu pemasangan cukup mengisi nilai
| yang sama di dua berkas `.env` tanpa perlu mengingat dua penamaan.
|
| Dipakai ETL untuk mengisi `dim_prodi.nama_pt` (Gambar 5.3 proposal), yang
| menjadi jangkar kalau kelak sistem dinaikkan ke banyak institusi.
|
*/

return [

    /**
     * Nama resmi lengkap perguruan tinggi.
     *
     * Bawaannya sengaja netral, bukan POLBAN: pemasangan yang lupa mengisi
     * `.env` harus terlihat belum dikonfigurasi, bukan diam-diam mengaku
     * sebagai institusi lain di dalam data warehouse.
     */
    'name' => env('INSTITUTION_NAME', 'Perguruan Tinggi'),

    /**
     * Kode PT versi PDDIKTI, mis. "044015". Dipakai sebagai nilai bawaan
     * `alumni_profiles.kode_pt` bagi baris yang tidak membawa kode sendiri
     * dari berkas impor.
     */
    'code' => env('INSTITUTION_CODE'),

    /**
     * Template struktur organisasi aktif: politeknik | universitas | institut
     * | custom (DFR-01/DFR-25 di cetak-biru-struktur-dinamis.md).
     *
     * Menentukan baris `org_unit_types` mana yang berlaku untuk pemasangan
     * ini. Fase 1 hanya menyediakan nilai konfigurasi ini (dicerminkan ke
     * `app_settings.institution_structure_template` sebagai flag yang bisa
     * dibaca tanpa reload config) -- logic pemakaiannya (baca kolom teks
     * lama vs `org_unit_id` baru di `EnforcesProdiScope`) baru dikerjakan
     * Fase 2, disengaja belum ada di sini.
     *
     * Bawaannya "politeknik" karena instalasi saat ini (POLBAN) memakai
     * bentuk itu -- lihat backfill DFR-24 di OrgUnitBackfillSeeder.
     */
    'structure_template' => env('INSTITUTION_STRUCTURE_TEMPLATE', 'politeknik'),

];
