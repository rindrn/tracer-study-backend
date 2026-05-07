<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class SubmitTracerStudyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; 
    }

    public function rules(): array
    {
        return [
            // Identitas
            'nim' => ['required', 'string', 'max:30'],
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'tahun_lulus' => ['required', 'integer'],
            'kdpstmsmh' => ['required', 'string'],
            'kode_pt' => ['nullable', 'string', 'max:10'],
            'nik' => ['nullable', 'string', 'max:16'],
            'npwp' => ['nullable', 'string', 'max:20'],

            // Kuesioner Wajib (F-Series)
            'f8' => ['required', 'integer', 'in:1,2,3,4,5'],

            // Jika bekerja (1) atau Wiraswasta (3)
            'f502' => ['required_if:f8,1,3', 'nullable', 'integer'],
            'f505' => ['required_if:f8,1,3', 'nullable', 'numeric'],
            'f5a1' => ['required_if:f8,1,3', 'nullable', 'string'], // Provinsi
            'f5a2' => ['required_if:f8,1,3', 'nullable', 'string'], // Kab/Kota
            'f5d' => ['required_if:f8,1,3', 'nullable', 'integer'], // Tingkat tempat kerja
            'f14' => ['required_if:f8,1,3', 'nullable', 'integer'], // Hubungan studi
            'f15' => ['required_if:f8,1,3', 'nullable', 'integer'], // Tingkat pendidikan tepat

            // Khusus Bekerja (1)
            'f1101' => ['required_if:f8,1', 'nullable', 'integer', 'in:1,2,3,4,5,6,7'],
            'f1102' => ['required_if:f1101,5', 'nullable', 'string'],
            'f5b' => ['required_if:f8,1', 'nullable', 'string'], // Nama perusahaan

            // Khusus Wiraswasta (3)
            'f5c' => ['required_if:f8,3', 'nullable', 'integer'], // Jabatan wiraswasta
            
            // Lanjut Pendidikan (4)
            'f18a' => ['required_if:f8,4', 'nullable', 'integer'], // Sumber biaya
            'f18b' => ['required_if:f8,4', 'nullable', 'string'], // Perguruan Tinggi
            'f18c' => ['required_if:f8,4', 'nullable', 'string'], // Program Studi
            'f18d' => ['required_if:f8,4', 'nullable', 'date'], // Tanggal Masuk

            // Lanjut Belajar umum
            'f1201' => ['required', 'integer'], // Sumberdana pembiayaan kuliah saat lulus
            'f1202' => ['required_if:f1201,7', 'nullable', 'string'],

            // Kompetensi (f1761 - f1774)
            'f1761' => ['required', 'integer', 'between:1,5'], // Etika (Lulus)
            'f1762' => ['required', 'integer', 'between:1,5'], // Etika (Saat ini)
            'f1763' => ['required', 'integer', 'between:1,5'], // Keahlian (Lulus)
            'f1764' => ['required', 'integer', 'between:1,5'], // Keahlian (Saat ini)
            'f1765' => ['required', 'integer', 'between:1,5'], // B Ing (Lulus)
            'f1766' => ['required', 'integer', 'between:1,5'], // B Ing (Saat ini)
            'f1767' => ['required', 'integer', 'between:1,5'], // TI (Lulus)
            'f1768' => ['required', 'integer', 'between:1,5'], // TI (Saat ini)
            'f1769' => ['required', 'integer', 'between:1,5'], // Komunikasi (Lulus)
            'f1770' => ['required', 'integer', 'between:1,5'], // Komunikasi (Saat ini)
            'f1771' => ['required', 'integer', 'between:1,5'], // Kerjasama (Lulus)
            'f1772' => ['required', 'integer', 'between:1,5'], // Kerjasama (Saat ini)
            'f1773' => ['required', 'integer', 'between:1,5'], // Pengembangan (Lulus)
            'f1774' => ['required', 'integer', 'between:1,5'], // Pengembangan (Saat ini)

            // Metode pembelajaran (f21 - f27)
            'f21' => ['required', 'integer', 'between:1,5'],
            'f22' => ['required', 'integer', 'between:1,5'],
            'f23' => ['required', 'integer', 'between:1,5'],
            'f24' => ['required', 'integer', 'between:1,5'],
            'f25' => ['required', 'integer', 'between:1,5'],
            'f26' => ['required', 'integer', 'between:1,5'],
            'f27' => ['required', 'integer', 'between:1,5'],

            // Mencari Pekerjaan
            'f301' => ['required', 'integer', 'in:1,2,3'],
            'f302' => ['required_if:f301,1', 'nullable', 'integer'], // Bulan sebelum
            'f303' => ['required_if:f301,2', 'nullable', 'integer'], // Bulan sesudah

            // Bagaimana mencari kerja (Multiple choice 0/1)
            'f401' => ['nullable', 'boolean'],
            'f402' => ['nullable', 'boolean'],
            'f403' => ['nullable', 'boolean'],
            'f404' => ['nullable', 'boolean'],
            'f405' => ['nullable', 'boolean'],
            'f406' => ['nullable', 'boolean'],
            'f407' => ['nullable', 'boolean'],
            'f408' => ['nullable', 'boolean'],
            'f409' => ['nullable', 'boolean'],
            'f410' => ['nullable', 'boolean'],
            'f411' => ['nullable', 'boolean'],
            'f412' => ['nullable', 'boolean'],
            'f413' => ['nullable', 'boolean'],
            'f414' => ['nullable', 'boolean'],
            'f415' => ['nullable', 'boolean'],
            'f416' => ['required_if:f415,1,true,on', 'nullable', 'string'],

            // Lamaran pekerjaaan
            'f6' => ['nullable', 'integer'],
            'f7' => ['nullable', 'integer'],
            'f7a' => ['nullable', 'integer'],

            // Aktif mencari bekerjan (f1001)
            'f1001' => ['required', 'integer', 'in:1,2,3,4,5'],
            'f1002' => ['required_if:f1001,5', 'nullable', 'string'],

            // Grouped boolean questions (array of selected codes)
            'q16_cara_cari_kerja'   => ['nullable', 'array'],
            'q16_cara_cari_kerja.*' => ['nullable', 'string'],
            'q21_alasan_tidak_sesuai' => ['nullable', 'array'],
            'q21_alasan_tidak_sesuai.*' => ['nullable', 'string'],

            // Mengapa mengambil pekerjaan tidak relevan
            'f1601' => ['nullable', 'boolean'],
            'f1602' => ['nullable', 'boolean'],
            'f1603' => ['nullable', 'boolean'],
            'f1604' => ['nullable', 'boolean'],
            'f1605' => ['nullable', 'boolean'],
            'f1606' => ['nullable', 'boolean'],
            'f1607' => ['nullable', 'boolean'],
            'f1608' => ['nullable', 'boolean'],
            'f1609' => ['nullable', 'boolean'],
            'f1610' => ['nullable', 'boolean'],
            'f1611' => ['nullable', 'boolean'],
            'f1612' => ['nullable', 'boolean'],
            'f1613' => ['nullable', 'boolean'],
            'f1614' => ['required_if:f1613,1,true,on', 'nullable', 'string'],
        ];
    }
}
