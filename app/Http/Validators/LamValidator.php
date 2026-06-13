<?php
namespace App\Http\Validators;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class LamValidator
{
    public function validateCreate(array $data): array
    {
        return $this->validate($data, [
            'name'         => ['required', 'string', 'max:100'],
            'code'         => ['required', 'string', 'max:20', 'unique:oltp.lams,code'],
            'program_ids'  => ['nullable', 'array'],       // ← opsional saat create
            'program_ids.*'=> ['integer', 'exists:oltp.programs,id'],
        ]);
    }

    public function validateUpdate(array $data, int $id): array
    {
        return $this->validate($data, [
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:20', "unique:oltp.lams,code,{$id}"],
        ]);
    }

    private function validate(array $data, array $rules): array
    {
        $v = Validator::make($data, $rules, [
            'name.required'        => 'Nama LAM wajib diisi.',
            'code.required'        => 'Kode LAM wajib diisi.',
            'code.unique'          => 'Kode LAM sudah digunakan.',
            'program_ids.*.exists' => 'Program studi tidak ditemukan.',
        ]);

        if ($v->fails()) throw new ValidationException($v);
        return $v->validated();
    }
}