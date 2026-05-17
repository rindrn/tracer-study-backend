<?php
// app/Http/Validators/LamProgramValidator.php
namespace App\Http\Validators;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class LamProgramValidator
{
    public function validateStore(array $data): array
    {
        $v = Validator::make($data, [
            'lam_id'       => ['required', 'integer', 'exists:oltp.lams,id'],
            'program_ids'  => ['required', 'array', 'min:1'],
            'program_ids.*'=> ['integer', 'exists:oltp.programs,id'],
        ], [
            'lam_id.required'      => 'LAM wajib dipilih.',
            'lam_id.exists'        => 'LAM tidak ditemukan.',
            'program_ids.required' => 'Minimal 1 program studi harus dipilih.',
            'program_ids.*.exists' => 'Program studi tidak ditemukan.',
        ]);

        if ($v->fails()) throw new ValidationException($v);
        return $v->validated();
    }

    public function validateDestroy(array $data): array
    {
        $v = Validator::make($data, [
            'lam_id'     => ['required', 'integer', 'exists:oltp.lams,id'],
            'program_id' => ['required', 'integer', 'exists:oltp.programs,id'],
        ], [
            'lam_id.required'     => 'LAM wajib dipilih.',
            'program_id.required' => 'Program studi wajib dipilih.',
        ]);

        if ($v->fails()) throw new ValidationException($v);
        return $v->validated();
    }
}