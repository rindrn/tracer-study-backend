<?php
namespace App\Http\Validators;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LamValidator
{
    public function validateCreate(array $data): array
    {
        return $this->validate($data, [
            'name' => ['required', 'string', 'max:100'],
            'code' => [
                'required',
                'string',
                'max:20',
                Rule::unique('oltp.lams', 'code'),
            ],
        ]);
    }

    public function validateUpdate(array $data, int $id): array
    {
        return $this->validate($data, [
            'name' => ['required', 'string', 'max:100'],
            'code' => [
                'required',
                'string',
                'max:20',
                Rule::unique('oltp.lams', 'code')->ignore($id),
            ],
        ]);
    }

    private function validate(array $data, array $rules): array
    {
        $v = Validator::make($data, $rules, [
            'name.required' => 'Nama LAM wajib diisi.',
            'code.required' => 'Kode LAM wajib diisi.',
            'code.unique'   => 'Kode LAM sudah digunakan.',
        ]);

        if ($v->fails()) throw new ValidationException($v);
        return $v->validated();
    }
}