<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreAlumniRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Hanya yang login yang bisa akses (middleware auth:sanctum meng-handle-nya)
        // Spesifik role check ada di controller
        return true;
    }

    public function rules(): array
    {
        $user = $this->user();
        
        $rules = [
            'nim' => ['required', 'string', 'max:30', 'unique:oltp.alumni_profiles,nim'],
            'name' => ['required', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'entry_year' => ['nullable', 'integer'],
            'graduation_year' => ['nullable', 'integer'],
            'gpa' => ['nullable', 'numeric', 'min:0', 'max:4'],
            'nik' => ['nullable', 'string', 'max:16'],
            'npwp' => ['nullable', 'string', 'max:20'],
            'kode_pt' => ['nullable', 'string', 'max:10'],
            // Kata sandi boleh ditetapkan langsung dari borang Tambah Akun
            // Mahasiswa. Kosong berarti akunnya belum punya kredensial masuk
            // dan harus menunggu penerbitan massal lewat AlumniCredentialService.
            'password' => ['nullable', 'string', 'min:8', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ];

        // Head_tracer wajib assign program_id manual (tidak terikat ke prodi).
        // Kaprodi tidak perlu — program_id auto-fill di AdminAlumniService::create.
        // Role viewer (wadir, kajur) ditolak di service via assertCanWrite.
        if ($user && $user->isHeadTracer()) {
            $rules['program_id'] = ['required', 'exists:oltp.programs,id'];
        }

        return $rules;
    }
}
