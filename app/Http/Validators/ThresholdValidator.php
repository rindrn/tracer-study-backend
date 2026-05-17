<?php
namespace App\Http\Validators;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ThresholdValidator
{
    public function validateCreate(array $data): array
    {
        return $this->validate($data, [
            'lam_version_id' => ['required', 'integer', 'exists:oltp.lam_versions,id'],
            'name'           => ['required', 'string', 'max:100'],
            'value'          => ['required', 'numeric', 'min:0'],
            'unit'           => ['required', 'string', 'max:20'],
            'operator'       => ['required', 'string', 'in:>=,<=,>,<,='],
        ]);
    }

    public function validateUpdate(array $data): array
    {
        // Update hanya boleh ubah value (sesuai API spec #7)
        // name/unit/operator opsional jika ingin fleksibel
        return $this->validate($data, [
            'name'     => ['sometimes', 'string', 'max:100'],
            'value'    => ['required', 'numeric', 'min:0'],
            'unit'     => ['sometimes', 'string', 'max:20'],
            'operator' => ['sometimes', 'string', 'in:>=,<=,>,<,='],
        ]);
    }

    private function validate(array $data, array $rules): array
    {
        $v = Validator::make($data, $rules, [
            'lam_version_id.required' => 'Versi LAM wajib dipilih.',
            'lam_version_id.exists'   => 'Versi LAM tidak ditemukan.',
            'name.required'           => 'Nama threshold wajib diisi.',
            'value.required'          => 'Nilai threshold wajib diisi.',
            'value.numeric'           => 'Nilai threshold harus berupa angka.',
            'value.min'               => 'Nilai threshold tidak boleh negatif.',
            'unit.required'           => 'Satuan wajib diisi (%, bulan, IDR, dst).',
            'operator.required'       => 'Operator wajib diisi.',
            'operator.in'             => 'Operator tidak valid. Gunakan: >=, <=, >, <, =',
        ]);

        if ($v->fails()) throw new ValidationException($v);
        return $v->validated();
    }
}