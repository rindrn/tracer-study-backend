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
        ];

        // Jika user adalah admin murni, dia wajib assign program_id secara manual.
        // Jika user adalah prodi, input program_id tidak perlu divalidasi karena akan di-force oleh Controller.
        if ($user && $user->isAdmin()) {
            $rules['program_id'] = ['required', 'exists:oltp.programs,id'];
        }

        return $rules;
    }
}
