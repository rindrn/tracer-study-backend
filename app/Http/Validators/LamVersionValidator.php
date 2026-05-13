<?php
// app/Http/Validators/LamVersionValidator.php
namespace App\Http\Validators;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class LamVersionValidator
{
    public function validateCreate(array $data): array
    {
        return $this->validate($data, [
            'lam_id'       => ['required', 'integer', 'exists:oltp.lams,id'],
            'year'         => ['required', 'integer', 'min:2000', 'max:2100'],
            'version_name' => ['nullable', 'string', 'max:100'],
            'is_active'    => ['nullable', 'boolean'],
        ], [
            'lam_id.required' => 'LAM wajib dipilih.',
            'lam_id.exists'   => 'LAM tidak ditemukan.',
            'year.required'   => 'Tahun wajib diisi.',
            'year.integer'    => 'Tahun harus berupa angka.',
        ]);
    }

    public function validateUpdate(array $data): array
    {
        return $this->validate($data, [
            'version_name' => ['nullable', 'string', 'max:100'],
            'is_active'    => ['nullable', 'boolean'],
        ], []);
    }

    // Cek duplikat lam_id + year secara manual
    public function assertUniqueVersion(int $lamId, int $year, ?int $excludeId = null): void
    {
        $query = DB::connection('oltp')
            ->table('lam_versions')
            ->where('lam_id', $lamId)
            ->where('year', $year);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        if ($query->exists()) {
            $v = Validator::make([], []);
            $v->errors()->add('year', 'Versi LAM untuk tahun ini sudah ada.');
            throw new ValidationException($v);
        }
    }

    private function validate(array $data, array $rules, array $messages): array
    {
        $v = Validator::make($data, $rules, $messages);

        if ($v->fails()) throw new ValidationException($v);
        return $v->validated();
    }
}