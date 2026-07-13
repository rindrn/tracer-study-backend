<?php
namespace App\Http\Validators;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ThresholdIndicatorValidator
{
    public function validateUpdate(array $data, int $id): array
    {
        $v = Validator::make($data, [
            // boleh mengandung placeholder {value} kalau indikatornya dinamis
            'name'               => ['sometimes', 'required', 'string', 'max:100'],
            'unit'               => ['sometimes', 'required', 'string', 'max:20'],
            'operator'           => ['sometimes', 'required', 'string', 'in:>=,<=,>,<,='],
            'description'        => ['nullable', 'string'],
            'dynamic_param_unit' => ['nullable', 'string', 'max:20'],
        ], [
            'name.required'     => 'Nama indikator wajib diisi.',
            'unit.required'     => 'Satuan wajib diisi.',
            'operator.required' => 'Operator wajib dipilih.',
            'operator.in'       => 'Operator tidak valid.',
        ]);

        if ($v->fails()) throw new ValidationException($v);
        return $v->validated();
    }
}