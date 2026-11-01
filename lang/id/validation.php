<?php

/*
|--------------------------------------------------------------------------
| Pesan validasi berbahasa Indonesia
|--------------------------------------------------------------------------
|
| Tanpa berkas ini Laravel memakai pesan bawaan berbahasa Inggris
| ("The nim field is required."), sehingga dialog galat di dashboard
| bercampur dua bahasa (NFR-07). Validator yang sudah menulis pesannya
| sendiri tetap memakai pesannya; berkas ini hanya mengisi sisanya.
|
*/

return [

    'accepted'             => ':Attribute harus disetujui.',
    'active_url'           => ':Attribute bukan URL yang valid.',
    'after'                => ':Attribute harus tanggal setelah :date.',
    'after_or_equal'       => ':Attribute harus tanggal setelah atau sama dengan :date.',
    'alpha'                => ':Attribute hanya boleh berisi huruf.',
    'alpha_dash'           => ':Attribute hanya boleh berisi huruf, angka, tanda hubung, dan garis bawah.',
    'alpha_num'            => ':Attribute hanya boleh berisi huruf dan angka.',
    'array'                => ':Attribute harus berupa daftar.',
    'before'               => ':Attribute harus tanggal sebelum :date.',
    'before_or_equal'      => ':Attribute harus tanggal sebelum atau sama dengan :date.',
    'between'              => [
        'array'   => ':Attribute harus berisi antara :min dan :max item.',
        'file'    => ':Attribute harus berukuran antara :min dan :max kilobita.',
        'numeric' => ':Attribute harus bernilai antara :min dan :max.',
        'string'  => ':Attribute harus berisi antara :min dan :max karakter.',
    ],
    'boolean'              => ':Attribute harus bernilai benar atau salah.',
    'confirmed'            => 'Konfirmasi :attribute tidak cocok.',
    'current_password'     => 'Kata sandi saat ini salah.',
    'date'                 => ':Attribute bukan tanggal yang valid.',
    'date_format'          => ':Attribute tidak sesuai format :format.',
    'decimal'              => ':Attribute harus memiliki :decimal angka desimal.',
    'different'            => ':Attribute dan :other harus berbeda.',
    'digits'               => ':Attribute harus terdiri dari :digits digit.',
    'digits_between'       => ':Attribute harus terdiri dari :min sampai :max digit.',
    'distinct'             => ':Attribute memiliki nilai yang duplikat.',
    'email'                => ':Attribute harus berupa alamat surel yang valid.',
    'ends_with'            => ':Attribute harus diakhiri salah satu dari: :values.',
    'exists'               => ':Attribute yang dipilih tidak ditemukan.',
    'file'                 => ':Attribute harus berupa berkas.',
    'filled'               => ':Attribute wajib diisi.',
    'gt'                   => [
        'array'   => ':Attribute harus berisi lebih dari :value item.',
        'file'    => ':Attribute harus berukuran lebih dari :value kilobita.',
        'numeric' => ':Attribute harus lebih besar dari :value.',
        'string'  => ':Attribute harus lebih dari :value karakter.',
    ],
    'gte'                  => [
        'array'   => ':Attribute harus berisi :value item atau lebih.',
        'file'    => ':Attribute harus berukuran :value kilobita atau lebih.',
        'numeric' => ':Attribute harus lebih besar dari atau sama dengan :value.',
        'string'  => ':Attribute harus :value karakter atau lebih.',
    ],
    'image'                => ':Attribute harus berupa gambar.',
    'in'                   => ':Attribute yang dipilih tidak valid.',
    'in_array'             => ':Attribute tidak terdapat dalam :other.',
    'integer'              => ':Attribute harus berupa bilangan bulat.',
    'ip'                   => ':Attribute harus berupa alamat IP yang valid.',
    'json'                 => ':Attribute harus berupa teks JSON yang valid.',
    'lowercase'            => ':Attribute harus huruf kecil.',
    'lt'                   => [
        'array'   => ':Attribute harus berisi kurang dari :value item.',
        'file'    => ':Attribute harus berukuran kurang dari :value kilobita.',
        'numeric' => ':Attribute harus lebih kecil dari :value.',
        'string'  => ':Attribute harus kurang dari :value karakter.',
    ],
    'lte'                  => [
        'array'   => ':Attribute tidak boleh berisi lebih dari :value item.',
        'file'    => ':Attribute harus berukuran :value kilobita atau kurang.',
        'numeric' => ':Attribute harus lebih kecil dari atau sama dengan :value.',
        'string'  => ':Attribute harus :value karakter atau kurang.',
    ],
    'max'                  => [
        'array'   => ':Attribute tidak boleh berisi lebih dari :max item.',
        'file'    => ':Attribute tidak boleh lebih dari :max kilobita.',
        'numeric' => ':Attribute tidak boleh lebih dari :max.',
        'string'  => ':Attribute tidak boleh lebih dari :max karakter.',
    ],
    'mimes'                => ':Attribute harus berkas bertipe: :values.',
    'mimetypes'            => ':Attribute harus berkas bertipe: :values.',
    'min'                  => [
        'array'   => ':Attribute harus berisi minimal :min item.',
        'file'    => ':Attribute harus berukuran minimal :min kilobita.',
        'numeric' => ':Attribute minimal bernilai :min.',
        'string'  => ':Attribute minimal berisi :min karakter.',
    ],
    'not_in'               => ':Attribute yang dipilih tidak valid.',
    'numeric'              => ':Attribute harus berupa angka.',
    'password'             => [
        'letters'       => ':Attribute harus memuat minimal satu huruf.',
        'mixed'         => ':Attribute harus memuat minimal satu huruf besar dan satu huruf kecil.',
        'numbers'       => ':Attribute harus memuat minimal satu angka.',
        'symbols'       => ':Attribute harus memuat minimal satu simbol.',
        'uncompromised' => ':Attribute ini pernah bocor di internet. Gunakan :attribute lain.',
    ],
    'present'              => ':Attribute wajib ada.',
    'prohibited'           => ':Attribute tidak boleh diisi.',
    'regex'                => 'Format :attribute tidak valid.',
    'required'             => ':Attribute wajib diisi.',
    'required_if'          => ':Attribute wajib diisi bila :other adalah :value.',
    'required_unless'      => ':Attribute wajib diisi kecuali :other adalah :values.',
    'required_with'        => ':Attribute wajib diisi bila :values ada.',
    'required_with_all'    => ':Attribute wajib diisi bila :values ada.',
    'required_without'     => ':Attribute wajib diisi bila :values tidak ada.',
    'required_without_all' => ':Attribute wajib diisi bila :values tidak ada.',
    'same'                 => ':Attribute dan :other harus sama.',
    'size'                 => [
        'array'   => ':Attribute harus berisi :size item.',
        'file'    => ':Attribute harus berukuran :size kilobita.',
        'numeric' => ':Attribute harus bernilai :size.',
        'string'  => ':Attribute harus berisi :size karakter.',
    ],
    'starts_with'          => ':Attribute harus diawali salah satu dari: :values.',
    'string'               => ':Attribute harus berupa teks.',
    'timezone'             => ':Attribute harus zona waktu yang valid.',
    'unique'               => ':Attribute sudah dipakai.',
    'uploaded'             => ':Attribute gagal diunggah.',
    'uppercase'            => ':Attribute harus huruf besar.',
    'url'                  => 'Format :attribute tidak valid.',
    'uuid'                 => ':Attribute harus UUID yang valid.',

    'custom' => [],

    /*
    | Nama kolom yang tampil di pesan. Kolom yang tidak terdaftar ditulis
    | apa adanya dengan garis bawah diganti spasi.
    */
    'attributes' => [
        'nim'             => 'NIM',
        'nik'             => 'NIK',
        'npwp'            => 'NPWP',
        'name'            => 'nama',
        'email'           => 'surel',
        'phone'           => 'nomor HP',
        'password'        => 'kata sandi',
        'role'            => 'peran',
        'program_id'      => 'program studi',
        'jurusan_id'      => 'jurusan',
        'fakultas_id'     => 'fakultas',
        'graduation_year' => 'tahun lulus',
        'entry_year'      => 'tahun masuk',
        'gpa'             => 'IPK',
        'kode_pt'         => 'kode PT',
        'title'           => 'judul',
        'description'     => 'deskripsi',
        'status'          => 'status',
        'file'            => 'berkas',
        'tahun'           => 'tahun',
        'per_page'        => 'jumlah baris per halaman',
        'page'            => 'halaman',
        'from'            => 'tanggal awal',
        'to'              => 'tanggal akhir',
        'identifier'      => 'surel atau NIM',
    ],

];
