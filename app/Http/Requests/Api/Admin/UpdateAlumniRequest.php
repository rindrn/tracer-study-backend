<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAlumniRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $user = $this->user();
        $alumniId = $this->route('alumnus'); // id dari route /api/admin/alumni/{alumnus}

        $rules = [
            'nim' => ['sometimes', 'required', 'string', 'max:30', 'unique:oltp.alumni_profiles,nim,' . $alumniId],
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'entry_year' => ['nullable', 'integer'],
            'graduation_year' => ['nullable', 'integer'],
            'gpa' => ['nullable', 'numeric', 'min:0', 'max:4'],
            'nik' => ['nullable', 'string', 'max:16'],
            'npwp' => ['nullable', 'string', 'max:20'],
            'kode_pt' => ['nullable', 'string', 'max:10'],
            'is_active' => ['nullable', 'boolean'],
        ];

        if ($user && $user->isAdmin()) {
            $rules['program_id'] = ['sometimes', 'required', 'exists:oltp.programs,id'];
        }

        return $rules;
    }
}
