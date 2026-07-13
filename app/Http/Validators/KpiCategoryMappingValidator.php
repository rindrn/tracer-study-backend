<?php
namespace App\Http\Validators;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class KpiCategoryMappingValidator
{
    public function validateStore(array $data): array
    {
        return $this->validate($data, [
            'semantic_role'         => ['required', 'string', 'max:50'],
            'option_code'           => ['required', 'string', 'max:80'],
            'option_label_snapshot' => ['nullable', 'string', 'max:200'],
            'kpi_category'          => ['required', 'string', 'max:30'],
            'kpi_category_label'    => ['nullable', 'string', 'max:150'],
            'digunakan_oleh'        => ['required', 'string', 'max:50'],
        ]);
    }

    private function validate(array $data, array $rules): array
    {
        $v = Validator::make($data, $rules, [
            'semantic_role.required'  => 'Semantic role wajib diisi.',
            'option_code.required'    => 'Kode opsi wajib diisi.',
            'kpi_category.required'   => 'Kategori KPI wajib diisi.',
            'digunakan_oleh.required' => 'Field digunakan_oleh wajib diisi.',
        ]);

        if ($v->fails()) throw new ValidationException($v);
        return $v->validated();
    }
}
