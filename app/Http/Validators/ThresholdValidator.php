<?php
// app/Http/Validators/ThresholdValidator.php
namespace App\Http\Validators;
 
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
 
class ThresholdValidator
{
    public function validateCreate(array $data): array
    {
        return $this->validate($data);
    }
 
    public function validateUpdate(array $data): array
    {
        return $this->validate($data);
    }
 
    private function validate(array $data): array
    {
        $v = Validator::make($data, [
            'name'          => ['required', 'string', 'max:100'],
            'value'         => ['required', 'numeric', 'min:0'],
            'program_ids'   => ['required', 'array', 'min:1'],
            'program_ids.*' => ['integer', 'exists:oltp.programs,id'],
        ], [
            'name.required'        => 'Nama threshold wajib diisi.',
            'value.required'       => 'Nilai threshold wajib diisi.',
            'value.numeric'        => 'Nilai threshold harus berupa angka.',
            'value.min'            => 'Nilai threshold tidak boleh negatif.',
            'program_ids.required' => 'Minimal 1 program studi harus dipilih.',
            'program_ids.min'      => 'Minimal 1 program studi harus dipilih.',
            'program_ids.*.exists' => 'Program studi tidak ditemukan.',
        ]);
 
        if ($v->fails()) throw new ValidationException($v);
        return $v->validated();
    }
}
