<?php
namespace App\Http\Validators;
 
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
 
class ProgramValidator
{
    public function validateCreate(array $data): array
    {
        return $this->validate($data, isUpdate: false);
    }
 
    public function validateUpdate(array $data, int $id): array
    {
        return $this->validate($data, isUpdate: true, id: $id);
    }
 
    private function validate(array $data, bool $isUpdate = false, int $id = 0): array
    {
        // Rule code: unique kecuali dirinya sendiri saat update
        $codeRule = $isUpdate
            ? "unique:oltp.programs,code,{$id}"
            : 'unique:oltp.programs,code';
 
        $v = Validator::make($data, [
            'name'      => ['required', 'string', 'max:100'],
            'code'      => ['required', 'string', 'max:20', $codeRule],
            'degree'    => ['required', 'in:S1,D3,D4,S2'],
            'is_active' => ['sometimes', 'boolean'],
        ], [
            'name.required'   => 'Nama program studi wajib diisi.',
            'code.required'   => 'Kode program studi wajib diisi.',
            'code.unique'     => 'Kode program studi sudah digunakan.',
            'degree.required' => 'Jenjang wajib diisi.',
            'degree.in'       => 'Jenjang harus salah satu dari: S1, D3, D4, S2.',
        ]);
 
        if ($v->fails()) throw new ValidationException($v);
        return $v->validated();
    }
}
